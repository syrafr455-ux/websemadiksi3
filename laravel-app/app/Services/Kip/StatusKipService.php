<?php

namespace App\Services\Kip;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\PenerimaKipk;
use App\Models\User;
use App\Notifications\StatusKipkDiperbarui;

/**
 * Perubahan status KIP-K akun (penautan ke daftar penerima, naik/turun role) beserta audit & notifikasi.
 * Audit TIDAK PERNAH memuat nomor pendaftaran; cukup id baris penerima.
 */
class StatusKipService
{
    public const CARA_NIM_SAAT_DAFTAR = 'nim_saat_daftar';

    public const CARA_KLAIM_NOMOR = 'klaim_nomor';

    public const CARA_IMPOR_NIM = 'impor_nim';

    public const CARA_ADMIN = 'persetujuan_admin';

    /**
     * Tautkan baris penerima ke akun dan jadikan akun mahasiswa_kipk.
     */
    public function hubungkan(PenerimaKipk $penerima, User $user, string $cara, ?User $pelaku = null, bool $beriNotifikasi = true, ?string $alasan = null): void
    {
        $roleLama = $user->role;

        $penerima->forceFill([
            'user_id' => $user->id,
            'cara_terhubung' => $cara,
            'dicocokkan_pada' => now(),
            'nim' => $penerima->nim ?: $user->nim,
            'status_tinjau' => PenerimaKipk::TINJAU_NORMAL,
            'alasan_tinjau' => null,
            'klaim_user_id' => null,
            'klaim_pada' => null,
        ])->save();

        $user->forceFill([
            'role' => UserRole::MahasiswaKipk,
            'kip_status' => 'KIP UNUSA',
            'verification_status' => 'Verified',
        ])->save();

        AuditLog::record($pelaku?->id ?? $user->id, 'status_kip_berubah', sprintf(
            'Status KIP-K akun %s (ID %d): %s -> mahasiswa_kipk via %s (penerima #%d)%s',
            $user->name,
            $user->id,
            $roleLama instanceof UserRole ? $roleLama->value : (string) $roleLama,
            $cara,
            $penerima->id,
            $alasan ? ". Alasan: {$alasan}" : ''
        ), $user, ['penerima_id' => $penerima->id, 'cara' => $cara]);

        if ($beriNotifikasi) {
            $user->notify(new StatusKipkDiperbarui(true, 'Akun SEMADIKSI Anda kini tercatat sebagai penerima KIP Kuliah. Menu layanan KIP-K sudah dapat digunakan.'));
        }
    }

    /**
     * Cabut status KIP-K akun (role -> user_umum) dan lepaskan tautan baris penerimanya.
     */
    public function cabut(User $user, User $admin, string $alasan): void
    {
        $penerima = $user->penerimaKipk;
        $penerima?->forceFill([
            'user_id' => null,
            'cara_terhubung' => null,
            'status_tinjau' => PenerimaKipk::TINJAU_NORMAL,
            'alasan_tinjau' => null,
            'catatan_tinjau' => "Dicabut admin: {$alasan}",
        ])->save();

        $roleLama = $user->role;
        $user->forceFill(['role' => UserRole::UserUmum, 'kip_status' => 'Umum'])->save();

        AuditLog::record($admin->id, 'status_kip_berubah', sprintf(
            'Status KIP-K akun %s (ID %d) dicabut: %s -> user_umum%s. Alasan: %s',
            $user->name,
            $user->id,
            $roleLama instanceof UserRole ? $roleLama->value : (string) $roleLama,
            $penerima ? " (tautan penerima #{$penerima->id} dilepas)" : '',
            $alasan
        ), $user, ['penerima_id' => $penerima?->id, 'cara' => 'cabut_admin']);

        $user->notify(new StatusKipkDiperbarui(false, 'Status penerima KIP Kuliah pada akun SEMADIKSI Anda dicabut oleh admin. Hubungi admin bila menurut Anda ini keliru.'));
    }

    /**
     * Tetapkan akun sebagai penerima KIP-K secara manual (tanpa baris daftar penerima).
     */
    public function tetapkanManual(User $user, User $admin, string $alasan): void
    {
        $roleLama = $user->role;
        $user->forceFill(['role' => UserRole::MahasiswaKipk, 'kip_status' => 'KIP UNUSA', 'verification_status' => 'Verified'])->save();

        AuditLog::record($admin->id, 'status_kip_berubah', sprintf(
            'Status KIP-K akun %s (ID %d) ditetapkan manual: %s -> mahasiswa_kipk. Alasan: %s',
            $user->name,
            $user->id,
            $roleLama instanceof UserRole ? $roleLama->value : (string) $roleLama,
            $alasan
        ), $user, ['cara' => 'manual_admin']);

        $user->notify(new StatusKipkDiperbarui(true, 'Akun SEMADIKSI Anda ditetapkan sebagai penerima KIP Kuliah oleh admin.'));
    }

    /**
     * Setujui klaim yang perlu ditinjau: tautkan baris ke akun pengklaim.
     */
    public function setujuiKlaim(PenerimaKipk $penerima, User $admin, string $alasan): void
    {
        $pengklaim = $penerima->pengklaim;
        abort_unless($pengklaim && $penerima->alasan_tinjau === PenerimaKipk::ALASAN_KLAIM_NAMA && ! $penerima->user_id, 422, 'Tidak ada klaim yang bisa disetujui.');
        abort_if($pengklaim->penerimaKipk()->exists(), 422, 'Akun pengklaim sudah terhubung ke penerima lain.');

        $this->hubungkan($penerima, $pengklaim, self::CARA_ADMIN, $admin, alasan: "Klaim disetujui admin: {$alasan}");
        $penerima->forceFill(['catatan_tinjau' => "Klaim disetujui: {$alasan}"])->save();
    }

    /**
     * Tolak klaim yang perlu ditinjau: baris kembali normal, akun tidak berubah.
     */
    public function tolakKlaim(PenerimaKipk $penerima, User $admin, string $alasan): void
    {
        $pengklaim = $penerima->pengklaim;
        abort_unless($pengklaim && $penerima->alasan_tinjau === PenerimaKipk::ALASAN_KLAIM_NAMA, 422, 'Tidak ada klaim yang bisa ditolak.');

        $penerima->forceFill([
            'status_tinjau' => PenerimaKipk::TINJAU_NORMAL,
            'alasan_tinjau' => null,
            'klaim_user_id' => null,
            'klaim_pada' => null,
            'catatan_tinjau' => "Klaim ditolak: {$alasan}",
        ])->save();

        AuditLog::record($admin->id, 'klaim_kip_ditolak', "Klaim KIP-K akun {$pengklaim->name} (ID {$pengklaim->id}) atas penerima #{$penerima->id} ditolak. Alasan: {$alasan}", $pengklaim, [
            'penerima_id' => $penerima->id,
        ]);

        $pengklaim->notify(new StatusKipkDiperbarui(false, 'Klaim status KIP Kuliah Anda belum dapat disetujui. Hubungi admin SEMADIKSI untuk informasi lebih lanjut.'));
    }

    /**
     * Penerima yang hilang dari impor: tetap dipertahankan (penanda dicabut) atau status akunnya dicabut.
     */
    public function tinjauHilang(PenerimaKipk $penerima, User $admin, bool $pertahankan, string $alasan): void
    {
        abort_unless($penerima->alasan_tinjau === PenerimaKipk::ALASAN_HILANG, 422, 'Baris ini tidak sedang ditandai hilang dari impor.');

        if (! $pertahankan && $penerima->user) {
            $this->cabut($penerima->user, $admin, $alasan);

            return;
        }

        $penerima->forceFill([
            'status_tinjau' => PenerimaKipk::TINJAU_NORMAL,
            'alasan_tinjau' => null,
            'catatan_tinjau' => "Dipertahankan walau tidak ada di impor: {$alasan}",
        ])->save();

        AuditLog::record($admin->id, 'penerima_kipk_ditinjau', "Penerima #{$penerima->id} yang hilang dari impor dipertahankan. Alasan: {$alasan}", null, [
            'penerima_id' => $penerima->id,
        ]);
    }

    /**
     * Setelah impor daftar penerima:
     * 1. Baris ber-NIM yang belum terhubung dan cocok dengan akun user_umum jalur UNUSA -> akun dinaikkan
     *    menjadi mahasiswa_kipk (dengan notifikasi). Akun yang sudah mahasiswa_kipk cukup ditautkan.
     * 2. Bila file adalah daftar lengkap: penerima yang tidak ada di file TIDAK diturunkan, hanya ditandai
     *    "perlu_ditinjau" (hilang_dari_impor) untuk super_admin.
     *
     * @param  list<string>  $hashDiImpor
     * @return array<string, int>
     */
    public function sinkronkanSetelahImpor(array $hashDiImpor, User $admin, bool $daftarLengkap = true): array
    {
        $dinaikkan = 0;
        $ditautkan = 0;

        $kandidat = PenerimaKipk::whereIn('nomor_kip_hash', $hashDiImpor)->whereNull('user_id')->whereNotNull('nim')->get();
        foreach ($kandidat as $penerima) {
            $user = User::where('nim', $penerima->nim)->first();
            if (! $user || $user->penerimaKipk()->exists()) {
                continue;
            }

            if ($user->isUmumUnusa()) {
                $this->hubungkan($penerima, $user, self::CARA_IMPOR_NIM, $admin);
                $dinaikkan++;
            } elseif ($user->role === UserRole::MahasiswaKipk) {
                $this->hubungkan($penerima, $user, self::CARA_IMPOR_NIM, $admin, beriNotifikasi: false);
                $ditautkan++;
            }
        }

        $ditandaiHilang = 0;
        if ($daftarLengkap && $hashDiImpor !== []) {
            $ditandaiHilang = PenerimaKipk::whereNotIn('nomor_kip_hash', $hashDiImpor)
                ->where('status_tinjau', PenerimaKipk::TINJAU_NORMAL)
                ->update([
                    'status_tinjau' => PenerimaKipk::TINJAU_PERLU,
                    'alasan_tinjau' => PenerimaKipk::ALASAN_HILANG,
                    'updated_at' => now(),
                ]);

            if ($ditandaiHilang > 0) {
                AuditLog::record($admin->id, 'penerima_kipk_hilang_dari_impor', "{$ditandaiHilang} penerima KIP-K tidak ada di impor terbaru; ditandai perlu ditinjau (status akun tidak diubah)", null, ['jumlah' => $ditandaiHilang]);
            }
        }

        return ['dinaikkan' => $dinaikkan, 'ditautkan' => $ditautkan, 'ditandai_hilang' => $ditandaiHilang];
    }
}
