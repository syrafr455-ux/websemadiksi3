<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Fakultas;
use App\Models\MasterMahasiswaUnusa;
use App\Models\PenerimaKipk;
use App\Models\ProgramStudi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class KeycloakSsoAndSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function setKeycloakConfig(bool $enabled = true, bool $forceLoginPrompt = true): void
    {
        Config::set('services.keycloak.enabled', $enabled);
        Config::set('services.keycloak.base_url', 'https://sso.unusa.ac.id');
        Config::set('services.keycloak.realms', 'UNUSA');
        Config::set('services.keycloak.client_id', 'simadiksi');
        Config::set('services.keycloak.client_secret', 'secret-keycloak-test');
        Config::set('services.keycloak.redirect', 'http://localhost:8000/auth/login/callback');
        Config::set('services.keycloak.logout_enabled', false);
        Config::set('services.keycloak.post_logout_redirect_uri', 'http://localhost:8000/masuk');
        Config::set('services.keycloak.force_login_prompt', $forceLoginPrompt);
    }

    public function test_fitur_nonaktif_mengembalikan_404_tanpa_error(): void
    {
        Config::set('services.keycloak.enabled', false);

        $this->get('/auth/login')->assertNotFound();
        $this->get('/auth/login/callback')->assertNotFound();

        $response = $this->get('/masuk');
        $response->assertOk();
        $response->assertDontSee('Masuk dengan akun UNUSA');
    }

    public function test_tombol_sso_tampil_bila_fitur_aktif(): void
    {
        $this->setKeycloakConfig(true);

        $response = $this->get('/masuk');
        $response->assertOk();
        $response->assertSee('Masuk dengan akun UNUSA');
    }

    public function test_redirect_login_sso_generates_state_and_pkce(): void
    {
        $this->setKeycloakConfig(true);

        $response = $this->get('/auth/login');
        $response->assertRedirect();

        $targetUrl = $response->headers->get('Location');
        $this->assertStringContainsString('https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/auth', $targetUrl);
        $this->assertStringContainsString('client_id=simadiksi', $targetUrl);
        $this->assertStringContainsString('code_challenge=', $targetUrl);
        $this->assertStringContainsString('code_challenge_method=S256', $targetUrl);
        $this->assertStringContainsString('state=', $targetUrl);

        $response->assertSessionHas('keycloak_oauth_state');
        $response->assertSessionHas('keycloak_code_verifier');
    }

    public function test_callback_state_tidak_cocok_mengembalikan_403(): void
    {
        $this->setKeycloakConfig(true);

        $response = $this->withSession(['keycloak_oauth_state' => 'state_benar'])
            ->get('/auth/login/callback?state=state_palsu&code=auth_code_123');

        $response->assertForbidden();
        $this->assertGuest();
    }

    public function test_callback_state_hilang_mengembalikan_403(): void
    {
        $this->setKeycloakConfig(true);

        $response = $this->get('/auth/login/callback?state=apa_saja&code=auth_code_123');
        $response->assertForbidden();
        $this->assertGuest();
    }

    public function test_callback_nim_tidak_ada_di_klaim(): void
    {
        $this->setKeycloakConfig(true);

        Http::fake([
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/token' => Http::response([
                'access_token' => 'dummy_token',
                'id_token' => 'dummy_id_token',
                'token_type' => 'Bearer',
            ], 200),
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/userinfo' => Http::response([
                'sub' => 'sub-unusa-1',
                'name' => 'Fulan bin Fulan',
                'email' => 'fulan@student.unusa.ac.id',
                // preferred_username sengaja kosong
            ], 200),
        ]);

        $response = $this->withSession([
            'keycloak_oauth_state' => 'valid_state',
            'keycloak_code_verifier' => 'dummy_verifier',
        ])->get('/auth/login/callback?state=valid_state&code=test_code');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('sso');
        $response->assertSessionDoesntHaveErrors('identity');
        $this->assertGuest();
    }

    public function test_callback_nim_tidak_ada_di_master(): void
    {
        $this->setKeycloakConfig(true);

        Http::fake([
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/token' => Http::response([
                'access_token' => 'dummy_token',
                'token_type' => 'Bearer',
            ], 200),
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/userinfo' => Http::response([
                'sub' => 'sub-unusa-999',
                'preferred_username' => '9999999999',
                'name' => 'Orang Asing',
                'email' => 'asing@student.unusa.ac.id',
            ], 200),
        ]);

        $response = $this->withSession([
            'keycloak_oauth_state' => 'valid_state',
            'keycloak_code_verifier' => 'dummy_verifier',
        ])->get('/auth/login/callback?state=valid_state&code=test_code');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['sso' => 'Akun belum terdaftar. Hubungi admin.']);
        $response->assertSessionDoesntHaveErrors('identity');
        $this->assertGuest();
    }

    public function test_callback_sukses_membuat_dan_mengaktifkan_mahasiswa_kipk(): void
    {
        $this->setKeycloakConfig(true);

        $fakultas = Fakultas::create(['kode' => 'FT', 'nama' => 'Fakultas Teknik']);
        $prodi = ProgramStudi::create(['kode' => 'SI', 'nama' => 'S1 Sistem Informasi', 'jenjang' => 'S1', 'fakultas_id' => $fakultas->id]);

        MasterMahasiswaUnusa::create([
            'nim' => '2241010001',
            'nama_lengkap' => 'Ahmad Fauzi',
            'email_kampus' => '2241010001@student.unusa.ac.id',
            'program_studi_id' => $prodi->id,
            'fakultas_id' => $fakultas->id,
            'angkatan' => 2022,
            'status_aktif' => 'AKTIF',
        ]);

        $penerima = new PenerimaKipk([
            'nim' => '2241010001',
            'nama_lengkap' => 'Ahmad Fauzi',
            'program_studi_teks' => 'S1 Sistem Informasi',
            'angkatan' => 2022,
            'status_penetapan' => 'ditetapkan',
            'sumber' => 'impor_unusa',
            'diimpor_pada' => now(),
            'status_tinjau' => PenerimaKipk::TINJAU_NORMAL,
        ]);
        $penerima->aturNomor('KIPK-2241010001-TEST');
        $penerima->save();

        Http::fake([
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/token' => Http::response([
                'access_token' => 'token_kipk',
                'token_type' => 'Bearer',
            ], 200),
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/userinfo' => Http::response([
                'sub' => 'keycloak-sub-fauzi',
                'preferred_username' => '2241010001',
                'name' => 'Ahmad Fauzi',
                'email' => '2241010001@student.unusa.ac.id',
            ], 200),
        ]);

        $response = $this->withSession([
            'keycloak_oauth_state' => 'valid_state',
            'keycloak_code_verifier' => 'dummy_verifier',
        ])->get('/auth/login/callback?state=valid_state&code=test_code');

        $response->assertRedirect(route('dashboard.index'));
        $this->assertAuthenticated();

        $user = User::where('keycloak_sub', 'keycloak-sub-fauzi')->first();
        $this->assertNotNull($user);
        $this->assertSame('2241010001', $user->nim);
        $this->assertSame(UserRole::MahasiswaKipk, $user->role);
        $this->assertTrue($user->is_unusa);
        $this->assertNotNull($user->email_verified_at);

        $penerima->refresh();
        $this->assertSame($user->id, $penerima->user_id);
    }

    public function test_callback_sukses_membuat_mahasiswa_umum_bila_bukan_penerima_kipk(): void
    {
        $this->setKeycloakConfig(true);

        $fakultas = Fakultas::create(['kode' => 'FE', 'nama' => 'Fakultas Ekonomi']);
        $prodi = ProgramStudi::create(['kode' => 'MN', 'nama' => 'S1 Manajemen', 'jenjang' => 'S1', 'fakultas_id' => $fakultas->id]);

        MasterMahasiswaUnusa::create([
            'nim' => '2241010002',
            'nama_lengkap' => 'Budi Santoso',
            'email_kampus' => '2241010002@student.unusa.ac.id',
            'program_studi_id' => $prodi->id,
            'fakultas_id' => $fakultas->id,
            'angkatan' => 2022,
            'status_aktif' => 'AKTIF',
        ]);

        Http::fake([
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/token' => Http::response([
                'access_token' => 'token_umum',
                'token_type' => 'Bearer',
            ], 200),
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/userinfo' => Http::response([
                'sub' => 'keycloak-sub-budi',
                'preferred_username' => '2241010002',
                'name' => 'Budi Santoso',
                'email' => '2241010002@student.unusa.ac.id',
            ], 200),
        ]);

        $response = $this->withSession([
            'keycloak_oauth_state' => 'valid_state',
            'keycloak_code_verifier' => 'dummy_verifier',
        ])->get('/auth/login/callback?state=valid_state&code=test_code');

        $response->assertRedirect(route('dashboard.index'));
        $this->assertAuthenticated();

        $user = User::where('keycloak_sub', 'keycloak-sub-budi')->first();
        $this->assertNotNull($user);
        $this->assertSame(UserRole::UserUmum, $user->role);
        $this->assertTrue($user->is_unusa);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_callback_menautkan_keycloak_sub_pada_akun_mahasiswa_yang_sudah_ada(): void
    {
        $this->setKeycloakConfig(true);

        $user = User::factory()->create([
            'nim' => '2241010003',
            'is_unusa' => true,
            'role' => UserRole::MahasiswaKipk,
            'keycloak_sub' => null,
        ]);

        MasterMahasiswaUnusa::create([
            'nim' => '2241010003',
            'nama_lengkap' => $user->name,
            'email_kampus' => '2241010003@student.unusa.ac.id',
            'status_aktif' => 'AKTIF',
        ]);

        Http::fake([
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/token' => Http::response([
                'access_token' => 'token_link',
                'token_type' => 'Bearer',
            ], 200),
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/userinfo' => Http::response([
                'sub' => 'keycloak-sub-taut',
                'preferred_username' => '2241010003',
                'name' => $user->name,
                'email' => $user->email,
            ], 200),
        ]);

        $response = $this->withSession([
            'keycloak_oauth_state' => 'valid_state',
            'keycloak_code_verifier' => 'dummy_verifier',
        ])->get('/auth/login/callback?state=valid_state&code=test_code');

        $response->assertRedirect(route('dashboard.index'));
        $this->assertAuthenticatedAs($user);

        $user->refresh();
        $this->assertSame('keycloak-sub-taut', $user->keycloak_sub);
    }

    public function test_callback_langsung_cocok_lewat_keycloak_sub_pada_login_berikutnya(): void
    {
        $this->setKeycloakConfig(true);

        $user = User::factory()->create([
            'nim' => '2241010004',
            'is_unusa' => true,
            'role' => UserRole::MahasiswaKipk,
            'keycloak_sub' => 'keycloak-sub-langsung',
        ]);

        Http::fake([
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/token' => Http::response([
                'access_token' => 'token_existing',
                'token_type' => 'Bearer',
            ], 200),
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/userinfo' => Http::response([
                'sub' => 'keycloak-sub-langsung',
                'name' => $user->name,
                'email' => $user->email,
            ], 200),
        ]);

        $response = $this->withSession([
            'keycloak_oauth_state' => 'valid_state',
            'keycloak_code_verifier' => 'dummy_verifier',
        ])->get('/auth/login/callback?state=valid_state&code=test_code');

        $response->assertRedirect(route('dashboard.index'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_akun_diblokir_ditolak_sso(): void
    {
        $this->setKeycloakConfig(true);

        $user = User::factory()->create([
            'nim' => '2241010005',
            'is_unusa' => true,
            'keycloak_sub' => 'keycloak-sub-blocked',
            'is_blocked' => true,
        ]);

        Http::fake([
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/token' => Http::response([
                'access_token' => 'token_blocked',
                'token_type' => 'Bearer',
            ], 200),
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/userinfo' => Http::response([
                'sub' => 'keycloak-sub-blocked',
                'name' => $user->name,
                'email' => $user->email,
            ], 200),
        ]);

        $response = $this->withSession([
            'keycloak_oauth_state' => 'valid_state',
            'keycloak_code_verifier' => 'dummy_verifier',
        ])->get('/auth/login/callback?state=valid_state&code=test_code');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('sso');
        $response->assertSessionDoesntHaveErrors('identity');
        $this->assertGuest();
    }

    public function test_role_tidak_bisa_naik_lewat_sso(): void
    {
        $this->setKeycloakConfig(true);

        MasterMahasiswaUnusa::create([
            'nim' => '2241010006',
            'nama_lengkap' => 'Percobaan Hacker',
            'email_kampus' => '2241010006@student.unusa.ac.id',
            'status_aktif' => 'AKTIF',
        ]);

        // Klaim menyertakan role super_admin dalam payload
        Http::fake([
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/token' => Http::response([
                'access_token' => 'token_palsu',
                'token_type' => 'Bearer',
            ], 200),
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/userinfo' => Http::response([
                'sub' => 'keycloak-sub-hacker',
                'preferred_username' => '2241010006',
                'name' => 'Percobaan Hacker',
                'email' => '2241010006@student.unusa.ac.id',
                'roles' => ['super_admin'],
                'realm_access' => ['roles' => ['super_admin']],
            ], 200),
        ]);

        $response = $this->withSession([
            'keycloak_oauth_state' => 'valid_state',
            'keycloak_code_verifier' => 'dummy_verifier',
        ])->get('/auth/login/callback?state=valid_state&code=test_code');

        $response->assertRedirect(route('dashboard.index'));
        $this->assertAuthenticated();

        $user = User::where('keycloak_sub', 'keycloak-sub-hacker')->first();
        $this->assertNotNull($user);
        // Pastikan TIDAK PERNAH jadi super_admin
        $this->assertNotSame(UserRole::SuperAdmin, $user->role);
        $this->assertSame(UserRole::UserUmum, $user->role);
    }

    public function test_staf_tidak_bisa_tertaut_lewat_sso_pertama_kali(): void
    {
        $this->setKeycloakConfig(true);

        $admin = User::factory()->create([
            'nim' => '2241010099',
            'is_unusa' => true,
            'role' => UserRole::SuperAdmin,
            'keycloak_sub' => null,
        ]);

        MasterMahasiswaUnusa::create([
            'nim' => '2241010099',
            'nama_lengkap' => $admin->name,
            'email_kampus' => '2241010099@student.unusa.ac.id',
            'status_aktif' => 'AKTIF',
        ]);

        Http::fake([
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/token' => Http::response([
                'access_token' => 'token_staf',
                'token_type' => 'Bearer',
            ], 200),
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/userinfo' => Http::response([
                'sub' => 'sub-staf-keycloak',
                'preferred_username' => '2241010099',
                'name' => $admin->name,
                'email' => $admin->email,
            ], 200),
        ]);

        $response = $this->withSession([
            'keycloak_oauth_state' => 'valid_state',
            'keycloak_code_verifier' => 'dummy_verifier',
        ])->get('/auth/login/callback?state=valid_state&code=test_code');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('sso');
        $response->assertSessionDoesntHaveErrors('identity');
        $this->assertGuest();

        $admin->refresh();
        $this->assertNull($admin->keycloak_sub);
    }

    public function test_middleware_security_headers(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    public function test_middleware_security_headers_production_https(): void
    {
        Config::set('app.env', 'production');

        $response = $this->withServerVariables([
            'HTTPS' => 'on',
            'SERVER_PORT' => 443,
        ])->get('https://localhost/');

        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $response->assertHeader('Content-Security-Policy-Report-Only');
    }

    public function test_perintah_preflight_berjalan_dan_melaporkan(): void
    {
        $this->artisan('semadiksi:preflight')
            ->expectsOutputToContain('Memeriksa kesiapan konfigurasi SEMADIKSI')
            ->assertExitCode(0);
    }

    public function test_halaman_error_berbahasa_indonesia(): void
    {
        $views = [
            'errors.403' => 'Akses Dibatasi',
            'errors.404' => 'Halaman Tidak Ditemukan',
            'errors.419' => 'Sesi Telah Berakhir',
            'errors.429' => 'Terlalu Banyak Permintaan',
            'errors.500' => 'Kendala pada Sistem',
        ];

        foreach ($views as $view => $expectedText) {
            $html = view($view)->render();
            $this->assertStringContainsString($expectedText, $html);
            $this->assertStringNotContainsString('Stack trace', $html);
        }
    }

    public function test_url_otorisasi_memuat_prompt_login_saat_flag_aktif_dan_tidak_memuat_saat_nonaktif(): void
    {
        $this->setKeycloakConfig(true, forceLoginPrompt: true);
        $resAktif = $this->get('/auth/login');
        $resAktif->assertRedirect();
        $this->assertStringContainsString('prompt=login', (string) $resAktif->headers->get('Location'));

        $this->setKeycloakConfig(true, forceLoginPrompt: false);
        $resNonaktif = $this->get('/auth/login');
        $resNonaktif->assertRedirect();
        $this->assertStringNotContainsString('prompt=login', (string) $resNonaktif->headers->get('Location'));
    }

    public function test_logging_alasan_kegagalan_sso_state_tidak_cocok_tanpa_data_pribadi(): void
    {
        Log::spy();
        $this->setKeycloakConfig(true);

        $this->withSession(['keycloak_oauth_state' => 'state_benar'])
            ->get('/auth/login/callback?state=state_palsu&code=auth_code_rahasia_123');

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message) {
                return str_contains($message, 'state tidak cocok')
                    && ! str_contains($message, 'auth_code_rahasia_123')
                    && ! str_contains($message, 'state_palsu');
            });
    }

    public function test_logging_alasan_kegagalan_sso_kode_penukaran_gagal_tanpa_data_pribadi(): void
    {
        Log::spy();
        $this->setKeycloakConfig(true);

        // Kasus A: code kosong
        $this->withSession([
            'keycloak_oauth_state' => 'valid_state',
            'keycloak_code_verifier' => 'dummy_verifier_secret',
        ])->get('/auth/login/callback?state=valid_state');

        // Kasus B: token exchange HTTP gagal
        Http::fake([
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/token' => Http::response([], 400),
        ]);

        $this->withSession([
            'keycloak_oauth_state' => 'valid_state_2',
            'keycloak_code_verifier' => 'dummy_verifier_secret_2',
        ])->get('/auth/login/callback?state=valid_state_2&code=code_rahasia_999');

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message) {
                return str_contains($message, 'kode penukaran gagal')
                    && ! str_contains($message, 'dummy_verifier_secret')
                    && ! str_contains($message, 'code_rahasia_999');
            });
    }

    public function test_logging_alasan_kegagalan_sso_klaim_wajib_hilang_tanpa_data_pribadi(): void
    {
        Log::spy();
        $this->setKeycloakConfig(true);

        // Sub hilang
        Http::fake([
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/token' => Http::response([
                'access_token' => 'secret_access_token_1',
                'token_type' => 'Bearer',
            ], 200),
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/userinfo' => Http::response([
                'name' => 'Tanpa Sub',
            ], 200),
        ]);

        $this->withSession([
            'keycloak_oauth_state' => 'valid_state',
            'keycloak_code_verifier' => 'dummy_verifier',
        ])->get('/auth/login/callback?state=valid_state&code=test_code');

        // NIM / preferred_username hilang
        Http::fake([
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/token' => Http::response([
                'access_token' => 'secret_access_token_2',
                'token_type' => 'Bearer',
            ], 200),
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/userinfo' => Http::response([
                'sub' => 'sub-unusa-valid',
                'name' => 'Fulan',
                'email' => 'fulan@student.unusa.ac.id',
            ], 200),
        ]);

        $this->withSession([
            'keycloak_oauth_state' => 'valid_state_2',
            'keycloak_code_verifier' => 'dummy_verifier',
        ])->get('/auth/login/callback?state=valid_state_2&code=test_code');

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message) {
                return str_contains($message, 'klaim wajib hilang')
                    && ! str_contains($message, 'secret_access_token')
                    && ! str_contains($message, 'fulan@student.unusa.ac.id');
            });
    }

    public function test_logging_alasan_kegagalan_sso_nim_tidak_ada_di_data_master_tanpa_data_pribadi(): void
    {
        Log::spy();
        $this->setKeycloakConfig(true);

        $nimRahasia = '9998887776';
        $emailRahasia = 'rahasia999@student.unusa.ac.id';

        Http::fake([
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/token' => Http::response([
                'access_token' => 'secret_token_master',
                'token_type' => 'Bearer',
            ], 200),
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/userinfo' => Http::response([
                'sub' => 'sub-unusa-asing',
                'preferred_username' => $nimRahasia,
                'email' => $emailRahasia,
            ], 200),
        ]);

        $response = $this->withSession([
            'keycloak_oauth_state' => 'valid_state',
            'keycloak_code_verifier' => 'dummy_verifier',
        ])->get('/auth/login/callback?state=valid_state&code=test_code');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['sso' => 'Akun belum terdaftar. Hubungi admin.']);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message) use ($nimRahasia, $emailRahasia) {
                return str_contains($message, 'NIM tidak ada di data master')
                    && ! str_contains($message, $nimRahasia)
                    && ! str_contains($message, $emailRahasia)
                    && ! str_contains($message, 'secret_token_master');
            });
    }

    public function test_logging_alasan_kegagalan_sso_pengguna_belum_teraktivasi_tanpa_data_pribadi(): void
    {
        Log::spy();
        $this->setKeycloakConfig(true);

        $nim = '2241010077';
        $email = '2241010077@student.unusa.ac.id';

        // Akun ada di master mahasiswa
        MasterMahasiswaUnusa::create([
            'nim' => $nim,
            'nama_lengkap' => 'Mahasiswa Belum Aktivasi',
            'email_kampus' => $email,
            'status_aktif' => 'AKTIF',
        ]);

        // Akun ada di tabel users tetapi belum teraktivasi (email_verified_at null)
        User::factory()->create([
            'nim' => $nim,
            'email' => $email,
            'is_unusa' => true,
            'email_verified_at' => null,
            'keycloak_sub' => null,
        ]);

        Http::fake([
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/token' => Http::response([
                'access_token' => 'secret_token_unverified',
                'token_type' => 'Bearer',
            ], 200),
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/userinfo' => Http::response([
                'sub' => 'sub-unverified-1',
                'preferred_username' => $nim,
                'email' => $email,
            ], 200),
        ]);

        $response = $this->withSession([
            'keycloak_oauth_state' => 'valid_state',
            'keycloak_code_verifier' => 'dummy_verifier',
        ])->get('/auth/login/callback?state=valid_state&code=test_code');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('sso');

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message) use ($nim, $email) {
                return str_contains($message, 'pengguna belum teraktivasi')
                    && ! str_contains($message, $nim)
                    && ! str_contains($message, $email)
                    && ! str_contains($message, 'secret_token_unverified');
            });
    }

    public function test_logging_alasan_kegagalan_sso_akun_diblokir_tanpa_data_pribadi(): void
    {
        Log::spy();
        $this->setKeycloakConfig(true);

        $nim = '2241010088';
        $email = 'terblokir@student.unusa.ac.id';

        User::factory()->create([
            'nim' => $nim,
            'email' => $email,
            'is_unusa' => true,
            'keycloak_sub' => 'keycloak-sub-terblokir',
            'is_blocked' => true,
        ]);

        Http::fake([
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/token' => Http::response([
                'access_token' => 'secret_token_blocked',
                'token_type' => 'Bearer',
            ], 200),
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/userinfo' => Http::response([
                'sub' => 'keycloak-sub-terblokir',
                'email' => $email,
            ], 200),
        ]);

        $response = $this->withSession([
            'keycloak_oauth_state' => 'valid_state',
            'keycloak_code_verifier' => 'dummy_verifier',
        ])->get('/auth/login/callback?state=valid_state&code=test_code');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('sso');

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message) use ($nim, $email) {
                return str_contains($message, 'akun diblokir')
                    && ! str_contains($message, $nim)
                    && ! str_contains($message, $email)
                    && ! str_contains($message, 'secret_token_blocked');
            });
    }

    public function test_logging_alasan_kegagalan_sso_role_tidak_diizinkan_sso_tanpa_data_pribadi(): void
    {
        Log::spy();
        $this->setKeycloakConfig(true);

        $nim = '2241010099';
        $email = 'superadmin.sso@semadiksi.ac.id';

        $admin = User::factory()->create([
            'nim' => $nim,
            'email' => $email,
            'is_unusa' => true,
            'role' => UserRole::SuperAdmin,
            'keycloak_sub' => null,
        ]);

        MasterMahasiswaUnusa::create([
            'nim' => $nim,
            'nama_lengkap' => $admin->name,
            'email_kampus' => $email,
            'status_aktif' => 'AKTIF',
        ]);

        Http::fake([
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/token' => Http::response([
                'access_token' => 'secret_token_staf_role',
                'token_type' => 'Bearer',
            ], 200),
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/userinfo' => Http::response([
                'sub' => 'sub-staf-keycloak-role',
                'preferred_username' => $nim,
                'email' => $email,
            ], 200),
        ]);

        $response = $this->withSession([
            'keycloak_oauth_state' => 'valid_state',
            'keycloak_code_verifier' => 'dummy_verifier',
        ])->get('/auth/login/callback?state=valid_state&code=test_code');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('sso');

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message) use ($nim, $email) {
                return str_contains($message, 'role tidak diizinkan SSO')
                    && ! str_contains($message, $nim)
                    && ! str_contains($message, $email)
                    && ! str_contains($message, 'secret_token_staf_role');
            });
    }
}
