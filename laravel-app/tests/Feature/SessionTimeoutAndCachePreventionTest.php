<?php

namespace Tests\Feature;

use App\Enums\KegiatanStatus;
use App\Models\Kegiatan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SessionTimeoutAndCachePreventionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_respons_halaman_terautentikasi_dan_halaman_masuk_memuat_no_store_tetapi_halaman_publik_tidak(): void
    {
        $student = User::factory()->mahasiswaKipk()->create([
            'email_verified_at' => now(),
        ]);

        $admin = User::factory()->superAdmin()->create();

        // 1. Halaman terautentikasi (dasbor peserta)
        $resStudent = $this->actingAs($student)->get('/dashboard');
        $resStudent->assertOk();
        $this->assertStringContainsString('no-store', (string) $resStudent->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-cache', (string) $resStudent->headers->get('Cache-Control'));
        $this->assertSame('no-cache', $resStudent->headers->get('Pragma'));

        // 2. Halaman admin
        $resAdmin = $this->actingAs($admin)->get(route('admin.users'));
        $this->assertTrue(in_array($resAdmin->status(), [200, 302], true));
        $this->assertStringContainsString('no-store', (string) $resAdmin->headers->get('Cache-Control'));

        // 3. Respons Inertia
        $resInertia = $this->actingAs($admin)
            ->withHeaders(['X-Inertia' => 'true'])
            ->get('/admin/kegiatan');
        $this->assertStringContainsString('no-store', (string) $resInertia->headers->get('Cache-Control'));

        // 4. Halaman /masuk dan /admin/masuk
        auth()->logout();
        $this->flushHeaders();
        $resLogin = $this->get('/masuk');
        $resLogin->assertOk();
        $this->assertStringContainsString('no-store', (string) $resLogin->headers->get('Cache-Control'));

        $resAdminLogin = $this->get('/admin/masuk');
        $resAdminLogin->assertOk();
        $this->assertStringContainsString('no-store', (string) $resAdminLogin->headers->get('Cache-Control'));

        // 5. Halaman form aktivasi
        $resDaftar = $this->get('/daftar');
        $this->assertStringContainsString('no-store', (string) $resDaftar->headers->get('Cache-Control'));

        // 6. Halaman publik: landing page & /verifikasi TIDAK boleh memuat no-store
        $resHome = $this->get('/');
        $resHome->assertOk();
        $this->assertStringNotContainsString('no-store', (string) $resHome->headers->get('Cache-Control'));

        $resVerif = $this->get('/verifikasi');
        $resVerif->assertOk();
        $this->assertStringNotContainsString('no-store', (string) $resVerif->headers->get('Cache-Control'));
    }

    public function test_setelah_logout_mengakses_dasbor_dialihkan_ke_login_dan_token_sesi_lama_tidak_bisa_dipakai(): void
    {
        $student = User::factory()->mahasiswaKipk()->create([
            'email_verified_at' => now(),
        ]);

        $this->actingAs($student);
        $this->get('/dashboard')->assertOk();

        // Logout
        $resLogout = $this->post('/keluar');
        $resLogout->assertRedirect(route('login'));
        $this->assertGuest();

        // Mengakses dasbor kembali harus dialihkan ke login
        $resAfter = $this->get('/dashboard');
        $resAfter->assertRedirect(route('login'));
    }

    public function test_permintaan_pada_menit_ke_14_tetap_berhasil_dan_menit_ke_16_dialihkan_ke_login_dengan_pesan_indonesia(): void
    {
        $student = User::factory()->mahasiswaKipk()->create([
            'email_verified_at' => now(),
        ]);

        Carbon::setTestNow('2026-10-25 10:00:00');

        $this->actingAs($student);
        $this->get('/dashboard')->assertOk();

        // Aktivitas pada menit ke-14: harus tetap berhasil dan memperpanjang sesi
        Carbon::setTestNow('2026-10-25 10:14:00');
        $this->get('/dashboard')->assertOk();

        // Aktivitas pada menit ke-28 (14 menit dari aktivitas terakhir): tetap berhasil
        Carbon::setTestNow('2026-10-25 10:28:00');
        $this->get('/dashboard')->assertOk();

        // Melewati 16 menit tanpa aktivitas (10:44:01)
        Carbon::setTestNow('2026-10-25 10:44:01');
        $response = $this->get('/dashboard');

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('warning', 'Sesi Anda berakhir karena tidak ada aktivitas selama 15 menit. Silakan masuk kembali.');
        $this->assertGuest();
    }

    public function test_staf_yang_tidak_aktif_selama_16_menit_dialihkan_ke_admin_masuk(): void
    {
        $admin = User::factory()->superAdmin()->create();

        Carbon::setTestNow('2026-10-25 10:00:00');

        $this->actingAs($admin);
        $this->get(route('admin.dashboard'))->assertOk();

        // Lewat 16 menit tanpa aktivitas
        Carbon::setTestNow('2026-10-25 10:16:01');
        $response = $this->get(route('admin.dashboard'));

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHas('warning', 'Sesi Anda berakhir karena tidak ada aktivitas selama 15 menit. Silakan masuk kembali.');
        $this->assertGuest();
    }

    public function test_permintaan_inertia_yang_kedaluwarsa_mendapat_409_x_inertia_location_dan_json_mendapat_401(): void
    {
        $student = User::factory()->mahasiswaKipk()->create([
            'email_verified_at' => now(),
        ]);

        Carbon::setTestNow('2026-10-25 10:00:00');
        $this->actingAs($student);
        $this->get('/dashboard');

        Carbon::setTestNow('2026-10-25 10:16:01');

        // Permintaan Inertia yang kedaluwarsa mendapat 409 dengan header X-Inertia-Location
        $resInertia = $this->withHeaders(['X-Inertia' => 'true'])->get('/dashboard');
        $resInertia->assertStatus(409);
        $this->assertSame(route('login'), $resInertia->headers->get('X-Inertia-Location'));

        // Permintaan JSON/ping mendapat 401 dengan pesan Indonesia
        Carbon::setTestNow('2026-10-25 10:00:00');
        $this->actingAs($student);
        $this->get('/dashboard');

        Carbon::setTestNow('2026-10-25 10:16:01');
        $resJson = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->post('/sesi/ping');

        $resJson->assertStatus(401);
        $resJson->assertJson([
            'message' => 'Sesi Anda berakhir karena tidak ada aktivitas selama 15 menit. Silakan masuk kembali.',
        ]);
    }

    public function test_ping_sesi_mengembalikan_sesi_tetap_hidup_dan_tidak_membocorkan_data_pribadi(): void
    {
        $student = User::factory()->mahasiswaKipk()->create([
            'email_verified_at' => now(),
        ]);

        Carbon::setTestNow('2026-10-25 10:00:00');
        $this->actingAs($student);

        $response = $this->post('/sesi/ping');
        $response->assertOk();
        $response->assertJson(['ok' => true]);

        $content = $response->getContent();
        $this->assertStringNotContainsString($student->nim, $content);
        $this->assertStringNotContainsString($student->email, $content);
        $this->assertStringNotContainsString($student->name, $content);

        // Maju 14 menit, lakukan ping -> harus memperpanjang sesi
        Carbon::setTestNow('2026-10-25 10:14:00');
        $this->post('/sesi/ping')->assertOk();

        // Maju ke 10:28 (14 menit dari ping) -> masih aktif
        Carbon::setTestNow('2026-10-25 10:28:00');
        $this->get('/dashboard')->assertOk();
    }

    public function test_kotak_centang_ingat_saya_tidak_ada_di_form_login(): void
    {
        $response = $this->get('/masuk');
        $response->assertOk();
        $response->assertDontSee('name="remember"', false);
        $response->assertDontSee('Ingat saya di perangkat ini');
    }

    public function test_autentikasi_lewat_cookie_ingat_saya_segera_dilogout(): void
    {
        $student = User::factory()->mahasiswaKipk()->create([
            'email_verified_at' => now(),
            'remember_token' => 'dummy_remember_token_123',
        ]);

        $this->actingAs($student);

        // Pasang viaRemember = true pada guard untuk simulasi autentikasi via cookie ingat-saya
        $guard = auth()->guard('web');
        $reflection = new \ReflectionClass($guard);
        $property = $reflection->getProperty('viaRemember');
        $property->setAccessible(true);
        $property->setValue($guard, true);

        $response = $this->get('/dashboard');
        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_halaman_pemindai_dan_proyektor_menggunakan_batas_waktu_kios_4_jam(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $kegiatan = Kegiatan::create([
            'nama_kegiatan' => 'Kios Test Kegiatan',
            'deskripsi' => 'Deskripsi kegiatan kios',
            'kategori' => 'seminar',
            'metode_pelaksanaan' => 'offline',
            'sasaran_peserta' => 'umum',
            'tanggal_mulai' => now()->addHour(),
            'tanggal_selesai' => now()->addHours(5),
            'lokasi_type' => 'unusa_campus',
            'lokasi_nama' => 'Auditorium Tower',
            'file_panduan_pdf' => 'kegiatan/panduan.pdf',
            'biaya' => 0,
            'kuota' => 100,
            'status' => KegiatanStatus::Published,
        ]);

        Carbon::setTestNow('2026-10-25 08:00:00');

        $this->actingAs($admin);
        $scannerUrl = route('admin.kegiatan.scanner', $kegiatan->id);
        $this->get($scannerUrl)->assertOk();

        // Maju 60 menit (melewati 15 menit biasa) -> di mode kios/scanner tetap aktif
        Carbon::setTestNow('2026-10-25 09:00:00');
        $this->get($scannerUrl)->assertOk();

        // Maju 241 menit dari aktivitas awal (melewati batas 240 menit / 4 jam) -> kedaluwarsa dan dialihkan
        Carbon::setTestNow('2026-10-25 13:01:00');
        $resExpired = $this->get($scannerUrl);
        $resExpired->assertRedirect(route('admin.login'));
        $this->assertGuest();
    }
}
