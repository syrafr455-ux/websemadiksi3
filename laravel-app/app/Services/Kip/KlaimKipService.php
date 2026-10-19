<?php

namespace App\Services\Kip;

use App\Contracts\KipRecipientProvider;
use App\Models\AuditLog;
use App\Models\PenerimaKipk;
use App\Models\User;
use App\Support\KemiripanNama;
use App\Support\NomorKip;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Klaim status KIP-K oleh akun user_umum jalur UNUSA dengan nomor pendaftaran SIM KIP Kuliah.
 *
 * Berhasil hanya bila: baris ditemukan, belum terhubung/diklaim akun lain, NIM sama (bila baris punya NIM),
 * dan nama cukup mirip (KemiripanNama::AMBANG). Nama kurang mirip -> "perlu ditinjau" super_admin.
 * Pesan gagal netral: tidak membedakan "nomor tidak ada" dan "nomor milik orang lain".
 * Batas: 5 percobaan per 24 jam per akun dan per IP.
 */
class KlaimKipService
{
    public const MAKS_PERCOBAAN = 5;

    public const JENDELA_DETIK = 86400;

    public const BERHASIL = 'berhasil';

    public const DITINJAU = 'ditinjau';

    public const GAGAL = 'gagal';

    public const TERKUNCI = 'terkunci';

    public const PESAN_GAGAL = 'Klaim belum dapat diproses. Periksa kembali nomor pendaftaran SIM KIP Kuliah Anda, atau hubungi admin SEMADIKSI bila Anda yakin nomor tersebut benar.';

    public function __construct(
        private readonly KipRecipientProvider $kip,
        private readonly StatusKipService $statusKip,
    ) {}

    /**
     * @return array{status: string, pesan: string}
     */
    public function klaim(User $user, string $nomor, string $ip): array
    {
        $kunci = ['klaim-kip:user:'.$user->id, 'klaim-kip:ip:'.$ip];
        foreach ($kunci as $k) {
            if (RateLimiter::tooManyAttempts($k, self::MAKS_PERCOBAAN)) {
                $jam = max(1, (int) ceil(RateLimiter::availableIn($k) / 3600));

                return ['status' => self::TERKUNCI, 'pesan' => "Terlalu banyak percobaan klaim. Fitur klaim dikunci sementara; coba lagi dalam {$jam} jam."];
            }
        }
        foreach ($kunci as $k) {
            RateLimiter::hit($k, self::JENDELA_DETIK);
        }

        $penerima = NomorKip::valid($nomor) ? $this->kip->cariByNomorPendaftaran($nomor) : null;
        $alasanGagal = match (true) {
            ! $penerima => 'tidak_ditemukan',
            $penerima->user_id && $penerima->user_id !== $user->id => 'terhubung_akun_lain',
            $penerima->klaim_user_id && $penerima->klaim_user_id !== $user->id => 'diklaim_akun_lain',
            $penerima->nim && $penerima->nim !== $user->nim => 'nim_berbeda',
            default => null,
        };

        if ($alasanGagal) {
            // Alasan hanya dicatat untuk admin; pengguna menerima pesan netral yang sama.
            AuditLog::record($user->id, 'klaim_kip_gagal', "Klaim KIP-K akun {$user->name} (ID {$user->id}) gagal", $user, [
                'alasan' => $alasanGagal,
                'penerima_id' => $penerima?->id,
            ]);

            return ['status' => self::GAGAL, 'pesan' => self::PESAN_GAGAL];
        }

        if (! KemiripanNama::cocok($penerima->nama_lengkap, $user->name)) {
            DB::transaction(fn () => $penerima->forceFill([
                'status_tinjau' => PenerimaKipk::TINJAU_PERLU,
                'alasan_tinjau' => PenerimaKipk::ALASAN_KLAIM_NAMA,
                'klaim_user_id' => $user->id,
                'klaim_pada' => now(),
            ])->save());

            AuditLog::record($user->id, 'klaim_kip_perlu_ditinjau', "Klaim KIP-K akun {$user->name} (ID {$user->id}) menunggu tinjauan admin: nama kurang mirip", $user, [
                'penerima_id' => $penerima->id,
                'skor_nama' => round(KemiripanNama::skor($penerima->nama_lengkap, $user->name), 2),
            ]);

            return ['status' => self::DITINJAU, 'pesan' => 'Klaim Anda sedang ditinjau admin karena nama pada daftar penerima belum cocok sepenuhnya dengan nama akun. Anda akan diberi tahu setelah ditinjau.'];
        }

        DB::transaction(fn () => $this->statusKip->hubungkan($penerima, $user, StatusKipService::CARA_KLAIM_NOMOR));

        return ['status' => self::BERHASIL, 'pesan' => 'Klaim berhasil. Akun Anda kini tercatat sebagai penerima KIP Kuliah.'];
    }
}
