<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * Show student login form.
     */
    public function showLogin(): View
    {
        return view('auth.login');
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

        // Support login by email OR nim OR phone
        $user = User::whereRaw('LOWER(email) = ?', [strtolower($identity)])
            ->orWhere('nim', $identity)
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

        if ($user->isAdmin() || $user->isStaff()) {
            return redirect()->intended(route('admin.dashboard'));
        }

        return redirect()->intended(route('dashboard.index'));
    }

    /**
     * Show student registration form.
     */
    public function showRegister(): View
    {
        return view('auth.register');
    }

    /**
     * Handle student registration submission.
     */
    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc,dns', 'max:255', 'unique:users,email'],
            'nim' => ['required', 'string', 'max:30', 'unique:users,nim'],
            'phone' => ['nullable', 'string', 'max:20'],
            'angkatan' => ['required', 'string', 'max:10'],
            'prodi' => ['required', 'string', 'max:100'],
            'university' => ['required', 'string', 'max:255'],
            'kip_status' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'email.required' => 'Email akademik wajib diisi.',
            'email.unique' => 'Email ini sudah terdaftar di sistem.',
            'nim.required' => 'NIM wajib diisi.',
            'nim.unique' => 'NIM ini sudah terdaftar di sistem.',
            'angkatan.required' => 'Tahun angkatan wajib dipilih.',
            'prodi.required' => 'Program studi wajib dipilih.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal terdiri dari 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        $role = \App\Enums\UserRole::MahasiswaKipk;

        $user = User::create([
            'name' => trim($validated['name']),
            'email' => strtolower(trim($validated['email'])),
            'nim' => trim($validated['nim']),
            'phone' => $validated['phone'] ? trim($validated['phone']) : null,
            'angkatan' => trim($validated['angkatan']),
            'prodi' => trim($validated['prodi']),
            'university' => trim($validated['university']),
            'kip_status' => $validated['kip_status'],
            'verification_status' => 'Pending',
            'role' => $role,
            'is_blocked' => false,
            'password' => Hash::make($validated['password']),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard.index')->with('success', 'Selamat datang di Portal SEMADIKSI UNUSA!');
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
                \App\Enums\UserRole::SuperAdmin->value,
                \App\Enums\UserRole::VerifikatorBerkas->value,
                \App\Enums\UserRole::PenilaiKarya->value,
            ])
            ->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            RateLimiter::hit($throttleKey, 60);

            return back()->withErrors([
                'email' => 'Kredensial administrator tidak valid atau Anda bukan admin.',
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

        return redirect()->intended(route('admin.dashboard'));
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
