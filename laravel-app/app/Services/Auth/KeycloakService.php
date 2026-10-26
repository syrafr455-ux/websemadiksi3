<?php

namespace App\Services\Auth;

use App\Contracts\KipRecipientProvider;
use App\Contracts\UnusaAcademicProvider;
use App\Enums\UserRole;
use App\Models\AktivasiMahasiswa;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Kip\StatusKipService;
use App\Support\Masking;
use App\Support\NormalisasiNim;
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
     */
    /**
     * Evaluasi kelayakan SSO secara read-only (dipakai bersama oleh callback SSO dan perintah diagnosa).
     */
    public function evaluasiLoginSso(string $nim, ?string $sub = null): SsoEvaluasi
    {
        $nimBersih = NormalisasiNim::bersihkan($nim);
        $kodeRef = 'SSO-'.strtoupper(Str::random(4));

        $userBySub = null;
        if (filled($sub)) {
            $userBySub = User::where('keycloak_sub', $sub)->first();
        }

        // 1. Jika keycloak_sub sudah tertaut ke akun di sistem
        if ($userBySub) {
            // Cek sub bentrok: jika NIM di klaim ada dan berbeda dari NIM akun
            if ($nimBersih !== '' && $userBySub->nim !== null && NormalisasiNim::bersihkan($userBySub->nim) !== $nimBersih) {
                return new SsoEvaluasi(
                    dapatLogin: false,
                    kodeAlasan: 'sub bentrok',
                    pesan: "Login SSO gagal. Sebutkan kode {$kodeRef} kepada admin",
                    kodeRef: $kodeRef,
                    user: $userBySub,
                    subBentrok: true
                );
            }

            if ($userBySub->is_blocked) {
                return new SsoEvaluasi(
                    dapatLogin: false,
                    kodeAlasan: 'akun diblokir',
                    pesan: 'Akun Anda dinonaktifkan, hubungi admin',
                    kodeRef: $kodeRef,
                    user: $userBySub
                );
            }

            if ($userBySub->isStaff() || in_array($userBySub->role, [UserRole::SuperAdmin, UserRole::VerifikatorBerkas, UserRole::PenilaiKarya], true)) {
                return new SsoEvaluasi(
                    dapatLogin: false,
                    kodeAlasan: 'role tidak diizinkan SSO',
                    pesan: 'Akun staf masuk lewat halaman login kata sandi',
                    kodeRef: $kodeRef,
                    user: $userBySub
                );
            }

            $nimCek = $nimBersih !== '' ? $nimBersih : ($userBySub->nim ? NormalisasiNim::bersihkan($userBySub->nim) : '');
            $penerima = $nimCek !== '' ? $this->kipProvider->cariByNim($nimCek) : null;

            if ($userBySub->email_verified_at === null) {
                if ($userBySub->role === UserRole::MahasiswaKipk) {
                    return new SsoEvaluasi(
                        dapatLogin: true,
                        kodeAlasan: 'aktivasi via SSO',
                        pesan: 'Siap diaktivasi via SSO',
                        kodeRef: $kodeRef,
                        user: $userBySub,
                        penerimaKipk: $penerima,
                        perluAktivasi: true
                    );
                }

                return new SsoEvaluasi(
                    dapatLogin: false,
                    kodeAlasan: 'pengguna belum teraktivasi',
                    pesan: 'Akun belum teraktivasi. Hubungi admin SEMADIKSI.',
                    kodeRef: $kodeRef,
                    user: $userBySub
                );
            }

            return new SsoEvaluasi(
                dapatLogin: true,
                kodeAlasan: 'siap login',
                pesan: 'Siap login',
                kodeRef: $kodeRef,
                user: $userBySub,
                penerimaKipk: $penerima
            );
        }

        // 2. Jika belum tertaut keycloak_sub, lakukan pencocokan via NIM master:
        if ($nimBersih === '') {
            return new SsoEvaluasi(
                dapatLogin: false,
                kodeAlasan: 'klaim wajib hilang',
                pesan: 'Akun belum terdaftar. Hubungi admin.',
                kodeRef: $kodeRef
            );
        }

        $mahasiswa = $this->academicProvider->cariMahasiswaByNim($nimBersih);
        if (! $mahasiswa || empty($mahasiswa['nim'])) {
            return new SsoEvaluasi(
                dapatLogin: false,
                kodeAlasan: 'NIM tidak ada di data master',
                pesan: 'Akun belum terdaftar. Hubungi admin.',
                kodeRef: $kodeRef
            );
        }

        // Cek apakah $sub yang diberikan sudah dipakai akun lain
        if (filled($sub)) {
            $userLainDenganSub = User::where('keycloak_sub', $sub)->first();
            if ($userLainDenganSub) {
                return new SsoEvaluasi(
                    dapatLogin: false,
                    kodeAlasan: 'sub bentrok',
                    pesan: "Login SSO gagal. Sebutkan kode {$kodeRef} kepada admin",
                    kodeRef: $kodeRef,
                    user: $userLainDenganSub,
                    masterMahasiswa: $mahasiswa,
                    subBentrok: true
                );
            }
        }

        // Cek apakah ada akun ganda di tabel users dengan NIM ini
        $usersDenganNim = User::where('nim', $nimBersih)->where('is_unusa', true)->get();
        if ($usersDenganNim->count() > 1) {
            return new SsoEvaluasi(
                dapatLogin: false,
                kodeAlasan: 'akun ganda',
                pesan: "Login SSO gagal. Sebutkan kode {$kodeRef} kepada admin",
                kodeRef: $kodeRef,
                masterMahasiswa: $mahasiswa,
                akunGanda: true
            );
        }

        $existing = $usersDenganNim->first();
        $penerima = $this->kipProvider->cariByNim($nimBersih);

        if ($existing) {
            if ($existing->is_blocked) {
                return new SsoEvaluasi(
                    dapatLogin: false,
                    kodeAlasan: 'akun diblokir',
                    pesan: 'Akun Anda dinonaktifkan, hubungi admin',
                    kodeRef: $kodeRef,
                    user: $existing,
                    masterMahasiswa: $mahasiswa,
                    penerimaKipk: $penerima
                );
            }

            if ($existing->isStaff() || in_array($existing->role, [UserRole::SuperAdmin, UserRole::VerifikatorBerkas, UserRole::PenilaiKarya], true)) {
                return new SsoEvaluasi(
                    dapatLogin: false,
                    kodeAlasan: 'role tidak diizinkan SSO',
                    pesan: 'Akun staf masuk lewat halaman login kata sandi',
                    kodeRef: $kodeRef,
                    user: $existing,
                    masterMahasiswa: $mahasiswa,
                    penerimaKipk: $penerima
                );
            }

            if ($existing->email_verified_at === null) {
                if ($existing->role === UserRole::MahasiswaKipk) {
                    return new SsoEvaluasi(
                        dapatLogin: true,
                        kodeAlasan: 'aktivasi via SSO',
                        pesan: 'Siap diaktivasi via SSO',
                        kodeRef: $kodeRef,
                        user: $existing,
                        masterMahasiswa: $mahasiswa,
                        penerimaKipk: $penerima,
                        perluAktivasi: true
                    );
                }

                return new SsoEvaluasi(
                    dapatLogin: false,
                    kodeAlasan: 'pengguna belum teraktivasi',
                    pesan: 'Akun belum teraktivasi. Hubungi admin SEMADIKSI.',
                    kodeRef: $kodeRef,
                    user: $existing,
                    masterMahasiswa: $mahasiswa,
                    penerimaKipk: $penerima
                );
            }

            return new SsoEvaluasi(
                dapatLogin: true,
                kodeAlasan: 'siap login',
                pesan: 'Siap login',
                kodeRef: $kodeRef,
                user: $existing,
                masterMahasiswa: $mahasiswa,
                penerimaKipk: $penerima
            );
        }

        // 3. Jika user belum ada di users tetapi NIM ada di data master:
        // Jika terdaftar sebagai penerima KIP-K bebas, buat akun mahasiswa KIP-K
        if ($penerima && ! $penerima->user_id) {
            return new SsoEvaluasi(
                dapatLogin: true,
                kodeAlasan: 'aktivasi via SSO',
                pesan: 'Siap dibuat dan diaktivasi via SSO',
                kodeRef: $kodeRef,
                masterMahasiswa: $mahasiswa,
                penerimaKipk: $penerima,
                perluDibuat: true
            );
        }

        // Mahasiswa ada di master tetapi bukan penerima KIP-K:
        // Dibuatkan akun user_umum otomatis lewat SSO (kebijakan SSO lama dipulihkan)
        return new SsoEvaluasi(
            dapatLogin: true,
            kodeAlasan: 'buat akun baru via SSO',
            pesan: 'Siap dibuat sebagai User Umum via SSO',
            kodeRef: $kodeRef,
            masterMahasiswa: $mahasiswa,
            penerimaKipk: null,
            perluDibuat: true
        );
    }

    /**
     * Pencocokan dan pembuatan akun berdasarkan klaim SSO IdP.
     *
     * Aturan ketat:
     * - Pencocokan akun hanya lewat keycloak_sub lalu NIM master.
     * - Role TIDAK PERNAH diambil atau diubah dari klaim SSO.
     * - Staf (super_admin, verifikator_berkas, penilai_karya) tidak tertaut lewat SSO dan data akun tidak diubah.
     * - Email tidak dipakai untuk mencocokkan.
     * - Akun mahasiswa_kipk tertunda diaktivasi otomatis saat SSO pertama.
     * - Akun diblokir sengaja tetap ditolak.
     * - Sub bentrok atau akun ganda ditolak dengan kode referensi SSO-XXXX.
     * - Mahasiswa non-KIP-K yang ada di master dibuatkan akun user_umum otomatis.
     *
     * @param  array<string, mixed>  $claims
     *
     * @throws Exception
     */
    public function matchOrCreateUser(array $claims): User
    {
        $sub = trim((string) ($claims['sub'] ?? ''));
        $kodeRef = 'SSO-'.strtoupper(Str::random(4));

        if ($sub === '') {
            Log::warning("Keycloak SSO gagal: klaim wajib hilang [Ref: {$kodeRef}, NIM: -]");
            throw new Exception("Login SSO gagal. Sebutkan kode {$kodeRef} kepada admin");
        }

        // Ambil NIM dari klaim (default: preferred_username)
        $nimClaimKey = (string) config('unusa.keycloak_nim_claim', 'preferred_username');
        $nim = NormalisasiNim::bersihkan((string) ($claims[$nimClaimKey] ?? ''));

        if ($nim === '') {
            // Bila NIM tidak ada di klaim username, cek apakah sub sudah terhubung ke user ber-NIM
            $userBySub = User::where('keycloak_sub', $sub)->first();
            if ($userBySub && $userBySub->nim) {
                $nim = NormalisasiNim::bersihkan($userBySub->nim);
            }
        }

        if ($nim === '') {
            Log::warning("Keycloak SSO gagal: klaim wajib hilang [Ref: {$kodeRef}, NIM: -]");
            throw new Exception('Akun belum terdaftar. Hubungi admin.');
        }

        $nimTersamar = Masking::nim($nim);
        $evaluasi = $this->evaluasiLoginSso($nim, $sub);

        if (! $evaluasi->dapatLogin) {
            Log::warning("Keycloak SSO gagal: {$evaluasi->kodeAlasan} [Ref: {$evaluasi->kodeRef}, NIM: {$nimTersamar}]");
            throw new Exception($evaluasi->pesan);
        }

        // Aktivasi otomatis akun mahasiswa KIP-K yang ada
        if ($evaluasi->perluAktivasi && $evaluasi->user) {
            $user = $evaluasi->user;
            $attributes = [
                'email_verified_at' => now(),
                'verification_status' => 'Verified',
                'keycloak_sub' => $sub,
                'is_unusa' => true,
            ];

            if (filled($user->password)) {
                $attributes['password'] = Hash::make(Str::random(40));
            }

            $user->forceFill($attributes)->save();

            AktivasiMahasiswa::where('nim', $nim)
                ->whereNull('dipakai_pada')
                ->update(['dipakai_pada' => now()]);

            if ($evaluasi->penerimaKipk && ! $evaluasi->penerimaKipk->user_id) {
                $this->statusKip->hubungkan($evaluasi->penerimaKipk, $user, StatusKipService::CARA_NIM_SAAT_DAFTAR, beriNotifikasi: false);
            }

            AuditLog::record(
                $user->id,
                'sso_activation',
                'Aktivasi akun mahasiswa KIP-K via SSO',
                $user
            );

            Log::info("Keycloak SSO: aktivasi via SSO [NIM: {$nimTersamar}]");

            return $user;
        }

        // Akun sudah ada dan siap login
        // ATURAN BAKU: Peran akun TIDAK PERNAH diubah oleh SSO, kata sandi & 2FA utuh
        if ($evaluasi->user) {
            $user = $evaluasi->user;
            if ($user->keycloak_sub !== $sub) {
                $user->forceFill(['keycloak_sub' => $sub])->saveQuietly();
            }

            return $user;
        }

        // Akun baru dibuat (identitas sudah dibuktikan IdP)
        if ($evaluasi->perluDibuat && $evaluasi->masterMahasiswa) {
            $mahasiswa = $evaluasi->masterMahasiswa;

            // Jika penerima KIP-K:
            if ($evaluasi->penerimaKipk && ! $evaluasi->penerimaKipk->user_id) {
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
                    'kip_status' => 'KIP UNUSA',
                    'password' => Hash::make(Str::random(32)),
                ]);

                $newUser->forceFill([
                    'role' => UserRole::MahasiswaKipk,
                    'verification_status' => 'Verified',
                    'is_blocked' => false,
                    'jalur_pendaftaran' => User::JALUR_UNUSA,
                    'is_unusa' => true,
                    'email_verified_at' => now(),
                    'keycloak_sub' => $sub,
                ])->save();

                $this->statusKip->hubungkan($evaluasi->penerimaKipk, $newUser, StatusKipService::CARA_NIM_SAAT_DAFTAR, beriNotifikasi: false);

                AuditLog::record(
                    $newUser->id,
                    'sso_activation',
                    'Aktivasi akun mahasiswa KIP-K via SSO',
                    $newUser
                );

                Log::info("Keycloak SSO: aktivasi via SSO [NIM: {$nimTersamar}]");

                return $newUser;
            }

            // Bukan penerima KIP-K (perilaku lama dipulihkan):
            // Dibuat sebagai akun UserUmum
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

            Log::info("Keycloak SSO: buat akun user umum via SSO [NIM: {$nimTersamar}]");

            return $newUser;
        }

        throw new Exception("Login SSO gagal. Sebutkan kode {$kodeRef} kepada admin");
    }
}
