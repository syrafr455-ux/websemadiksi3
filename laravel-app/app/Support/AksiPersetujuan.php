<?php

namespace App\Support;

use App\Enums\KegiatanStatus;
use App\Models\Kegiatan;
use App\Models\User;

/**
 * Tombol alur persetujuan kegiatan untuk satu pengguna, dipakai daftar dan halaman detail admin.
 *
 * Ini hanya petunjuk tampilan. Kewenangan sebenarnya dicek ulang di
 * Admin\KegiatanController (ajukan, tarikDraft, setujui, tolak) dan KegiatanPolicy.
 */
final class AksiPersetujuan
{
    /**
     * @return array{can_ajukan: bool, can_tarik_draft: bool, can_setujui: bool, can_tolak: bool, keputusan_saya: ?string, alasan_nonaktif: ?string}
     */
    public static function untuk(Kegiatan $kegiatan, ?User $user): array
    {
        $aksi = [
            'can_ajukan' => false,
            'can_tarik_draft' => false,
            'can_setujui' => false,
            'can_tolak' => false,
            'keputusan_saya' => null,
            'alasan_nonaktif' => null,
        ];

        if (! $user || ! $user->isSuperAdmin() || $user->is_blocked) {
            return $aksi;
        }

        $pembuat = $kegiatan->created_by === $user->id;
        // Server: pembuat (atau kegiatan tanpa pembuat) yang mengajukan/menarik; selain pembuat yang memutuskan.
        $bolehMengajukan = $pembuat || ! $kegiatan->created_by;
        $bolehMemutuskan = ! $pembuat;

        $aksi['keputusan_saya'] = $kegiatan->persetujuan->firstWhere('user_id', $user->id)?->keputusan;
        $aksi['can_ajukan'] = $bolehMengajukan && $kegiatan->status === KegiatanStatus::Draft;
        $aksi['can_tarik_draft'] = $bolehMengajukan && $kegiatan->status === KegiatanStatus::Diajukan;

        $bisaMemberiSuara = $bolehMemutuskan && $kegiatan->status === KegiatanStatus::Diajukan && $aksi['keputusan_saya'] === null;
        $aksi['can_setujui'] = $bisaMemberiSuara;
        $aksi['can_tolak'] = $bisaMemberiSuara;

        if ($bolehMemutuskan && $kegiatan->status === KegiatanStatus::Draft) {
            $aksi['alasan_nonaktif'] = 'Menunggu diajukan oleh '.($kegiatan->createdBy?->name ?? 'pembuatnya');
        }

        return $aksi;
    }
}
