<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Support\MenuAdmin;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     */
    protected string $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'nim' => $user->nim,
                    'phone' => $user->phone,
                    'prodi' => $user->prodi,
                    'university' => $user->university,
                    'role' => $user->role instanceof UserRole ? $user->role->value : (string) $user->role,
                    'role_label' => $user->role instanceof UserRole ? $user->role->label() : (string) $user->role,
                    'is_super_admin' => $user->isSuperAdmin(),
                    'is_staff' => $user->isStaff(),
                    'verification_status' => $user->verification_status,
                    'avatar_url' => $user->avatar_url,
                ] : null,
            ],
            // Menu panel admin sesuai role (sumber yang sama dengan layouts/admin.blade.php).
            'menu_admin' => fn () => MenuAdmin::untuk($user, $request),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'info' => fn () => $request->session()->get('info'),
            ],
            'csrf_token' => csrf_token(),
        ]);
    }
}
