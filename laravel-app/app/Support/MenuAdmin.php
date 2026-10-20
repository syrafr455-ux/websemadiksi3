<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Satu sumber menu panel admin untuk layouts/admin.blade.php dan AdminLayout.jsx
 * (dibagikan ke React sebagai prop Inertia "menu_admin").
 *
 * Hak akses tidak ditulis ulang di sini: sebuah link tampil hanya bila middleware route-nya
 * ('admin' dan 'role:...') mengizinkan role pengguna, sehingga menu selalu sama dengan aturan akses.
 */
class MenuAdmin
{
    /**
     * Urutan menu. 'aktif' = pola nama route yang menandai link sebagai halaman aktif.
     *
     * @var list<array{route: string, label: string, icon: string, aktif: string}>
     */
    public const ITEM = [
        ['route' => 'admin.dashboard', 'label' => 'Ringkasan Utama', 'icon' => 'dashboard', 'aktif' => 'admin.dashboard'],
        ['route' => 'admin.kegiatan', 'label' => 'Kelola Kegiatan', 'icon' => 'event', 'aktif' => 'admin.kegiatan*'],
        ['route' => 'admin.penilaian', 'label' => 'Penilaian Karya', 'icon' => 'rate_review', 'aktif' => 'admin.penilaian*'],
        ['route' => 'admin.users', 'label' => 'Data Pengguna', 'icon' => 'group', 'aktif' => 'admin.users*'],
        ['route' => 'admin.mahasiswa-unusa', 'label' => 'Data Mahasiswa UNUSA', 'icon' => 'school', 'aktif' => 'admin.mahasiswa-unusa*'],
        ['route' => 'admin.penerima-kipk', 'label' => 'Daftar Penerima KIP-K', 'icon' => 'badge', 'aktif' => 'admin.penerima-kipk*'],
        ['route' => 'admin.attendances', 'label' => 'Presensi & Kehadiran', 'icon' => 'how_to_reg', 'aktif' => 'admin.attendances*'],
        ['route' => 'admin.certificates', 'label' => 'Sertifikat Digital', 'icon' => 'workspace_premium', 'aktif' => 'admin.certificates*'],
        ['route' => 'admin.berkas', 'label' => 'Verifikasi Berkas KIP-K', 'icon' => 'folder_shared', 'aktif' => 'admin.berkas*'],
        ['route' => 'admin.submissions', 'label' => 'Formulir Masuk', 'icon' => 'dynamic_form', 'aktif' => 'admin.submissions*'],
    ];

    /**
     * Menu untuk $user: hanya link yang boleh diakses role-nya. Peserta (bukan staf) tidak punya menu admin.
     *
     * @return list<array{label: string, icon: string, href: string, route: string, active: bool}>
     */
    public static function untuk(?User $user, ?Request $request = null): array
    {
        if (! $user || ! $user->role instanceof UserRole) {
            return [];
        }

        $menu = [];
        foreach (self::ITEM as $item) {
            if (! self::bolehAkses($user->role, $item['route'])) {
                continue;
            }

            $menu[] = [
                'label' => $item['label'],
                'icon' => $item['icon'],
                'href' => route($item['route'], absolute: false),
                'route' => $item['route'],
                'active' => (bool) $request?->routeIs($item['aktif']),
            ];
        }

        return $menu;
    }

    /**
     * Halaman awal panel admin untuk role ini (link menu pertama), null bila tidak punya akses admin.
     */
    public static function beranda(?User $user): ?string
    {
        return self::untuk($user)[0]['href'] ?? null;
    }

    /**
     * Apakah middleware route bernama $namaRoute mengizinkan $role (logika EnsureAdmin + EnsureRole).
     */
    public static function bolehAkses(UserRole $role, string $namaRoute): bool
    {
        $route = Route::getRoutes()->getByName($namaRoute);
        if (! $route) {
            return false;
        }

        foreach ($route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }

            if ($middleware === 'admin' && ! $role->isStaff()) {
                return false;
            }

            if (str_starts_with($middleware, 'role:')) {
                $diizinkan = explode(',', substr($middleware, strlen('role:')));
                // EnsureRole: super_admin selalu lolos.
                if ($role !== UserRole::SuperAdmin && ! in_array($role->value, $diizinkan, true)) {
                    return false;
                }
            }
        }

        return true;
    }
}
