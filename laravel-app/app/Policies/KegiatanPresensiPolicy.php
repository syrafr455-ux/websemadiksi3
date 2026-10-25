<?php

namespace App\Policies;

use App\Models\KegiatanPresensi;
use App\Models\User;

class KegiatanPresensiPolicy
{
    /**
     * Hanya super admin yang dapat mengakses halaman manajemen presensi & kehadiran.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Tanda tangan adalah data pribadi peserta yang bersifat rahasia.
     * Hanya super admin yang berhak melihat pratinjau tanda tangan digital.
     * Tidak pernah diizinkan untuk publik atau role staf lain.
     */
    public function viewSignature(User $user, ?KegiatanPresensi $presensi = null): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Hanya super admin yang dapat mengekspor rekapitulasi data presensi.
     */
    public function export(User $user): bool
    {
        return $user->isSuperAdmin();
    }
}
