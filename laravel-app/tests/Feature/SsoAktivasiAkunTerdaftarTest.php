<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Fakultas;
use App\Models\MasterMahasiswaUnusa;
use App\Models\PenerimaKipk;
use App\Models\ProgramStudi;
use App\Models\User;
use App\Support\Masking;
use App\Support\NormalisasiNim;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SsoAktivasiAkunTerdaftarTest extends TestCase
{
    use RefreshDatabase;

    private function setKeycloakConfig(bool $enabled = true): void
    {
        Config::set('services.keycloak.enabled', $enabled);
        Config::set('services.keycloak.base_url', 'https://sso.unusa.ac.id');
        Config::set('services.keycloak.realms', 'UNUSA');
        Config::set('services.keycloak.client_id', 'simadiksi');
        Config::set('services.keycloak.client_secret', 'secret-keycloak-test');
        Config::set('services.keycloak.redirect', 'http://localhost:8000/auth/login/callback');
        Config::set('services.keycloak.logout_enabled', false);
        Config::set('services.keycloak.post_logout_redirect_uri', 'http://localhost:8000/masuk');
        Config::set('services.keycloak.force_login_prompt', true);
        Config::set('unusa.validasi_email_dns', false);
    }

    public function test_kasus_nyata_nim_4330024012_dibuat_admin_tertunda_teraktivasi_otomatis_saat_login_sso(): void
    {
        $this->setKeycloakConfig(true);

        $fakultas = Fakultas::create(['kode' => 'FKes', 'nama' => 'Fakultas Kesehatan']);
        $prodi = ProgramStudi::create([
            'kode' => 'S1-GZ',
            'nama' => 'S1 Gizi',
            'jenjang' => 'S1',
            'fakultas_id' => $fakultas->id,
        ]);

        $nim = '4330024012';
        $email = '4330024012@student.unusa.ac.id';

        // 1. Data di master mahasiswa UNUSA
        MasterMahasiswaUnusa::create([
            'nim' => $nim,
            'nama_lengkap' => 'Mahasiswa Terdaftar',
            'email_kampus' => $email,
            'program_studi_id' => $prodi->id,
            'fakultas_id' => $fakultas->id,
            'angkatan' => 2024,
            'status_aktif' => 'AKTIF',
        ]);

        // 2. Data di penerima KIP-K
        $penerima = new PenerimaKipk([
            'nim' => $nim,
            'nama_lengkap' => 'Mahasiswa Terdaftar',
            'program_studi_teks' => 'S1 Gizi',
            'angkatan' => 2024,
            'status_penetapan' => 'ditetapkan',
            'sumber' => 'impor_unusa',
            'diimpor_pada' => now(),
            'status_tinjau' => PenerimaKipk::TINJAU_NORMAL,
        ]);
        $penerima->aturNomor('KIPK-4330024012-TEST');
        $penerima->save();

        // 3. Akun dibuat lewat menu Data Pengguna (role MahasiswaKipk, belum teraktivasi, password awal ada)
        $passwordAwal = 'PasswordAwal123!';
        $user = User::create([
            'name' => 'Mahasiswa Terdaftar',
            'email' => $email,
            'nim' => $nim,
            'role' => UserRole::MahasiswaKipk,
            'is_unusa' => true,
            'email_verified_at' => null,
            'verification_status' => 'Verified',
            'is_blocked' => false,
            'password' => Hash::make($passwordAwal),
            'keycloak_sub' => null,
        ]);

        Http::fake([
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/token' => Http::response([
                'access_token' => 'access_token_4330024012',
                'token_type' => 'Bearer',
            ], 200),
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/userinfo' => Http::response([
                'sub' => 'sub-unusa-4330024012',
                'preferred_username' => $nim,
                'name' => 'Mahasiswa Terdaftar',
                'email' => $email,
            ], 200),
        ]);

        $response = $this->withSession([
            'keycloak_oauth_state' => 'valid_state_4330',
            'keycloak_code_verifier' => 'dummy_verifier_4330',
        ])->get('/auth/login/callback?state=valid_state_4330&code=test_code');

        $response->assertRedirect(route('dashboard.index'));
        $this->assertAuthenticatedAs($user);

        $user->refresh();
        $this->assertNotNull($user->email_verified_at, 'email_verified_at harus terisi setelah login SSO');
        $this->assertSame('sub-unusa-4330024012', $user->keycloak_sub);
        $this->assertSame('Verified', $user->verification_status);
        $this->assertFalse(Hash::check($passwordAwal, $user->password), 'Password lama harus diganti hash acak');

        $penerima->refresh();
        $this->assertSame($user->id, $penerima->user_id, 'Penerima KIP-K harus terhubung dengan user');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'sso_activation',
        ]);
    }

    public function test_akun_mahasiswa_kipk_tertunda_yang_sudah_memiliki_sub_teraktivasi_otomatis(): void
    {
        $this->setKeycloakConfig(true);

        $nim = '4330024099';
        $email = '4330024099@student.unusa.ac.id';
        $sub = 'sub-unusa-4330024099';

        MasterMahasiswaUnusa::create([
            'nim' => $nim,
            'nama_lengkap' => 'Mahasiswa Sub Tertunda',
            'email_kampus' => $email,
            'status_aktif' => 'AKTIF',
        ]);

        // Akun sudah memiliki keycloak_sub tetapi email_verified_at bernilai null
        $user = User::create([
            'name' => 'Mahasiswa Sub Tertunda',
            'email' => $email,
            'nim' => $nim,
            'role' => UserRole::MahasiswaKipk,
            'is_unusa' => true,
            'email_verified_at' => null,
            'verification_status' => 'Verified',
            'is_blocked' => false,
            'password' => Hash::make('passwordAwal'),
            'keycloak_sub' => $sub,
        ]);

        Http::fake([
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/token' => Http::response([
                'access_token' => 'access_token_99',
                'token_type' => 'Bearer',
            ], 200),
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/userinfo' => Http::response([
                'sub' => $sub,
                'preferred_username' => $nim,
                'name' => 'Mahasiswa Sub Tertunda',
                'email' => $email,
            ], 200),
        ]);

        $response = $this->withSession([
            'keycloak_oauth_state' => 'valid_state_sub',
            'keycloak_code_verifier' => 'dummy_verifier_sub',
        ])->get('/auth/login/callback?state=valid_state_sub&code=test_code');

        $response->assertRedirect(route('dashboard.index'));
        $this->assertAuthenticatedAs($user);

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_akun_user_umum_tertunda_ditolak_sso_dengan_pesan_ramah_dan_log_kode_referensi(): void
    {
        Log::spy();
        $this->setKeycloakConfig(true);

        $nim = '2241010080';
        $email = '2241010080@student.unusa.ac.id';

        MasterMahasiswaUnusa::create([
            'nim' => $nim,
            'nama_lengkap' => 'Mahasiswa Non KIP',
            'email_kampus' => $email,
            'status_aktif' => 'AKTIF',
        ]);

        // Akun di users sebagai user_umum tertunda (email_verified_at null)
        $user = User::factory()->create([
            'nim' => $nim,
            'email' => $email,
            'role' => UserRole::UserUmum,
            'is_unusa' => true,
            'email_verified_at' => null,
            'keycloak_sub' => null,
        ]);

        Http::fake([
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/token' => Http::response([
                'access_token' => 'token_80',
                'token_type' => 'Bearer',
            ], 200),
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/userinfo' => Http::response([
                'sub' => 'sub-80',
                'preferred_username' => $nim,
                'email' => $email,
            ], 200),
        ]);

        $response = $this->withSession([
            'keycloak_oauth_state' => 'valid_state_80',
            'keycloak_code_verifier' => 'dummy_verifier_80',
        ])->get('/auth/login/callback?state=valid_state_80&code=test_code');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['sso' => 'Akun belum teraktivasi. Hubungi admin SEMADIKSI.']);
        $this->assertGuest();

        $user->refresh();
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->keycloak_sub);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message) use ($nim, $email) {
                return str_contains($message, 'pengguna belum teraktivasi')
                    && str_contains($message, 'SSO-')
                    && str_contains($message, Masking::nim($nim))
                    && ! str_contains($message, $nim)
                    && ! str_contains($message, $email);
            });
    }

    public function test_peran_akun_user_umum_tidak_pernah_berubah_oleh_sso_meskipun_nim_ada_di_penerima_kipk(): void
    {
        $this->setKeycloakConfig(true);

        $nim = '2241010082';
        $email = '2241010082@student.unusa.ac.id';
        $sub = 'sub-unusa-2241010082';

        MasterMahasiswaUnusa::create([
            'nim' => $nim,
            'nama_lengkap' => 'Mahasiswa Umum Tapi KIP',
            'email_kampus' => $email,
            'status_aktif' => 'AKTIF',
        ]);

        $penerima = new PenerimaKipk([
            'nim' => $nim,
            'nama_lengkap' => 'Mahasiswa Umum Tapi KIP',
            'program_studi_teks' => 'S1 Sistem Informasi',
            'angkatan' => 2022,
            'status_penetapan' => 'ditetapkan',
            'sumber' => 'impor_unusa',
            'diimpor_pada' => now(),
            'status_tinjau' => PenerimaKipk::TINJAU_NORMAL,
        ]);
        $penerima->aturNomor('KIPK-2241010082-TEST');
        $penerima->save();

        // Akun sudah terverifikasi dengan role user_umum, memiliki password dan 2FA
        $passwordAwal = 'PasswordTetapAman123!';
        $secret2Fa = 'JBSWY3DPEHPK3PXP';
        $user = User::factory()->create([
            'nim' => $nim,
            'email' => $email,
            'role' => UserRole::UserUmum,
            'is_unusa' => true,
            'email_verified_at' => now(),
            'password' => Hash::make($passwordAwal),
            'two_factor_secret' => $secret2Fa,
            'two_factor_confirmed_at' => now(),
            'keycloak_sub' => null,
        ]);

        Http::fake([
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/token' => Http::response([
                'access_token' => 'token_82',
                'token_type' => 'Bearer',
            ], 200),
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/userinfo' => Http::response([
                'sub' => $sub,
                'preferred_username' => $nim,
                'email' => $email,
            ], 200),
        ]);

        $response = $this->withSession([
            'keycloak_oauth_state' => 'valid_state_82',
            'keycloak_code_verifier' => 'dummy_verifier_82',
        ])->get('/auth/login/callback?state=valid_state_82&code=test_code');

        $response->assertRedirect(route('dashboard.index'));
        $this->assertAuthenticatedAs($user);

        $user->refresh();
        // Aturan baku (a): peran akun TIDAK pernah berubah oleh SSO
        $this->assertSame(UserRole::UserUmum, $user->role);
        // Aturan baku (c): kata sandi TIDAK ditimpa dan 2FA yang sudah aktif TIDAK dinonaktifkan
        $this->assertTrue(Hash::check($passwordAwal, $user->password));
        $this->assertSame($secret2Fa, $user->two_factor_secret);
        $this->assertNotNull($user->two_factor_confirmed_at);
        $this->assertSame($sub, $user->keycloak_sub);
    }

    public function test_staf_ditolak_sso_dan_data_akun_tidak_berubah_sedikitpun(): void
    {
        $this->setKeycloakConfig(true);

        $stafRoles = [
            UserRole::SuperAdmin,
            UserRole::VerifikatorBerkas,
            UserRole::PenilaiKarya,
        ];

        foreach ($stafRoles as $i => $role) {
            $nim = '224101009'.($i + 1);
            $email = "staf{$i}@unusa.ac.id";
            $sub = "sub-staf-{$i}";
            $passwordAsli = "PasswordStaf#{$i}!";
            $secret2Fa = "SECRETSTAF2FA{$i}";

            MasterMahasiswaUnusa::create([
                'nim' => $nim,
                'nama_lengkap' => "Staf {$role->value}",
                'email_kampus' => $email,
                'status_aktif' => 'AKTIF',
            ]);

            $staf = User::factory()->create([
                'nim' => $nim,
                'email' => $email,
                'role' => $role,
                'is_unusa' => true,
                'email_verified_at' => null,
                'password' => Hash::make($passwordAsli),
                'two_factor_secret' => $secret2Fa,
                'two_factor_confirmed_at' => now(),
                'keycloak_sub' => null,
            ]);

            Http::fake([
                'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/token' => Http::response([
                    'access_token' => "token_staf_{$i}",
                    'token_type' => 'Bearer',
                ], 200),
                'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/userinfo' => Http::response([
                    'sub' => $sub,
                    'preferred_username' => $nim,
                    'email' => $email,
                ], 200),
            ]);

            $response = $this->withSession([
                'keycloak_oauth_state' => "state_staf_{$i}",
                'keycloak_code_verifier' => "verifier_staf_{$i}",
            ])->get("/auth/login/callback?state=state_staf_{$i}&code=code_staf_{$i}");

            $response->assertRedirect(route('login'));
            $response->assertSessionHasErrors(['sso' => 'Akun staf masuk lewat halaman login kata sandi']);
            $this->assertGuest();

            $staf->refresh();
            // Aturan baku (b): staf ditolak dan tidak diubah sedikit pun
            $this->assertSame($role, $staf->role);
            $this->assertNull($staf->keycloak_sub);
            $this->assertNull($staf->email_verified_at);
            $this->assertTrue(Hash::check($passwordAsli, $staf->password));
            $this->assertSame($secret2Fa, $staf->two_factor_secret);
            $this->assertNotNull($staf->two_factor_confirmed_at);
        }
    }

    public function test_staf_ditolak_sso_dengan_pesan_ramah_dan_log_kode_referensi(): void
    {
        Log::spy();
        $this->setKeycloakConfig(true);

        $nim = '2241010081';
        $email = 'admin81@unusa.ac.id';

        MasterMahasiswaUnusa::create([
            'nim' => $nim,
            'nama_lengkap' => 'Staf Super Admin',
            'email_kampus' => $email,
            'status_aktif' => 'AKTIF',
        ]);

        $user = User::factory()->create([
            'nim' => $nim,
            'email' => $email,
            'role' => UserRole::SuperAdmin,
            'is_unusa' => true,
            'email_verified_at' => now(),
            'keycloak_sub' => null,
        ]);

        Http::fake([
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/token' => Http::response([
                'access_token' => 'token_81',
                'token_type' => 'Bearer',
            ], 200),
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/userinfo' => Http::response([
                'sub' => 'sub-81',
                'preferred_username' => $nim,
                'email' => $email,
            ], 200),
        ]);

        $response = $this->withSession([
            'keycloak_oauth_state' => 'valid_state_81',
            'keycloak_code_verifier' => 'dummy_verifier_81',
        ])->get('/auth/login/callback?state=valid_state_81&code=test_code');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['sso' => 'Akun staf masuk lewat halaman login kata sandi']);
        $this->assertGuest();

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message) use ($nim, $email) {
                return str_contains($message, 'role tidak diizinkan SSO')
                    && str_contains($message, 'SSO-')
                    && str_contains($message, Masking::nim($nim))
                    && ! str_contains($message, $nim)
                    && ! str_contains($message, $email);
            });
    }

    public function test_akun_diblokir_ditolak_sso_dengan_pesan_ramah_dan_log_kode_referensi(): void
    {
        Log::spy();
        $this->setKeycloakConfig(true);

        $nim = '2241010082';
        $email = '2241010082@student.unusa.ac.id';

        MasterMahasiswaUnusa::create([
            'nim' => $nim,
            'nama_lengkap' => 'Mahasiswa Blokir',
            'email_kampus' => $email,
            'status_aktif' => 'AKTIF',
        ]);

        $user = User::factory()->create([
            'nim' => $nim,
            'email' => $email,
            'role' => UserRole::MahasiswaKipk,
            'is_unusa' => true,
            'is_blocked' => true,
            'email_verified_at' => now(),
            'keycloak_sub' => null,
        ]);

        Http::fake([
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/token' => Http::response([
                'access_token' => 'token_82',
                'token_type' => 'Bearer',
            ], 200),
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/userinfo' => Http::response([
                'sub' => 'sub-82',
                'preferred_username' => $nim,
                'email' => $email,
            ], 200),
        ]);

        $response = $this->withSession([
            'keycloak_oauth_state' => 'valid_state_82',
            'keycloak_code_verifier' => 'dummy_verifier_82',
        ])->get('/auth/login/callback?state=valid_state_82&code=test_code');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['sso' => 'Akun Anda dinonaktifkan, hubungi admin']);
        $this->assertGuest();

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message) use ($nim, $email) {
                return str_contains($message, 'akun diblokir')
                    && str_contains($message, 'SSO-')
                    && str_contains($message, Masking::nim($nim))
                    && ! str_contains($message, $nim)
                    && ! str_contains($message, $email);
            });
    }

    public function test_sub_bentrok_ditolak_dengan_kode_referensi(): void
    {
        Log::spy();
        $this->setKeycloakConfig(true);

        $nim = '2241010083';
        $email = '2241010083@student.unusa.ac.id';
        $subBentrok = 'sub-sudah-dipakai-user-lain';

        // Akun lama memiliki subBentrok
        User::factory()->create([
            'nim' => '2241010000',
            'email' => 'lama@student.unusa.ac.id',
            'keycloak_sub' => $subBentrok,
            'role' => UserRole::MahasiswaKipk,
        ]);

        MasterMahasiswaUnusa::create([
            'nim' => $nim,
            'nama_lengkap' => 'Mahasiswa Baru Bentrok',
            'email_kampus' => $email,
            'status_aktif' => 'AKTIF',
        ]);

        // Akun baru dengan NIM 2241010083 belum tertaut sub
        $userBaru = User::factory()->create([
            'nim' => $nim,
            'email' => $email,
            'role' => UserRole::MahasiswaKipk,
            'keycloak_sub' => null,
        ]);

        Http::fake([
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/token' => Http::response([
                'access_token' => 'token_83',
                'token_type' => 'Bearer',
            ], 200),
            'https://sso.unusa.ac.id/realms/UNUSA/protocol/openid-connect/userinfo' => Http::response([
                'sub' => $subBentrok,
                'preferred_username' => $nim,
                'email' => $email,
            ], 200),
        ]);

        $response = $this->withSession([
            'keycloak_oauth_state' => 'valid_state_83',
            'keycloak_code_verifier' => 'dummy_verifier_83',
        ])->get('/auth/login/callback?state=valid_state_83&code=test_code');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('sso');
        $errorMessage = session('errors')->first('sso');
        $this->assertMatchesRegularExpression('/Login SSO gagal\. Sebutkan kode SSO-[A-Z0-9]{4} kepada admin/', $errorMessage);
        $this->assertGuest();

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message) use ($nim, $email) {
                return str_contains($message, 'sub bentrok')
                    && str_contains($message, 'SSO-')
                    && ! str_contains($message, $nim)
                    && ! str_contains($message, $email);
            });
    }

    public function test_normalisasi_nim_satu_pintu_bekerja_konsisten(): void
    {
        $this->assertSame('4330024012', NormalisasiNim::bersihkan(' 4330024012 '));
        $this->assertSame('4330024012', NormalisasiNim::bersihkan("4330024012\n"));
        $this->assertSame('', NormalisasiNim::bersihkan(null));
        $this->assertSame('', NormalisasiNim::bersihkan('   '));
    }
}
