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
     * Dipanggil setelah impor daftar penerima. (Penautan akun lewat NIM & penandaan penerima
     * yang hilang dari daftar ditambahkan bersama alur klaim.)
     *
     * @param  list<string>  $hashDiImpor
     * @return array<string, int>
     */
    public function sinkronkanSetelahImpor(array $hashDiImpor, User $admin): array
    {
        return [];
    }
}
