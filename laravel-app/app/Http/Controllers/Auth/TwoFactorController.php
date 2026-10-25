<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\TotpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class TwoFactorController extends Controller
{
    /**
     * Tampilkan formulir aktivasi / setup 2FA.
     */
    public function setup(Request $request, TotpService $totp): View|RedirectResponse
    {
        $user = $request->user();

        // Jika sudah aktif, arahkan ke challenge atau beranda
        if ($user->hasEnabledTwoFactor()) {
            if ($request->session()->get('two_factor_authenticated', false)) {
                return redirect()->to($this->redirectPath($user));
            }

            return redirect()->route('two-factor.challenge');
        }

        // Siapkan secret key jika belum ada
        if (empty($user->two_factor_secret)) {
            $user->two_factor_secret = $totp->generateSecret(16);
            $user->save();
        }

        $otpauthUrl = $totp->getOtpauthUrl(
            $user->email,
            config('app.name', 'SEMADIKSI UNUSA'),
            $user->two_factor_secret
        );

        $qrCodeBase64 = null;
        if (class_exists(QrCode::class)) {
            try {
                $qrSvg = QrCode::format('svg')->size(200)->margin(1)->generate($otpauthUrl);
                $qrCodeBase64 = 'data:image/svg+xml;base64,'.base64_encode($qrSvg);
            } catch (\Throwable) {
                $qrCodeBase64 = null;
            }
        }

        return view('auth.two-factor-setup', [
            'secret' => $user->two_factor_secret,
            'otpauthUrl' => $otpauthUrl,
            'qrCodeBase64' => $qrCodeBase64,
        ]);
    }

    /**
     * Konfirmasi aktivasi 2FA dengan kode 6-digit pertama.
     */
    public function confirm(Request $request, TotpService $totp): RedirectResponse
    {
        $user = $request->user();

        $throttleKey = 'two-factor-setup|'.$user->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $minutes = (int) ceil($seconds / 60);

            return back()->withErrors([
                'code' => "Terlalu banyak percobaan. Silakan coba lagi dalam {$minutes} menit.",
            ]);
        }

        $request->validate([
            'code' => ['required', 'string'],
        ], [
            'code.required' => 'Kode autentikasi wajib diisi.',
        ]);

        $code = trim((string) $request->input('code'));

        if (! $totp->verifyCode($user->two_factor_secret, $code, 1)) {
            RateLimiter::hit($throttleKey, 600);

            return back()->withErrors([
                'code' => 'Kode autentikasi tidak valid.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        // Generate 8 recovery codes
        $recoveryData = $totp->generateRecoveryCodes(8);

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $recoveryData['hashed'],
        ])->save();

        // Luluskan 2FA sesi ini dan simpan plain recovery codes satu kali untuk ditampilkan
        $request->session()->forget('two_factor_required');
        $request->session()->put('two_factor_authenticated', true);
        $request->session()->put('recovery_codes_plain', $recoveryData['plain']);

        return redirect()->route('two-factor.recovery-codes');
    }

    /**
     * Tampilkan 8 kode pemulihan satu kali setelah aktivasi berhasil.
     */
    public function recoveryCodes(Request $request): View|RedirectResponse
    {
        $codes = $request->session()->get('recovery_codes_plain', []);

        if (empty($codes)) {
            return redirect()->to($this->redirectPath($request->user()));
        }

        return view('auth.two-factor-recovery-codes', [
            'codes' => $codes,
            'redirectUrl' => $this->redirectPath($request->user()),
        ]);
    }

    /**
     * Tampilkan formulir tantangan 2FA (TOTP / Recovery Code).
     */
    public function challenge(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasEnabledTwoFactor()) {
            return redirect()->route('two-factor.setup');
        }

        if ($request->session()->get('two_factor_authenticated', false)) {
            return redirect()->to($this->redirectPath($user));
        }

        return view('auth.two-factor-challenge');
    }

    /**
     * Verifikasi kode 2FA (TOTP atau kode pemulihan).
     */
    public function verify(Request $request, TotpService $totp): RedirectResponse
    {
        $user = $request->user();

        $throttleKey = 'two-factor|'.$user->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $minutes = (int) ceil($seconds / 60);

            return back()->withErrors([
                'code' => "Terlalu banyak percobaan. Silakan coba lagi dalam {$minutes} menit.",
            ]);
        }

        $isRecovery = $request->filled('recovery_code');

        if ($isRecovery) {
            $recoveryInput = trim((string) $request->input('recovery_code'));
            $res = $totp->consumeRecoveryCode($recoveryInput, $user->two_factor_recovery_codes);

            if (! $res['valid']) {
                RateLimiter::hit($throttleKey, 600);

                return back()->withErrors([
                    'code' => 'Kode autentikasi tidak valid.',
                ]);
            }

            RateLimiter::clear($throttleKey);

            $user->forceFill([
                'two_factor_recovery_codes' => $res['remainingHashed'],
            ])->save();

            $request->session()->regenerate();
            $request->session()->forget('two_factor_required');
            $request->session()->put('two_factor_authenticated', true);

            return redirect()->intended($this->redirectPath($user));
        }

        // Verifikasi kode TOTP 6 digit
        $code = trim((string) $request->input('code'));

        if ($code === '' || ! $totp->verifyCode($user->two_factor_secret, $code, 1)) {
            RateLimiter::hit($throttleKey, 600);

            return back()->withErrors([
                'code' => 'Kode autentikasi tidak valid.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        $request->session()->regenerate();
        $request->session()->forget('two_factor_required');
        $request->session()->put('two_factor_authenticated', true);

        return redirect()->intended($this->redirectPath($user));
    }

    /**
     * URL tujuan setelah lolos autentikasi 2FA.
     */
    protected function redirectPath(User $user): string
    {
        if ($user->isAdmin() || $user->isStaff()) {
            return match ($user->role) {
                UserRole::PenilaiKarya => route('admin.penilaian'),
                UserRole::VerifikatorBerkas => route('admin.berkas'),
                default => route('admin.dashboard'),
            };
        }

        return route('dashboard.index');
    }
}
