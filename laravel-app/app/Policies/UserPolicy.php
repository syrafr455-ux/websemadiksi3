<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Boleh melihat daftar seluruh pengguna di panel admin.
     * HANYA super_admin.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Boleh membuat akun user baru dari panel admin.
     * HANYA super_admin.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Boleh mengubah status blokir akun user.
     * HANYA super_admin, dan tidak boleh memblokir akun sendiri.
     */
    public function toggleBlock(User $user, User $target): bool
    {
        return $user->isSuperAdmin() && (int) $user->id !== (int) $target->id;
    }

    /**
     * Boleh memverifikasi akun pengguna.
     * HANYA super_admin.
     */
    public function verify(User $user, User $target): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Boleh mengubah role atau profil pengguna lain.
     * HANYA super_admin, dan tidak boleh mengubah role diri sendiri.
     */
    public function update(User $user, User $target): bool
    {
        return $user->isSuperAdmin();
    }
}
