<?php

namespace App\Policies;

use App\Models\Sertifikat;
use App\Models\User;

class SertifikatPolicy
{
    /**
     * Hanya super admin yang berhak mengakses dan mengelola modul sertifikat digital.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, ?Sertifikat $sertifikat = null): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, ?Sertifikat $sertifikat = null): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, ?Sertifikat $sertifikat = null): bool
    {
        return $user->isSuperAdmin();
    }

    public function revoke(User $user, ?Sertifikat $sertifikat = null): bool
    {
        return $user->isSuperAdmin();
    }

    public function managePenandatangan(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function managePengaturan(User $user): bool
    {
        return $user->isSuperAdmin();
    }
}
