<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\KeycloakService;
use App\Support\MenuAdmin;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class KeycloakController extends Controller
{
    public function __construct(
        private readonly KeycloakService $keycloak,
    ) {}

    /**
     * Redirect pengguna ke halaman login Keycloak UNUSA dengan PKCE & state.
     */
    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->keycloak->isActive()) {
            abort(404);
        }

        // Menekan tombol SSO membersihkan galat dan isian formulir kata sandi sebelumnya
        $request->session()->forget(['errors', '_old_input']);

        $state = Str::random(40);
        $codeVerifier = Str::random(64);
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        $request->session()->put('keycloak_oauth_state', $state);
        $request->session()->put('keycloak_code_verifier', $codeVerifier);

        $authUrl = $this->keycloak->getAuthorizationUrl($state, $codeChallenge);

        return redirect()->away($authUrl);
    }

    /**
     * Handle callback dari Keycloak UNUSA.
     */
    public function callback(Request $request): RedirectResponse
    {
        if (! $this->keycloak->isActive()) {
            abort(404);
        }

        $storedState = $request->session()->pull('keycloak_oauth_state');
        $codeVerifier = $request->session()->pull('keycloak_code_verifier');
        $state = (string) $request->query('state', '');

        if ($storedState === null || $storedState === '' || ! hash_equals($storedState, $state)) {
            Log::warning('Keycloak SSO gagal: state tidak cocok');
            abort(403, 'Sesi autentikasi tidak valid atau telah kedaluwarsa.');
        }

        if ($request->has('error')) {
            $request->session()->forget('_old_input');

            return redirect()->route('login')->withErrors([
                'sso' => 'Proses masuk dengan akun UNUSA dibatalkan atau ditolak.',
            ]);
        }

        $code = (string) $request->query('code', '');
        if ($code === '' || empty($codeVerifier)) {
            Log::warning('Keycloak SSO gagal: kode penukaran gagal');
            $request->session()->forget('_old_input');

            return redirect()->route('login')->withErrors([
                'sso' => 'Otorisasi gagal. Silakan coba kembali.',
            ]);
        }

        $tokenData = $this->keycloak->exchangeCode($code, $codeVerifier);
        if (! $tokenData || empty($tokenData['access_token'])) {
            Log::warning('Keycloak SSO gagal: kode penukaran gagal');
            $request->session()->forget('_old_input');

            return redirect()->route('login')->withErrors([
                'sso' => 'Gagal memperoleh token akses dari server SSO UNUSA.',
            ]);
        }

        $userInfo = $this->keycloak->getUserInfo($tokenData['access_token']);
        if (! $userInfo || empty($userInfo['sub'])) {
            Log::warning('Keycloak SSO gagal: klaim wajib hilang');
            $request->session()->forget('_old_input');

            return redirect()->route('login')->withErrors([
                'sso' => 'Gagal membaca profil pengguna dari server SSO UNUSA.',
            ]);
        }

        try {
            $user = $this->keycloak->matchOrCreateUser($userInfo);
        } catch (Exception $e) {
            $request->session()->forget('_old_input');

            return redirect()->route('login')->withErrors([
                'sso' => $e->getMessage(),
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('auth_via_sso', true);

        if ($this->keycloak->isLogoutEnabled() && ! empty($tokenData['id_token'])) {
            $request->session()->put('keycloak_id_token', $tokenData['id_token']);
        }

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->saveQuietly();

        if ($user->isAdmin() || $user->isStaff()) {
            return redirect()->intended(MenuAdmin::beranda($user) ?? route('admin.dashboard'));
        }

        return redirect()->intended(route('dashboard.index'));
    }
}
