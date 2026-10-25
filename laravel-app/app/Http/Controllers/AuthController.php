<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Auth\KeycloakService;
use App\Support\MenuAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * Show student login form.
     */
    public function showLogin(KeycloakService $keycloak): View
    {
        return view('auth.login', [
            'ssoAktif' => $keycloak->isActive(),
        ]);
    }

    /**
     * Handle student login submission.
     */
    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'identity' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'identity.required' => 'Email atau NIM wajib diisi.',
            'password.required' => 'Kata sandi wajib diisi.',
        ]);

        $identity = trim($request->input('identity'));
        $throttleKey = Str::transliterate(Str::lower($identity).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()->withErrors([
                'identity' => "Terlalu banyak percobaan masuk. Silakan coba lagi dalam {$seconds} detik.",
            ])->onlyInput('identity');
        }

        // Login dengan email, NIM, atau nomor HP. NIM hanya unik di antara akun UNUSA,
        // jadi login dengan NIM hanya untuk akun UNUSA (peserta umum memakai email/nomor HP).
        $user = User::whereRaw('LOWER(email) = ?', [strtolower($identity)])
            ->orWhere(fn ($q) => $q->where('nim', $identity)->where('is_unusa', true))
            ->orWhere('phone', $identity)
            ->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            RateLimiter::hit($throttleKey, 60);

            return back()->withErrors([
                'identity' => 'Email/NIM atau kata sandi yang Anda masukkan tidak sesuai.',
            ])->onlyInput('identity');
        }

        if ($user->is_blocked) {
            return back()->withErrors([
                'identity' => 'Akun Anda dinonaktifkan oleh administrator. Hubungi admin SEMADIKSI.',
            ])->onlyInput('identity');
        }

        RateLimiter::clear($throttleKey);
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->saveQuietly();

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        if ($user->requiresTwoFactor()) {
            $request->session()->put('two_factor_required', true);

            return redirect()->route($user->hasEnabledTwoFactor() ? 'two-factor.challenge' : 'two-factor.setup');
        }

        if ($user->isAdmin() || $user->isStaff()) {
            // Halaman pertama di menu admin role ini (verifikator/juri tidak punya akses ke ringkasan utama).
            return redirect()->intended(MenuAdmin::beranda($user) ?? route('admin.dashboard'));
        }

        return redirect()->intended(route('dashboard.index'));
    }

    /**
     * Show admin login form.
     */
    public function showAdminLogin(): View
    {
        return view('admin.login');
    }

    /**
     * Handle admin login.
     */
    public function adminLogin(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Email administrator wajib diisi.',
            'password.required' => 'Kata sandi wajib diisi.',
        ]);

        $throttleKey = Str::transliterate('admin|'.Str::lower($request->input('email')).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()->withErrors([
                'email' => "Terlalu banyak percobaan. Silakan coba lagi dalam {$seconds} detik.",
            ])->onlyInput('email');
        }

        $user = User::whereRaw('LOWER(email) = ?', [strtolower(trim($request->input('email')))])
            ->whereIn('role', [
                UserRole::SuperAdmin->value,
                UserRole::VerifikatorBerkas->value,
                UserRole::PenilaiKarya->value,
            ])
            ->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            RateLimiter::hit($throttleKey, 60);

            return back()->withErrors([
                'email' => 'Email atau kata sandi administrator tidak sesuai.',
            ])->onlyInput('email');
        }

        if ($user->is_blocked) {
            return back()->withErrors([
                'email' => 'Akun administrator dinonaktifkan.',
            ])->onlyInput('email');
        }

        RateLimiter::clear($throttleKey);
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->saveQuietly();

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        if ($user->requiresTwoFactor()) {
            $request->session()->put('two_factor_required', true);

            return redirect()->route($user->hasEnabledTwoFactor() ? 'two-factor.challenge' : 'two-factor.setup');
        }

        $targetRoute = match ($user->role) {
            UserRole::PenilaiKarya => route('admin.penilaian'),
            UserRole::VerifikatorBerkas => route('admin.berkas'),
            default => route('admin.dashboard'),
        };

        return redirect()->intended($targetRoute);
    }

    /**
     * Log out of the application.
     */
    public function logout(Request $request): RedirectResponse
    {
        $isAdmin = Auth::check() && Auth::user()->isAdmin();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($isAdmin) {
            return redirect()->route('admin.login')->with('info', 'Anda telah berhasil keluar dari akun administrator.');
        }

        return redirect()->route('home')->with('info', 'Anda telah berhasil keluar.');
    }
}
