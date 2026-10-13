<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Handle an incoming request.
     *
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!Auth::check()) {
            if ($request->expectsJson() || $request->header('X-Inertia')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            // If attempting admin/staff routes, redirect to admin login
            if ($request->is('admin*')) {
                return redirect()->route('admin.login')->with('error', 'Silakan login terlebih dahulu.');
            }

            return redirect()->route('login')->with('error', 'Silakan masuk ke akun Anda.');
        }

        $user = Auth::user();

        if ($user->is_blocked) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'Akun Anda dinonaktifkan oleh administrator.');
        }

        $userRole = $user->role instanceof UserRole ? $user->role : UserRole::tryFrom((string) $user->role);
        $userRoleValue = $userRole?->value ?? (string) $user->role;

        // super_admin has master access to all role-protected routes
        if ($userRoleValue === UserRole::SuperAdmin->value) {
            return $next($request);
        }

        if (in_array($userRoleValue, $roles, true)) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->header('X-Inertia')) {
            return response()->json(['message' => 'Anda tidak memiliki hak akses untuk tindakan ini.'], 403);
        }

        // Tentukan rute tujuan yang pasti dapat diakses oleh role yang bersangkutan
        $targetRoute = match ($userRole) {
            UserRole::PenilaiKarya => 'admin.penilaian',
            UserRole::VerifikatorBerkas => 'admin.berkas',
            UserRole::SuperAdmin => 'admin.dashboard',
            UserRole::MahasiswaKipk, UserRole::UserUmum => 'dashboard.index',
            default => 'login',
        };

        // Cegah redirect loop jika rute saat ini sama dengan rute tujuan
        if ($request->routeIs($targetRoute) || $request->routeIs($targetRoute . '.*')) {
            abort(403, 'Akses ditolak: Anda tidak memiliki hak akses untuk halaman ini.');
        }

        return redirect()->route($targetRoute)->with('error', 'Akses ditolak: Anda tidak memiliki wewenang untuk halaman tersebut.');
    }
}
