<?php

namespace App\Services\Impor;

use App\Models\AuditLog;
use App\Models\MasterMahasiswaUnusa;
use App\Models\ProgramStudi;
use App\Models\User;
use App\Support\Impor\CsvReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Impor master mahasiswa UNUSA dari CSV: validasi per baris, lalu upsert berdasarkan NIM.
 */
class ImporMasterMahasiswa
{
    public const KOLOM = ['nim', 'nama_lengkap', 'email_kampus', 'program_studi', 'angkatan', 'status_aktif'];

    public const KOLOM_WAJIB = ['nim', 'nama_lengkap', 'email_kampus', 'program_studi'];

    public const ALIAS = [
        'nama' => 'nama_lengkap',
        'email' => 'email_kampus',
        'prodi' => 'program_studi',
        'kode_prodi' => 'program_studi',
        'status' => 'status_aktif',
    ];

    public static function template(): string
    {
        return implode(',', self::KOLOM)."\n"
            ."2241010001,Ahmad Fauzan,ahmad.fauzan@student.unusa.ac.id,FEBTD-S1-SI,2024,aktif\n"
            ."2241020002,Siti Aminah,siti.aminah@student.unusa.ac.id,S1 Gizi,2023,aktif\n";
    }

    /**
     * @return array{kolom_hilang: list<string>, baris: list<array<string, mixed>>, ringkasan: array<string, int>}
     */
    public function periksa(string $isi): array
    {
        $csv = CsvReader::baca($isi, self::ALIAS);
        $kolomHilang = array_values(array_diff(self::KOLOM_WAJIB, $csv['header']));

        $prodiKode = ProgramStudi::pluck('id', 'kode')->mapWithKeys(fn ($id, $kode) => [strtoupper($kode) => $id]);
        $prodiNama = ProgramStudi::pluck('id', 'nama')->mapWithKeys(fn ($id, $nama) => [mb_strtolower($nama) => $id]);
        $nimTerdaftar = MasterMahasiswaUnusa::pluck('nim')->flip();

        $nimDiFile = [];
        foreach ($csv['baris'] as $baris) {
            $nim = $baris['data']['nim'] ?? '';
            if ($nim !== '') {
                $nimDiFile[$nim][] = $baris['baris'];
            }
        }

        $hasil = [];
        foreach ($csv['baris'] as $baris) {
            $data = $baris['data'];
            $pesan = [];

            $validator = Validator::make($data, [
                'nim' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9.\-]+$/'],
                'nama_lengkap' => ['required', 'string', 'max:255'],
                'email_kampus' => ['required', 'email:rfc', 'max:255'],
                'angkatan' => ['nullable', 'regex:/^\d{4}$/'],
                'status_aktif' => ['nullable', 'in:'.implode(',', MasterMahasiswaUnusa::STATUS_AKTIF)],
            ], [
                'nim.required' => 'NIM kosong.',
                'nim.regex' => 'NIM hanya boleh berisi huruf, angka, titik, atau tanda hubung.',
                'nama_lengkap.required' => 'Nama lengkap kosong.',
                'email_kampus.required' => 'Email kampus kosong.',
                'email_kampus.email' => 'Email kampus tidak valid.',
                'angkatan.regex' => 'Angkatan harus 4 digit tahun.',
                'status_aktif.in' => 'Status aktif harus salah satu: '.implode(', ', MasterMahasiswaUnusa::STATUS_AKTIF).'.',
            ]);
            $pesan = $validator->errors()->all();

            $prodiId = null;
            $prodiInput = $data['program_studi'] ?? '';
            if ($prodiInput === '') {
                $pesan[] = 'Program studi kosong.';
            } else {
                $prodiId = $prodiKode[strtoupper($prodiInput)] ?? $prodiNama[mb_strtolower($prodiInput)] ?? null;
                if (! $prodiId) {
                    $pesan[] = 'Program studi tidak dikenal (pakai kode atau nama prodi dari master).';
                }
            }

            $nim = $data['nim'] ?? '';
            if ($nim !== '' && count($nimDiFile[$nim] ?? []) > 1) {
                $lain = array_diff($nimDiFile[$nim], [$baris['baris']]);
                $pesan[] = 'NIM ganda di file (juga di baris '.implode(', ', $lain).').';
            }

            $hasil[] = [
                'baris' => $baris['baris'],
                'nim' => $nim,
                'nama_lengkap' => $data['nama_lengkap'] ?? '',
                'email_kampus' => $data['email_kampus'] ?? '',
                'program_studi_id' => $prodiId,
                'program_studi' => $prodiInput,
                'angkatan' => ($data['angkatan'] ?? '') ?: null,
                'status_aktif' => strtolower(($data['status_aktif'] ?? '') ?: 'aktif'),
                'status' => $pesan ? 'galat' : (isset($nimTerdaftar[$nim]) ? 'perbarui' : 'baru'),
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
     * Simpan baris valid (upsert per NIM). Baris galat dilewati.
     *
     * @return array<string, int>
     */
    public function simpan(string $isi, string $namaFile, User $admin): array
    {
        $periksa = $this->periksa($isi);

        DB::transaction(function () use ($periksa) {
            foreach ($periksa['baris'] as $baris) {
                if ($baris['status'] === 'galat') {
                    continue;
                }

                MasterMahasiswaUnusa::updateOrCreate(['nim' => $baris['nim']], [
                    'nama_lengkap' => $baris['nama_lengkap'],
                    'email_kampus' => strtolower($baris['email_kampus']),
                    'program_studi_id' => $baris['program_studi_id'],
                    'angkatan' => $baris['angkatan'],
                    'status_aktif' => $baris['status_aktif'],
                    'sumber' => 'impor_csv',
                    'diperbarui_pada' => now(),
                ]);
            }
        });

        AuditLog::record($admin->id, 'impor_master_mahasiswa', "Impor master mahasiswa UNUSA dari '{$namaFile}'", null, $periksa['ringkasan']);

        return $periksa['ringkasan'];
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
