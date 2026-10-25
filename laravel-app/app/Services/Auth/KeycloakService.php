<?php

namespace App\Services\Auth;

use App\Contracts\KipRecipientProvider;
use App\Contracts\UnusaAcademicProvider;
use App\Enums\UserRole;
use App\Models\AktivasiMahasiswa;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Kip\StatusKipService;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class KeycloakService
{
    public function __construct(
        private readonly UnusaAcademicProvider $academicProvider,
        private readonly KipRecipientProvider $kipProvider,
        private readonly StatusKipService $statusKip,
    ) {}

    /**
     * Memeriksa apakah fitur Keycloak SSO aktif dan konfigurasi wajib telah terisi.
     */
    public function isActive(): bool
    {
        $enabled = (bool) config('services.keycloak.enabled');
        $baseUrl = (string) config('services.keycloak.base_url');
        $secret = (string) config('services.keycloak.client_secret');

        return $enabled && $baseUrl !== '' && $secret !== '';
    }

    /**
     * Ambil endpoint OIDC Keycloak (discovery di-cache, fallback jika server belum merespons).
     *
     * @return array<string, string>
     */
    public function getEndpoints(): array
    {
        $baseUrl = rtrim((string) config('services.keycloak.base_url'), '/');
        $realm = (string) config('services.keycloak.realms', 'UNUSA');
        $cacheKey = "keycloak_endpoints_{$realm}";

        return Cache::remember($cacheKey, now()->addDay(), function () use ($baseUrl, $realm) {
            $discoveryUrl = "{$baseUrl}/realms/{$realm}/.well-known/openid-configuration";
            try {
                $response = Http::timeout(5)->get($discoveryUrl);
                if ($response->successful()) {
                    $data = $response->json();

                    return [
                        'authorization_endpoint' => $data['authorization_endpoint'] ?? "{$baseUrl}/realms/{$realm}/protocol/openid-connect/auth",
                        'token_endpoint' => $data['token_endpoint'] ?? "{$baseUrl}/realms/{$realm}/protocol/openid-connect/token",
                        'userinfo_endpoint' => $data['userinfo_endpoint'] ?? "{$baseUrl}/realms/{$realm}/protocol/openid-connect/userinfo",
                        'end_session_endpoint' => $data['end_session_endpoint'] ?? "{$baseUrl}/realms/{$realm}/protocol/openid-connect/logout",
                    ];
                }
            } catch (\Throwable) {
                // Fallback default format Keycloak
            }

            return [
                'authorization_endpoint' => "{$baseUrl}/realms/{$realm}/protocol/openid-connect/auth",
                'token_endpoint' => "{$baseUrl}/realms/{$realm}/protocol/openid-connect/token",
                'userinfo_endpoint' => "{$baseUrl}/realms/{$realm}/protocol/openid-connect/userinfo",
                'end_session_endpoint' => "{$baseUrl}/realms/{$realm}/protocol/openid-connect/logout",
            ];
        });
    }

    /**
     * URL redirect otorisasi SSO dengan PKCE S256.
     */
    public function getAuthorizationUrl(string $state, string $codeChallenge): string
    {
        $endpoints = $this->getEndpoints();
        $clientId = (string) config('services.keycloak.client_id', 'simadiksi');
        $redirectUri = (string) (config('services.keycloak.redirect') ?: route('auth.login.callback'));

        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid profile email',
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ];

        if ((bool) config('services.keycloak.force_login_prompt', true)) {
            $params['prompt'] = 'login';
        }

        $query = http_build_query($params);

        return $endpoints['authorization_endpoint'].'?'.$query;
    }

    /**
     * Memeriksa apakah fitur RP-initiated logout Keycloak diaktifkan.
     */
    public function isLogoutEnabled(): bool
    {
        return (bool) config('services.keycloak.logout_enabled', false);
    }

    /**
     * URL RP-initiated logout Keycloak lewat end_session_endpoint dari discovery OIDC.
     */
    public function getLogoutUrl(?string $idToken = null): string
    {
        $endpoints = $this->getEndpoints();
        $endSessionEndpoint = $endpoints['end_session_endpoint'];

        $postLogoutRedirectUri = (string) (config('services.keycloak.post_logout_redirect_uri') ?: route('login'));
        $clientId = (string) config('services.keycloak.client_id', 'simadiksi');

        $params = [
            'client_id' => $clientId,
            'post_logout_redirect_uri' => $postLogoutRedirectUri,
        ];

        if (! empty($idToken)) {
            $params['id_token_hint'] = $idToken;
        }

        $separator = str_contains($endSessionEndpoint, '?') ? '&' : '?';

        return $endSessionEndpoint.$separator.http_build_query($params);
    }

    /**
     * Tukar authorization code + code verifier ke token endpoint.
     *
     * @return array<string, mixed>|null
     */
    public function exchangeCode(string $code, string $codeVerifier): ?array
    {
        $endpoints = $this->getEndpoints();
        $clientId = (string) config('services.keycloak.client_id', 'simadiksi');
        $clientSecret = (string) config('services.keycloak.client_secret');
        $redirectUri = (string) (config('services.keycloak.redirect') ?: route('auth.login.callback'));

        try {
            $response = Http::asForm()->timeout(10)->post($endpoints['token_endpoint'], [
                'grant_type' => 'authorization_code',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'code' => $code,
                'code_verifier' => $codeVerifier,
                'redirect_uri' => $redirectUri,
            ]);

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Throwable) {
            // Jangan mencatat kredensial atau detail error token di log.
        }

        return null;
    }

    /**
     * Ambil data profil dari userinfo endpoint lewat HTTPS.
     *
     * @return array<string, mixed>|null
     */
    public function getUserInfo(string $accessToken): ?array
    {
        $endpoints = $this->getEndpoints();

        try {
            $response = Http::withToken($accessToken)
                ->timeout(10)
                ->get($endpoints['userinfo_endpoint']);

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Throwable) {
            // Jangan mencatat token atau data pribadi di log.
        }

        return null;
    }

    /**
     * Pencocokan dan pembuatan akun berdasarkan klaim SSO IdP.
     *
     * Aturan ketat:
     * - users.keycloak_sub dicocokkan lebih dulu.
     * - Pada login pertama, cocokkan NIM dari klaim dengan data master dan akun mahasiswa yang NIM-nya sama.
     * - Jangan mencocokkan lewat email.
     * - Role TIDAK PERNAH diambil dari klaim SSO.
     * - Staf (super_admin, verifikator_berkas, penilai_karya) tidak bisa tertaut lewat SSO untuk pertama kali.
     * - Tolak akun diblokir atau belum teraktivasi.
     *
     * @param  array<string, mixed>  $claims
     *
     * @throws Exception
     */
    public function matchOrCreateUser(array $claims): User
    {
        $sub = trim((string) ($claims['sub'] ?? ''));
        if ($sub === '') {
            Log::warning('Keycloak SSO gagal: klaim wajib hilang');
            throw new Exception('Klaim subjek IdP tidak valid.');
        }

        // 1. Pencocokan langsung lewat keycloak_sub
        $user = User::where('keycloak_sub', $sub)->first();
        if ($user) {
            if ($user->is_blocked) {
                Log::warning('Keycloak SSO gagal: akun diblokir');
                throw new Exception('Akun Anda dinonaktifkan oleh administrator. Hubungi admin SEMADIKSI.');
            }

            if ($user->email_verified_at === null) {
                Log::warning('Keycloak SSO gagal: pengguna belum teraktivasi');
                throw new Exception('Akun belum teraktivasi. Hubungi admin SEMADIKSI.');
            }

            return $user;
        }

        // 2. Login SSO pertama: ambil NIM dari klaim (default: preferred_username)
        $nimClaimKey = (string) config('unusa.keycloak_nim_claim', 'preferred_username');
        $nim = trim((string) ($claims[$nimClaimKey] ?? ''));

        if ($nim === '') {
            Log::warning('Keycloak SSO gagal: klaim wajib hilang');
            throw new Exception('Akun belum terdaftar. Hubungi admin.');
        }

        // 3. Cocokkan NIM dengan master data mahasiswa UNUSA
        $mahasiswa = $this->academicProvider->cariMahasiswaByNim($nim);
        if (! $mahasiswa || empty($mahasiswa['nim'])) {
            Log::warning('Keycloak SSO gagal: NIM tidak ada di data master');
            throw new Exception('Akun belum terdaftar. Hubungi admin.');
        }

        // 4. Cari apakah akun mahasiswa dengan NIM ini sudah ada
        $existing = User::where('nim', $nim)->where('is_unusa', true)->first();

        if ($existing) {
            // Staf tidak boleh tertaut otomatis lewat SSO untuk pertama kalinya
            if ($existing->isStaff() || in_array($existing->role, [UserRole::SuperAdmin, UserRole::VerifikatorBerkas, UserRole::PenilaiKarya], true)) {
                Log::warning('Keycloak SSO gagal: role tidak diizinkan SSO');
                throw new Exception('Staf wajib masuk menggunakan kata sandi.');
            }

            if ($existing->is_blocked) {
                Log::warning('Keycloak SSO gagal: akun diblokir');
                throw new Exception('Akun Anda dinonaktifkan oleh administrator. Hubungi admin SEMADIKSI.');
            }

            if ($existing->email_verified_at === null) {
                if ($existing->role === UserRole::MahasiswaKipk) {
                    $attributes = [
                        'email_verified_at' => now(),
                        'verification_status' => 'Verified',
                        'keycloak_sub' => $sub,
                    ];

                    if (filled($existing->password)) {
                        $attributes['password'] = Hash::make(Str::random(40));
                    }

                    $existing->forceFill($attributes)->save();

                    AktivasiMahasiswa::where('nim', $nim)
                        ->whereNull('dipakai_pada')
                        ->update(['dipakai_pada' => now()]);

                    $penerima = $this->kipProvider->cariByNim($nim);
                    if ($penerima && ! $penerima->user_id) {
                        $this->statusKip->hubungkan($penerima, $existing, StatusKipService::CARA_NIM_SAAT_DAFTAR, beriNotifikasi: false);
                    }

                    AuditLog::record(
                        $existing->id,
                        'sso_activation',
                        'Aktivasi akun mahasiswa KIP-K via SSO',
                        $existing
                    );

                    Log::info('Keycloak SSO: aktivasi via SSO');

                    return $existing;
                }

                Log::warning('Keycloak SSO gagal: pengguna belum teraktivasi');
                throw new Exception('Akun belum teraktivasi. Hubungi admin SEMADIKSI.');
            }

            // Tautkan keycloak_sub ke akun mahasiswa yang sudah ada
            $existing->forceFill(['keycloak_sub' => $sub])->saveQuietly();

            return $existing;
        }

        // 5. Jika akun belum ada di users tetapi NIM ada di master mahasiswa:
        // Buat dan aktifkan akun baru (identitas sudah dibuktikan IdP).
        $penerima = $this->kipProvider->cariByNim($nim);
        $penerimaBebas = $penerima && ! $penerima->user_id;

        $newUser = new User([
            'name' => $mahasiswa['nama_lengkap'] ?? ($claims['name'] ?? 'Mahasiswa UNUSA'),
            'email' => strtolower($mahasiswa['email_kampus']),
            'nim' => $mahasiswa['nim'],
            'phone' => $mahasiswa['no_telepon'] ?? null,
            'angkatan' => $mahasiswa['angkatan'] ?? null,
            'prodi' => $mahasiswa['program_studi'] ?? ($mahasiswa['program_studi_teks'] ?? 'Umum'),
            'university' => 'Universitas Nahdlatul Ulama Surabaya',
            'asal_kampus' => 'Universitas Nahdlatul Ulama Surabaya',
            'program_studi_teks' => $mahasiswa['program_studi'] ?? ($mahasiswa['program_studi_teks'] ?? 'Umum'),
            'program_studi_id' => $mahasiswa['program_studi_id'] ?? null,
            'fakultas_id' => $mahasiswa['fakultas_id'] ?? null,
            'kip_status' => 'Umum',
            'password' => Hash::make(Str::random(32)),
        ]);

        $newUser->forceFill([
            'role' => UserRole::UserUmum,
            'verification_status' => 'Verified',
            'is_blocked' => false,
            'jalur_pendaftaran' => User::JALUR_UNUSA,
            'is_unusa' => true,
            'email_verified_at' => now(),
            'keycloak_sub' => $sub,
        ])->save();

        if ($penerimaBebas) {
            $this->statusKip->hubungkan($penerima, $newUser, StatusKipService::CARA_NIM_SAAT_DAFTAR, beriNotifikasi: false);
        }

        return $newUser;
    }
}
