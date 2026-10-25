<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorAuthenticated
{
    /**
     * Whitelist nama rute yang dapat diakses tanpa lolos verifikasi 2FA sesi ini.
     *
     * @var list<string>
     */
    protected array $whitelistRouteNames = [
        'two-factor.setup',
        'two-factor.confirm',
        'two-factor.recovery-codes',
        'two-factor.challenge',
        'two-factor.challenge.verify',
        'logout',
        'admin.logout',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Tamu / belum login diserahkan ke middleware auth masing-masing
        if (! $user) {
            return $next($request);
        }

        // Cek apakah role akun mewajibkan 2FA
        if (! $user->requiresTwoFactor()) {
            return $next($request);
        }

        // Siapa pun yang masuk lewat SSO tidak ditanya 2FA
        if ($request->session()->get('auth_via_sso', false)) {
            return $next($request);
        }

        // Jika sesi sudah lulus 2FA, lanjutkan
        if ($request->session()->get('two_factor_authenticated', false)) {
            return $next($request);
        }

        // Wajib saat login dengan kata sandi
        if (! $request->session()->get('two_factor_required', false)) {
            return $next($request);
        }

        // Jangan blokir rute alur 2FA dan rute logout
        $currentRouteName = $request->route()?->getName();
        if ($currentRouteName && in_array($currentRouteName, $this->whitelistRouteNames, true)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Autentikasi dua faktor diperlukan.'], 403);
        }

        // Bila belum aktifkan 2FA, wajib diarahkan ke halaman aktivasi
        if (! $user->hasEnabledTwoFactor()) {
            return redirect()->route('two-factor.setup');
        }

        // Bila sudah aktif tapi belum lolos di sesi ini, minta verifikasi kode TOTP
        return redirect()->route('two-factor.challenge');
    }
}
