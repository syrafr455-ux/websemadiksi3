<?php

namespace App\Services\Impor;

use App\Models\AuditLog;
use App\Models\PenerimaKipk;
use App\Models\User;
use App\Services\Kip\StatusKipService;
use App\Support\Impor\CsvReader;
use App\Support\NomorKip;
use Illuminate\Support\Facades\DB;

/**
 * Impor daftar penerima KIP Kuliah dari UNUSA (CSV berbasis nomor pendaftaran SIM KIP Kuliah).
 * Validasi per baris, upsert berdasarkan nomor_kip_hash. Nomor TIDAK PERNAH ditulis ke pesan,
 * log, atau audit -- pratinjau hanya menampilkan versi tersamar.
 */
class ImporPenerimaKipk
{
    public const KOLOM = ['nomor_pendaftaran', 'nama_lengkap', 'nim', 'program_studi', 'angkatan', 'status_penetapan'];

    public const KOLOM_WAJIB = ['nomor_pendaftaran', 'nama_lengkap'];

    public const ALIAS = [
        'nomor_pendaftaran_kip' => 'nomor_pendaftaran',
        'nomor_pendaftaran_sim_kip' => 'nomor_pendaftaran',
        'nomor_pendaftaran_sim_kip_kuliah' => 'nomor_pendaftaran',
        'no_pendaftaran' => 'nomor_pendaftaran',
        'nama' => 'nama_lengkap',
        'prodi' => 'program_studi',
        'status' => 'status_penetapan',
    ];

    public function __construct(private readonly StatusKipService $statusKip) {}

    public static function template(): string
    {
        return implode(',', self::KOLOM)."\n"
            ."1234.567.89012.1766.761,Ahmad Fauzan,2241010001,S1 Sistem Informasi,2024,Ditetapkan\n"
            ."9876.543.21098.7654.321,Siti Aminah,,S1 Gizi,2023,Ditetapkan\n";
    }

    /**
     * @return array{kolom_hilang: list<string>, baris: list<array<string, mixed>>, ringkasan: array<string, int>}
     */
    public function periksa(string $isi): array
    {
        $csv = CsvReader::baca($isi, self::ALIAS);
        $kolomHilang = array_values(array_diff(self::KOLOM_WAJIB, $csv['header']));

        $hashTerdaftar = PenerimaKipk::pluck('nim', 'nomor_kip_hash');

        $hashDiFile = [];
        $nimDiFile = [];
        foreach ($csv['baris'] as $baris) {
            $nomor = $baris['data']['nomor_pendaftaran'] ?? '';
            if (NomorKip::valid($nomor)) {
                $hashDiFile[NomorKip::hash($nomor)][] = $baris['baris'];
            }
            $nim = $baris['data']['nim'] ?? '';
            if ($nim !== '') {
                $nimDiFile[$nim][] = $baris['baris'];
            }
        }

        $hasil = [];
        foreach ($csv['baris'] as $baris) {
            $data = $baris['data'];
            $pesan = [];
            $nomor = $data['nomor_pendaftaran'] ?? '';
            $hash = null;

            if (trim($nomor) === '') {
                $pesan[] = 'Nomor pendaftaran kosong.';
            } elseif (! NomorKip::valid($nomor)) {
                $pesan[] = 'Nomor pendaftaran tidak valid (harus 8-30 digit; titik/spasi boleh).';
            } else {
                $hash = NomorKip::hash($nomor);
                if (count($hashDiFile[$hash]) > 1) {
                    $lain = array_diff($hashDiFile[$hash], [$baris['baris']]);
                    $pesan[] = 'Nomor pendaftaran ganda di file (juga di baris '.implode(', ', $lain).').';
                }
            }

            if (($data['nama_lengkap'] ?? '') === '') {
                $pesan[] = 'Nama lengkap kosong.';
            }

            $nim = $data['nim'] ?? '';
            if ($nim !== '') {
                if (count($nimDiFile[$nim]) > 1) {
                    $pesan[] = 'NIM ganda di file (juga di baris '.implode(', ', array_diff($nimDiFile[$nim], [$baris['baris']])).').';
                }
                $pemilikNim = PenerimaKipk::where('nim', $nim)->value('nomor_kip_hash');
                if ($pemilikNim && $pemilikNim !== $hash) {
                    $pesan[] = 'NIM sudah tercatat pada nomor pendaftaran lain.';
                }
            }

            if (($data['angkatan'] ?? '') !== '' && ! preg_match('/^\d{4}$/', $data['angkatan'])) {
                $pesan[] = 'Angkatan harus 4 digit tahun.';
            }

            $hasil[] = [
                'baris' => $baris['baris'],
                'hash' => $hash,
                'nomor' => $hash ? NomorKip::normalisasi($nomor) : null,
                'nomor_tersamar' => NomorKip::samarkan($nomor),
                'nama_lengkap' => $data['nama_lengkap'] ?? '',
                'nim' => $nim !== '' ? $nim : null,
                'program_studi_teks' => ($data['program_studi'] ?? '') ?: null,
                'angkatan' => ($data['angkatan'] ?? '') ?: null,
                'status_penetapan' => ($data['status_penetapan'] ?? '') ?: null,
                'status' => $pesan ? 'galat' : ($hash && $hashTerdaftar->has($hash) ? 'perbarui' : 'baru'),
                'pesan' => $pesan,
            ];
        }

        return [
            'kolom_hilang' => $kolomHilang,
            'baris' => $kolomHilang ? [] : $hasil,
            'ringkasan' => $this->ringkas($kolomHilang ? [] : $hasil),
        ];
    }

    /**
     * Pratinjau untuk tampilan: tanpa nomor lengkap dan hash.
     */
    public function pratinjau(string $isi): array
    {
        $periksa = $this->periksa($isi);
        $periksa['baris'] = array_map(fn (array $b) => array_diff_key($b, ['nomor' => true, 'hash' => true]), $periksa['baris']);

        return $periksa;
    }

    /**
     * Simpan baris valid (upsert per nomor_kip_hash), lalu sinkronkan status akun.
     *
     * @return array<string, int>
     */
    public function simpan(string $isi, string $namaFile, User $admin, bool $daftarLengkap = true): array
    {
        $periksa = $this->periksa($isi);
        $ringkasan = $periksa['ringkasan'];

        DB::transaction(function () use ($periksa, $namaFile, $admin, $daftarLengkap, &$ringkasan) {
            $hashDiImpor = [];

            foreach ($periksa['baris'] as $baris) {
                if ($baris['status'] === 'galat') {
                    continue;
                }

                $penerima = PenerimaKipk::firstOrNew(['nomor_kip_hash' => $baris['hash']]);
                $penerima->aturNomor($baris['nomor']);
                $penerima->fill([
                    'nama_lengkap' => $baris['nama_lengkap'],
                    // NIM kosong di file tidak menghapus NIM yang sudah tercatat.
                    'nim' => $baris['nim'] ?? $penerima->nim,
                    'program_studi_teks' => $baris['program_studi_teks'],
                    'angkatan' => $baris['angkatan'],
                    'status_penetapan' => $baris['status_penetapan'],
                    'sumber' => "Impor daftar UNUSA ({$namaFile})",
                    'diimpor_pada' => now(),
                    'diimpor_oleh' => $admin->id,
                ]);

                // Kembali muncul di daftar: penanda "hilang dari impor" dicabut.
                if ($penerima->alasan_tinjau === PenerimaKipk::ALASAN_HILANG) {
                    $penerima->status_tinjau = PenerimaKipk::TINJAU_NORMAL;
                    $penerima->alasan_tinjau = null;
                }

                $penerima->save();
                $hashDiImpor[] = $baris['hash'];
            }

            $ringkasan += $this->statusKip->sinkronkanSetelahImpor($hashDiImpor, $admin, $daftarLengkap);
        });

        AuditLog::record($admin->id, 'impor_penerima_kipk', "Impor daftar penerima KIP-K dari '{$namaFile}'", null, $ringkasan);

        return $ringkasan;
    }

    /**
     * @param  list<array<string, mixed>>  $baris
     * @return array<string, int>
     */
    private function ringkas(array $baris): array
    {
        $hitung = array_count_values(array_column($baris, 'status'));

        return [
            'total' => count($baris),
            'baru' => $hitung['baru'] ?? 0,
            'perbarui' => $hitung['perbarui'] ?? 0,
            'galat' => $hitung['galat'] ?? 0,
        ];
    }
}
