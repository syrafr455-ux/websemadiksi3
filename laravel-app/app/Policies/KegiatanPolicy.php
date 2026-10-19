<?php

namespace App\Policies;

use App\Enums\KegiatanStatus;
use App\Enums\UserRole;
use App\Models\Kegiatan;
use App\Models\User;

class KegiatanPolicy
{
    /**
     * Boleh melihat daftar kegiatan di panel admin enterprise.
     * Hanya super_admin (verifikator_berkas & penilai_karya dilarang CRUD kegiatan).
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Boleh melihat detail kegiatan.
     * Kegiatan published dapat dilihat siapa saja (peserta/publik).
     * Draft / archived kegiatan hanya dapat dilihat oleh super_admin.
     */
    public function view(?User $user, Kegiatan $kegiatan): bool
    {
        if ($kegiatan->status === KegiatanStatus::Published) {
            return true;
        }

        return $user !== null && $user->isSuperAdmin();
    }

    /**
     * Boleh membuat kegiatan baru.
     * HANYA super_admin.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Boleh mengubah kegiatan dan denah kursi.
     * HANYA super_admin.
     */
    public function update(User $user, Kegiatan $kegiatan): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Boleh memperbarui konfigurasi venue, kapasitas, dan jadwal booking kursi.
     * HANYA super_admin.
     */
    public function updateVenueConfig(User $user, Kegiatan $kegiatan): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Boleh menghapus kegiatan.
     * HANYA super_admin.
     */
    public function delete(User $user, Kegiatan $kegiatan): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Boleh mengunggah template sertifikat atau men-generate sertifikat batch.
     * HANYA super_admin.
     */
    public function manageCertificates(User $user, Kegiatan $kegiatan): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Melihat, menilai, mengekspor, dan mengimpor nilai karya: super_admin semua lomba,
     * juri hanya lomba yang ditugaskan kepadanya (pivot kegiatan_penilai).
     */
    public function nilaiKarya(User $user, Kegiatan $kegiatan): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->role === UserRole::PenilaiKarya
            && $kegiatan->penilai()->whereKey($user->id)->exists();
    }
}
