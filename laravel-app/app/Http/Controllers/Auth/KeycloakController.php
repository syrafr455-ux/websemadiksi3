<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\KeycloakService;
use App\Support\MenuAdmin;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            abort(403, 'Sesi autentikasi tidak valid atau telah kedaluwarsa.');
        }

        if ($request->has('error')) {
            return redirect()->route('login')->withErrors([
                'identity' => 'Proses masuk dengan akun UNUSA dibatalkan atau ditolak.',
            ]);
        }

        $code = (string) $request->query('code', '');
        if ($code === '' || empty($codeVerifier)) {
            return redirect()->route('login')->withErrors([
                'identity' => 'Otorisasi gagal. Silakan coba kembali.',
            ]);
        }

        $tokenData = $this->keycloak->exchangeCode($code, $codeVerifier);
        if (! $tokenData || empty($tokenData['access_token'])) {
            return redirect()->route('login')->withErrors([
                'identity' => 'Gagal memperoleh token akses dari server SSO UNUSA.',
            ]);
        }

        $userInfo = $this->keycloak->getUserInfo($tokenData['access_token']);
        if (! $userInfo || empty($userInfo['sub'])) {
            return redirect()->route('login')->withErrors([
                'identity' => 'Gagal membaca profil pengguna dari server SSO UNUSA.',
            ]);
        }

        try {
            $user = $this->keycloak->matchOrCreateUser($userInfo);
        } catch (Exception $e) {
            return redirect()->route('login')->withErrors([
                'identity' => $e->getMessage(),
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('auth_via_sso', true);

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
