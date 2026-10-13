<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            if ($request->expectsJson() || $request->header('X-Inertia')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            $redirect = redirect()->route('admin.login');
            return $request->hasSession() ? $redirect->with('error', 'Akses ditolak. Anda harus login sebagai Pengelola/Administrator.') : $redirect;
        }

        $user = Auth::user();

        if ($user->is_blocked) {
            Auth::logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
            if ($request->expectsJson() || $request->header('X-Inertia')) {
                return response()->json(['message' => 'Akun administrator Anda dinonaktifkan.'], 403);
            }
            $redirect = redirect()->route('admin.login');
            return $request->hasSession() ? $redirect->with('error', 'Akun administrator Anda dinonaktifkan.') : $redirect;
        }

        // Allow super_admin and any staff role
        if (!$user->isSuperAdmin() && !$user->isStaff()) {
            if ($request->expectsJson() || $request->header('X-Inertia')) {
                return response()->json(['message' => 'Akses ditolak. Anda tidak memiliki izin mengelola sistem.'], 403);
            }
            $redirect = redirect()->route('admin.login');
            return $request->hasSession() ? $redirect->with('error', 'Akses ditolak. Anda tidak memiliki izin mengelola sistem.') : $redirect;
        }

        return $next($request);
    }
}
