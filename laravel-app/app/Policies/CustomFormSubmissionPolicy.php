<?php

namespace App\Policies;

use App\Models\CustomFormSubmission;
use App\Models\User;

class CustomFormSubmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isVerifikator();
    }

    /**
     * Ubah status/catatan formulir: super_admin semua formulir; verifikator hanya formulir di
     * config('unusa.verifikator_form_keys') (pencairan tidak pernah) dari mahasiswa dalam cakupan fakultas/prodinya.
     */
    public function review(User $user, CustomFormSubmission $submission): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isVerifikator()
            && in_array($submission->form?->form_key, CustomFormSubmission::formVerifikator(), true)
            && $user->mencakupMahasiswa($submission->user);
    }
}
