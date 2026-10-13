<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\KipkDocument;
use App\Models\User;

class KipkDocumentPolicy
{
    /**
     * Boleh melihat daftar seluruh berkas KIP-K di panel admin.
     * Hanya super_admin dan verifikator_berkas.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->role === UserRole::VerifikatorBerkas;
    }

    /**
     * Boleh melihat berkas KIP-K.
     * Diizinkan untuk:
     * - Super Admin
     * - Verifikator Berkas
     * - Mahasiswa pemilik berkas itu sendiri
     */
    public function view(User $user, KipkDocument $doc): bool
    {
        if ($user->isSuperAdmin() || $user->role === UserRole::VerifikatorBerkas) {
            return true;
        }

        return (int) $doc->user_id === (int) $user->id;
    }

    /**
     * Boleh mengunduh / membuka tautan file berkas KIP-K.
     * Diizinkan untuk:
     * - Super Admin
     * - Verifikator Berkas
     * - Mahasiswa pemilik berkas itu sendiri
     */
    public function download(User $user, KipkDocument $doc): bool
    {
        return $this->view($user, $doc);
    }

    /**
     * Boleh memverifikasi, menilai, dan mengubah status berkas KIP-K.
     * HANYA super_admin dan verifikator_berkas.
     * Juri (penilai_karya), user umum, dan mahasiswa dilarang.
     */
    public function verify(User $user, KipkDocument $doc): bool
    {
        return $user->isSuperAdmin() || $user->role === UserRole::VerifikatorBerkas;
    }

    /**
     * Boleh mengunggah berkas KIP-K baru.
     * Mahasiswa KIP-K atau Super Admin.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->role === UserRole::MahasiswaKipk;
    }

    /**
     * Boleh menghapus berkas KIP-K.
     * Super Admin, atau Mahasiswa pemilik jika status masih menunggu review.
     */
    public function delete(User $user, KipkDocument $doc): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return (int) $doc->user_id === (int) $user->id && in_array($doc->status, ['Menunggu Review', 'Draft', 'Pending'], true);
    }
}
