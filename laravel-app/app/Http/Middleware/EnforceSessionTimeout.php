<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnforceSessionTimeout
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();
        $isStaff = $user && method_exists($user, 'isStaff') && $user->isStaff();

        // 1. Larangan autentikasi via cookie ingat-saya (remember-me cookie)
        if (Auth::viaRemember()) {
            if ($user && method_exists($user, 'setRememberToken')) {
                $user->setRememberToken(null);
                if (method_exists($user, 'saveQuietly')) {
                    $user->saveQuietly();
                } else {
                    $user->save();
                }
            }

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $targetRoute = $isStaff ? route('admin.login') : route('login');

            if ($request->expectsJson() || $request->is('sesi/ping')) {
                return response()->json([
                    'message' => 'Autentikasi ingat-saya dinonaktifkan. Silakan masuk kembali.',
                ], 401);
            }

            if ($request->header('X-Inertia')) {
                return Inertia::location($targetRoute);
            }

            return redirect()->to($targetRoute);
        }

        // 2. Batas waktu inaktivitas (default 15 menit)
        $timeoutMinutes = (int) config('session.idle_timeout_minutes', 15);
        $timeoutSeconds = $timeoutMinutes * 60;
        $now = now()->timestamp;

        $lastActivity = $request->session()->get('last_activity_time');
        $lastActivityUserId = $request->session()->get('last_activity_user_id');

        // Jika user berbeda dari yang tercatat di sesi (mis. login baru atau pergantian user),
        // inisialisasi awal aktivitas untuk user ini tanpa memicu timeout basi user sebelumnya.
        if ($lastActivityUserId !== $user->id) {
            $request->session()->put('last_activity_user_id', $user->id);
            $request->session()->put('last_activity_time', $now);

            return $next($request);
        }

        if ($lastActivity !== null && ($now - $lastActivity) > $timeoutSeconds) {
            if ($user && method_exists($user, 'setRememberToken')) {
                $user->setRememberToken(null);
                if (method_exists($user, 'saveQuietly')) {
                    $user->saveQuietly();
                } else {
                    $user->save();
                }
            }

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $warningMessage = 'Sesi Anda berakhir karena tidak ada aktivitas selama 15 menit. Silakan masuk kembali.';
            $targetRoute = $isStaff ? route('admin.login') : route('login');

            if ($request->expectsJson() || $request->is('sesi/ping')) {
                return response()->json([
                    'message' => $warningMessage,
                ], 401);
            }

            if ($request->header('X-Inertia')) {
                $request->session()->flash('warning', $warningMessage);

                return Inertia::location($targetRoute);
            }

            $request->session()->flash('warning', $warningMessage);

            return redirect()->to($targetRoute);
        }

        // Simpan / perbarui waktu aktivitas terakhir
        $request->session()->put('last_activity_user_id', $user->id);
        $request->session()->put('last_activity_time', $now);

        return $next($request);
    }
}
