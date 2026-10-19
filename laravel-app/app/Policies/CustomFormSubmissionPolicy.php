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
     * Ubah status/catatan formulir: super_admin semua formulir; verifikator hanya formulir monev
     * dari mahasiswa dalam cakupan fakultas/prodinya.
     */
    public function review(User $user, CustomFormSubmission $submission): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isVerifikator()
            && $submission->form?->form_key === CustomFormSubmission::FORM_VERIFIKATOR
            && $user->mencakupMahasiswa($submission->user);
    }
}
