<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class HalamanLoginDanLogoutTest extends TestCase
{
    use RefreshDatabase;

    private function setKeycloakConfig(bool $enabled = true, bool $logoutEnabled = false): void
    {
        Config::set('services.keycloak.enabled', $enabled);
        Config::set('services.keycloak.base_url', 'https://sso.unusa.ac.id');
        Config::set('services.keycloak.realms', 'UNUSA');
        Config::set('services.keycloak.client_id', 'simadiksi');
        Config::set('services.keycloak.client_secret', 'secret-keycloak-test');
        Config::set('services.keycloak.redirect', 'http://localhost:8000/auth/login/callback');
        Config::set('services.keycloak.logout_enabled', $logoutEnabled);
        Config::set('services.keycloak.post_logout_redirect_uri', 'http://localhost:8000/masuk');
    }

    public function test_galat_sso_tampil_sebagai_banner_di_atas_formulir_dan_bukan_di_bawah_isian_email(): void
    {
        $this->setKeycloakConfig(true);

        Http::fake([
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/token' => Http::response([
                'access_token' => 'dummy_token',
                'token_type' => 'Bearer',
            ], 200),
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/userinfo' => Http::response([
                'sub' => 'sub-unusa-tidak-ada',
                'preferred_username' => '2241999999',
                'name' => 'Peserta Tak Terdaftar',
                'email' => 'tak.terdaftar@student.unusa.ac.id',
            ], 200),
        ]);

        // 1. Uji redirect dan session error
        $response = $this->withSession([
            'keycloak_oauth_state' => 'valid_state',
            'keycloak_code_verifier' => 'dummy_verifier',
        ])->get('/auth/login/callback?state=valid_state&code=test_code');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['sso' => 'Akun belum terdaftar. Hubungi admin.']);
        $response->assertSessionDoesntHaveErrors('identity');
        $this->assertNull(session('_old_input'));

        // 2. Uji langsung dengan followingRedirects untuk memeriksa render halaman
        $followed = $this->followingRedirects()
            ->withSession([
                'keycloak_oauth_state' => 'valid_state',
                'keycloak_code_verifier' => 'dummy_verifier',
            ])->get('/auth/login/callback?state=valid_state&code=test_code');

        $followed->assertOk();
        $followed->assertSee('id="sso-error-banner"', false);
        $followed->assertSee('Akun belum terdaftar. Hubungi admin.');
        $followed->assertDontSee('border-rose-500');
    }

    public function test_menekan_tombol_sso_membersihkan_galat_dan_isian_formulir_kata_sandi_sebelumnya(): void
    {
        $this->setKeycloakConfig(true);

        // Simulasi sesi yang sebelumnya memiliki galat dan isian lama dari form kata sandi
        $response = $this->withSession([
            '_old_input' => ['identity' => 'email.lama@student.unusa.ac.id'],
            'errors' => new ViewErrorBag,
        ])->get(route('auth.login'));

        $response->assertRedirect();
        $response->assertSessionMissing('_old_input');
    }

    public function test_galat_formulir_kata_sandi_tetap_di_bawah_isian_dan_pesan_netral(): void
    {
        $user = User::factory()->create([
            'email' => 'mahasiswa@student.unusa.ac.id',
            'password' => bcrypt('PasswordBenar123'),
        ]);

        // Coba kata sandi salah dengan header referer dari halaman /masuk
        $response = $this->from(route('login'))
            ->followingRedirects()
            ->post(route('login.post'), [
                'identity' => 'mahasiswa@student.unusa.ac.id',
                'password' => 'PasswordSalah',
            ]);

        $response->assertOk();
        $response->assertDontSee('id="sso-error-banner"', false);
        $response->assertSee('Email/NIM atau kata sandi yang Anda masukkan tidak sesuai.');
        $response->assertSee('border-rose-500');
        $response->assertSee('value="mahasiswa@student.unusa.ac.id"', false);

        // Coba akun yang tidak ada sama sekali: pesan tetap netral persis sama
        $response2 = $this->from(route('login'))->post(route('login.post'), [
            'identity' => 'tidakada@student.unusa.ac.id',
            'password' => 'PasswordSembarang',
        ]);
        $response2->assertSessionHasErrors(['identity' => 'Email/NIM atau kata sandi yang Anda masukkan tidak sesuai.']);
    }

    public function test_jalur_login_staf_dan_tautan_masuk_sebagai_staf(): void
    {
        $admin = User::factory()->role(UserRole::SuperAdmin)->create([
            'email' => 'superadmin@semadiksi.ac.id',
            'password' => bcrypt('AdminRahasia123'),
        ]);

        // 1. Tautan "Masuk sebagai staf" ada di halaman /masuk
        $loginPage = $this->get(route('login'));
        $loginPage->assertOk();
        $loginPage->assertSee(route('admin.login'));
        $loginPage->assertSee('Masuk sebagai staf');

        // 2. Jika email staf diketik di /masuk dengan kata sandi salah: respons tetap netral tanpa membocorkan role/keberadaan akun
        $responseSalah = $this->from(route('login'))->post(route('login.post'), [
            'identity' => 'superadmin@semadiksi.ac.id',
            'password' => 'SalahSandi',
        ]);
        $responseSalah->assertSessionHasErrors(['identity' => 'Email/NIM atau kata sandi yang Anda masukkan tidak sesuai.']);
        $this->assertGuest();

        // 3. Jika email staf diketik di /masuk dengan kata sandi benar: staf tetap berhasil masuk ke halaman admin
        $responseBenar = $this->post(route('login.post'), [
            'identity' => 'superadmin@semadiksi.ac.id',
            'password' => 'AdminRahasia123',
        ]);
        $responseBenar->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
        auth()->logout();

        // 4. Staf login di halaman /admin/masuk
        $adminLogin = $this->post(route('admin.login.post'), [
            'email' => 'superadmin@semadiksi.ac.id',
            'password' => 'AdminRahasia123',
        ]);
        $adminLogin->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_logout_semua_role_mengarah_ke_halaman_login_yang_benar(): void
    {
        // 1. Super Admin -> admin.login
        $admin = User::factory()->role(UserRole::SuperAdmin)->create();
        $this->actingAs($admin)
            ->post(route('logout'))
            ->assertRedirect(route('admin.login'));
        $this->assertGuest();

        // 2. Verifikator Berkas -> admin.login
        $verifikator = User::factory()->role(UserRole::VerifikatorBerkas)->create();
        $this->actingAs($verifikator)
            ->post(route('logout'))
            ->assertRedirect(route('admin.login'));
        $this->assertGuest();

        // 3. Penilai Karya (Juri) -> admin.login
        $juri = User::factory()->role(UserRole::PenilaiKarya)->create();
        $this->actingAs($juri)
            ->post(route('logout'))
            ->assertRedirect(route('admin.login'));
        $this->assertGuest();

        // 4. Mahasiswa KIP-K -> login (/masuk)
        $mhs = User::factory()->mahasiswaKipk()->create();
        $this->actingAs($mhs)
            ->post(route('logout'))
            ->assertRedirect(route('login'));
        $this->assertGuest();

        // 5. User Umum -> login (/masuk)
        $umum = User::factory()->userUmum()->create();
        $this->actingAs($umum)
            ->post(route('logout'))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_logout_dengan_request_inertia_mengembalikan_location_409_mencegah_halaman_mengecil(): void
    {
        $admin = User::factory()->role(UserRole::SuperAdmin)->create();

        // Request dari Inertia (AdminLayout.jsx memanggil router.post('/keluar') dengan header X-Inertia)
        $response = $this->actingAs($admin)
            ->withHeaders(['X-Inertia' => 'true'])
            ->post(route('logout'));

        // Harus mengembalikan HTTP 409 dengan header X-Inertia-Location, BUKAN 302 yang tertangkap modal iframe
        $response->assertStatus(409);
        $response->assertHeader('X-Inertia-Location', route('admin.login'));
        $this->assertGuest();

        // Begitu juga untuk mahasiswa di ParticipantLayout.jsx
        $mhs = User::factory()->mahasiswaKipk()->create();
        $responseMhs = $this->actingAs($mhs)
            ->withHeaders(['X-Inertia' => 'true'])
            ->post(route('logout'));

        $responseMhs->assertStatus(409);
        $responseMhs->assertHeader('X-Inertia-Location', route('login'));
        $this->assertGuest();
    }

    public function test_logout_sso_flag_nonaktif_tidak_mengubah_perilaku(): void
    {
        $this->setKeycloakConfig(true, logoutEnabled: false);

        $user = User::factory()->userUmum()->create([
            'keycloak_sub' => 'sub-sso-user-1',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['auth_via_sso' => true, 'keycloak_id_token' => 'dummy_token'])
            ->post(route('logout'));

        // Tetap redirect ke login lokal tanpa memanggil Keycloak end_session_endpoint
        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_logout_sso_flag_aktif_melakukan_rp_initiated_logout_dan_membersihkan_token(): void
    {
        $this->setKeycloakConfig(true, logoutEnabled: true);

        $user = User::factory()->userUmum()->create([
            'keycloak_sub' => 'sub-sso-user-2',
        ]);

        $response = $this->actingAs($user)
            ->withSession([
                'auth_via_sso' => true,
                'keycloak_id_token' => 'id-token-secret-123',
            ])
            ->post(route('logout'));

        $this->assertGuest();

        // Response mengarah ke Keycloak end_session_endpoint dengan id_token_hint dan post_logout_redirect_uri
        $target = $response->headers->get('Location');
        $this->assertStringContainsString('https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/logout', $target);
        $this->assertStringContainsString('id_token_hint=id-token-secret-123', $target);
        $this->assertStringContainsString('client_id=simadiksi', $target);
        $this->assertStringContainsString('post_logout_redirect_uri=', $target);

        // Token tidak boleh tersisa di session
        $this->assertNull(session('keycloak_id_token'));
    }
}
