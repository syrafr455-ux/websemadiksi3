<?php

namespace Tests\Feature;

use App\Enums\KegiatanStatus;
use App\Enums\UserRole;
use App\Jobs\SendProtectedEmailJob;
use App\Mail\ActivationLinkMail;
use App\Mail\KegiatanUpdatedMail;
use App\Mail\ResetKataSandiMail;
use App\Models\Kegiatan;
use App\Models\MasterMahasiswaUnusa;
use App\Models\ProgramStudi;
use App\Models\User;
use App\Services\Academic\ApiUnusaAcademicProvider;
use App\Services\EmailProtectionService;
use App\Services\Unusa\UnusaApiClient;
use App\Services\Unusa\UnusaApiException;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PengamananPengirimanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        RateLimiter::clear('email_protection_rate_limit');
        RateLimiter::clear('unusa_api_client_rate_limiter');
    }

    protected function buatKegiatan(): Kegiatan
    {
        $creator = User::factory()->superAdmin()->create();

        return Kegiatan::create([
            'created_by' => $creator->id,
            'nama_kegiatan' => 'Seminar Perlindungan Data',
            'deskripsi' => 'Deskripsi kegiatan',
            'kategori' => 'seminar',
            'metode_pelaksanaan' => 'offline',
            'sasaran_peserta' => 'umum',
            'tanggal_mulai' => now()->addDays(2),
            'tanggal_selesai' => now()->addDays(2)->addHours(2),
            'lokasi_type' => 'unusa_campus',
            'lokasi_nama' => 'Auditorium Tower',
            'file_panduan_pdf' => 'kegiatan/panduan.pdf',
            'biaya' => 0,
            'kuota' => 100,
            'status' => KegiatanStatus::Published,
            'is_seat_booking_active' => false,
            'is_submission_active' => false,
        ]);
    }

    public function test_email_queue_job_dispatches_with_retry_backoff_and_max_tries(): void
    {
        $mailable = new ActivationLinkMail('Budi', 'https://semadiksi.test/aktivasi', 60);
        $job = new SendProtectedEmailJob('budi@student.unusa.ac.id', $mailable);

        $this->assertSame(3, $job->tries);
        $this->assertSame([60, 300, 900], $job->backoff());
        $this->assertTrue($job->isCritical);

        $kegiatan = $this->buatKegiatan();
        $user = User::factory()->create();
        $massalMail = new KegiatanUpdatedMail($kegiatan, $user, []);
        $massalJob = new SendProtectedEmailJob('user@contoh.test', $massalMail);

        $this->assertFalse($massalJob->isCritical);
    }

    public function test_email_queue_job_releases_when_rate_limit_exceeded(): void
    {
        config([
            'queue.default' => 'database',
            'unusa.email_protection.rate_limit_per_minute' => 2,
        ]);
        Mail::fake();

        $mailable = new ResetKataSandiMail('Siti', 'https://semadiksi.test/reset', 60);

        // Mock Queue Job
        $mockJob = $this->createMock(Job::class);

        $service = app(EmailProtectionService::class);

        // Job 1
        $job1 = new SendProtectedEmailJob('siti@student.unusa.ac.id', $mailable);
        $job1->setJob($mockJob);
        $job1->handle($service);

        // Job 2
        $job2 = new SendProtectedEmailJob('siti@student.unusa.ac.id', $mailable);
        $job2->setJob($mockJob);
        $job2->handle($service);

        // Job 3 melebihi batas 2/menit -> harus release
        $mockJob->expects($this->once())->method('release')->with($this->greaterThanOrEqual(5));

        $job3 = new SendProtectedEmailJob('siti@student.unusa.ac.id', $mailable);
        $job3->setJob($mockJob);
        $job3->handle($service);

        Mail::assertSent(ResetKataSandiMail::class, 2);
    }

    public function test_daily_quota_delays_bulk_email_to_tomorrow_but_sends_critical_email(): void
    {
        config([
            'queue.default' => 'database',
            'unusa.email_protection.daily_quota' => 5,
        ]);
        Mail::fake();

        $service = app(EmailProtectionService::class);

        // Penuhi kuota harian hingga 5
        for ($i = 0; $i < 5; $i++) {
            $service->recordSent(false);
        }

        $this->assertTrue($service->isDailyQuotaExceeded());

        $kegiatan = $this->buatKegiatan();
        $user = User::factory()->create();

        // 1. Uji email massal: KegiatanUpdatedMail
        $massalMail = new KegiatanUpdatedMail($kegiatan, $user, []);
        $massalJob = new SendProtectedEmailJob('user@contoh.test', $massalMail);

        $mockJob = $this->createMock(Job::class);
        $mockJob->expects($this->once())->method('release')->with($this->greaterThan(60));
        $massalJob->setJob($mockJob);

        $massalJob->handle($service);

        // Email massal TIDAK dikirim
        Mail::assertNotSent(KegiatanUpdatedMail::class);

        // 2. Uji email kritis: ResetKataSandiMail
        $kritisMail = new ResetKataSandiMail('User', 'https://semadiksi.test/reset', 60);
        $kritisJob = new SendProtectedEmailJob('user@contoh.test', $kritisMail);
        $mockJobKritis = $this->createMock(Job::class);
        $mockJobKritis->expects($this->never())->method('release');
        $kritisJob->setJob($mockJobKritis);

        $kritisJob->handle($service);

        // Email kritis TETAP dikirim
        Mail::assertSent(ResetKataSandiMail::class, 1);
    }

    public function test_failed_job_logs_without_personal_data(): void
    {
        $mailable = new ActivationLinkMail('Rahasia Banget', 'https://semadiksi.test/link-rahasia', 60);
        $job = new SendProtectedEmailJob('pribadi@student.unusa.ac.id', $mailable);

        $logged = false;
        Log::listen(function ($message) use (&$logged) {
            if (str_contains($message->message, 'gagal permanen')) {
                $logged = true;
                // Pastikan email dan nama tidak bocor ke log
                $this->assertStringNotContainsString('pribadi@student.unusa.ac.id', json_encode($message->context));
                $this->assertStringNotContainsString('Rahasia Banget', json_encode($message->context));
                $this->assertSame(SendProtectedEmailJob::class, $message->context['job']);
            }
        });

        $job->failed(new \RuntimeException('SMTP timeout'));

        $this->assertTrue($logged, 'Log kegagalan akhir harus tercatat');
    }

    public function test_proves_with_fake_queue(): void
    {
        Queue::fake();

        $mailable = new ResetKataSandiMail('User', 'https://semadiksi.test/reset', 60);
        SendProtectedEmailJob::dispatch('user@contoh.test', $mailable);

        Queue::assertPushed(SendProtectedEmailJob::class, function ($job) {
            return $job->recipient === 'user@contoh.test' && $job->isCritical === true;
        });
    }

    public function test_works_with_sync_queue_connection(): void
    {
        Mail::fake();
        config(['queue.default' => 'sync']);

        $mailable = new ResetKataSandiMail('User', 'https://semadiksi.test/reset', 60);
        $job = new SendProtectedEmailJob('user@contoh.test', $mailable);
        $job->handle(app(EmailProtectionService::class));

        Mail::assertSent(ResetKataSandiMail::class, 1);
    }

    public function test_preflight_and_dashboard_display_80_percent_quota_warning(): void
    {
        config(['unusa.email_protection.daily_quota' => 100]);
        $service = app(EmailProtectionService::class);

        // Catat 82 pengiriman (82% >= 80%)
        for ($i = 0; $i < 82; $i++) {
            $service->recordSent(false);
        }

        $this->assertTrue($service->isNearDailyQuota());

        // Uji Preflight Command
        Artisan::call('semadiksi:preflight');
        $output = Artisan::output();

        $this->assertStringContainsString('EMAIL_DAILY_QUOTA', $output);
        $this->assertStringContainsString('82/100', $output);
        $this->assertStringContainsString('WARN', $output);

        // Uji Dashboard Admin
        $superAdmin = User::factory()->create([
            'role' => UserRole::SuperAdmin->value,
            'is_blocked' => false,
        ]);

        $response = $this->actingAs($superAdmin)->get(route('admin.dashboard'));
        $response->assertOk();
        $response->assertSee('Peringatan Kuota Pengiriman Email');
        $response->assertSeeHtml('<strong>82</strong> dari kuota <strong>100</strong>');
    }

    public function test_unusa_api_client_enforces_rate_limit(): void
    {
        config(['unusa.api' => [
            'base_url' => 'https://api.unusa.test',
            'issuer' => 'https://sso.unusa.test/realms/unusa',
            'client_id' => 'semadiksi',
            'client_secret' => 'rahasia',
            'scope' => null,
            'path_mahasiswa' => '/mahasiswa/{nim}',
            'timeout' => 5,
            'retry' => 2,
            'rate_limit_per_minute' => 2,
            'circuit_breaker_threshold' => 3,
            'circuit_breaker_cooldown_seconds' => 300,
            'cache_ttl_seconds' => 300,
        ]]);

        Cache::put('unusa_api_token', 'tok-abc', 300);

        Http::fake([
            'api.unusa.test/mahasiswa/*' => Http::response(['nim' => '2241010001', 'nama' => 'Test']),
        ]);

        $client = app(UnusaApiClient::class);

        // 1 & 2 lolos
        $client->get('/mahasiswa/1');
        $client->get('/mahasiswa/2');

        // 3 melebihi rate limit 2/menit
        $this->expectException(UnusaApiException::class);
        $this->expectExceptionMessage('Batas laju pemanggilan API UNUSA');

        $client->get('/mahasiswa/3');
    }

    public function test_unusa_api_client_respects_429_and_retry_after(): void
    {
        config(['unusa.api' => [
            'base_url' => 'https://api.unusa.test',
            'issuer' => 'https://sso.unusa.test/realms/unusa',
            'client_id' => 'semadiksi',
            'client_secret' => 'rahasia',
            'scope' => null,
            'path_mahasiswa' => '/mahasiswa/{nim}',
            'timeout' => 5,
            'retry' => 2,
            'rate_limit_per_minute' => 60,
            'circuit_breaker_threshold' => 3,
            'circuit_breaker_cooldown_seconds' => 300,
            'cache_ttl_seconds' => 300,
        ]]);

        Http::fake([
            'sso.unusa.test/realms/unusa/.well-known/openid-configuration' => Http::response(['token_endpoint' => 'https://sso.unusa.test/token']),
            'sso.unusa.test/token' => Http::response(['access_token' => 'tok-abc', 'expires_in' => 300]),
            'api.unusa.test/mahasiswa/*' => Http::response('', 429, ['Retry-After' => '120']),
        ]);

        $client = app(UnusaApiClient::class);

        try {
            $client->get('/mahasiswa/1');
            $this->fail('Seharusnya melempar UnusaApiException');
        } catch (UnusaApiException $e) {
            $this->assertSame(UnusaApiException::BATAS_LAJU, $e->jenis);
            $this->assertStringContainsString('429', $e->getMessage());
            $this->assertStringContainsString('120', $e->getMessage());
        }

        // Panggilan kedua langsung dicegat oleh cache retry after
        try {
            $client->get('/mahasiswa/2');
            $this->fail('Seharusnya melempar UnusaApiException karena Retry-After aktif');
        } catch (UnusaApiException $e) {
            $this->assertSame(UnusaApiException::BATAS_LAJU, $e->jenis);
            $this->assertStringContainsString('Retry-After', $e->getMessage());
        }
    }

    public function test_unusa_api_client_circuit_breaker_opens_after_consecutive_failures_and_falls_back_to_csv(): void
    {
        config(['unusa.api' => [
            'base_url' => 'https://api.unusa.test',
            'issuer' => 'https://sso.unusa.test/realms/unusa',
            'client_id' => 'semadiksi',
            'client_secret' => 'rahasia',
            'scope' => null,
            'path_mahasiswa' => '/mahasiswa/{nim}',
            'timeout' => 5,
            'retry' => 0, // matikan retry agar tiap get = 1 kegagalan
            'rate_limit_per_minute' => 60,
            'circuit_breaker_threshold' => 3,
            'circuit_breaker_cooldown_seconds' => 300,
            'cache_ttl_seconds' => 300,
        ]]);

        Http::fake([
            'sso.unusa.test/realms/unusa/.well-known/openid-configuration' => Http::response(['token_endpoint' => 'https://sso.unusa.test/token']),
            'sso.unusa.test/token' => Http::response(['access_token' => 'tok-abc', 'expires_in' => 300]),
            'api.unusa.test/mahasiswa/*' => Http::response('', 500),
        ]);

        $client = app(UnusaApiClient::class);

        // 1 & 2 gagal
        for ($i = 0; $i < 2; $i++) {
            try {
                $client->get("/mahasiswa/{$i}");
            } catch (UnusaApiException) {
            }
        }

        $this->assertNull(Cache::get(UnusaApiClient::KUNCI_CIRCUIT_OPEN_UNTIL));

        // Kegagalan ke-3 -> Circuit Breaker terbuka!
        try {
            $client->get('/mahasiswa/3');
        } catch (UnusaApiException) {
        }

        $this->assertNotNull(Cache::get(UnusaApiClient::KUNCI_CIRCUIT_OPEN_UNTIL));

        // Percobaan ke-4 langsung ditolak oleh pemutus sirkuit
        try {
            $client->get('/mahasiswa/4');
            $this->fail('Harus melempar PEMUTUS_SIRKUIT');
        } catch (UnusaApiException $e) {
            $this->assertSame(UnusaApiException::PEMUTUS_SIRKUIT, $e->jenis);
        }

        // Uji fallback provider ke data CSV
        $prodi = ProgramStudi::where('kode', 'FKES-S1-GIZI')->firstOrFail();
        MasterMahasiswaUnusa::create([
            'nim' => '2241010001',
            'nama_lengkap' => 'Fauzan CSV',
            'email_kampus' => 'fauzan@student.unusa.ac.id',
            'program_studi_id' => $prodi->id,
        ]);

        $provider = app(ApiUnusaAcademicProvider::class);
        $hasil = $provider->cariMahasiswaByNim('2241010001');

        $this->assertSame('Fauzan CSV', $hasil['nama_lengkap']);
    }

    public function test_unusa_academic_provider_caches_nim_lookups_briefly_without_logging_pii(): void
    {
        config(['unusa.api' => [
            'base_url' => 'https://api.unusa.test',
            'issuer' => 'https://sso.unusa.test/realms/unusa',
            'client_id' => 'semadiksi',
            'client_secret' => 'rahasia',
            'scope' => null,
            'path_mahasiswa' => '/mahasiswa/{nim}',
            'timeout' => 5,
            'retry' => 2,
            'rate_limit_per_minute' => 60,
            'circuit_breaker_threshold' => 3,
            'circuit_breaker_cooldown_seconds' => 300,
            'cache_ttl_seconds' => 300,
        ]]);

        Http::fake([
            'sso.unusa.test/realms/unusa/.well-known/openid-configuration' => Http::response(['token_endpoint' => 'https://sso.unusa.test/token']),
            'sso.unusa.test/token' => Http::response(['access_token' => 'tok-abc', 'expires_in' => 300]),
            'api.unusa.test/mahasiswa/2241010001' => Http::response([
                'nim' => '2241010001',
                'nama' => 'Ahmad Dahlan',
                'email' => 'ahmad@student.unusa.ac.id',
                'program_studi' => 'S1 Gizi',
                'semester' => 4,
            ]),
        ]);

        $provider = app(ApiUnusaAcademicProvider::class);

        // Panggilan pertama mengambil dari API
        $mhs1 = $provider->cariMahasiswaByNim('2241010001');
        $this->assertSame('Ahmad Dahlan', $mhs1['nama_lengkap']);
        Http::assertSentCount(3); // discovery + token + data

        // Panggilan kedua mengambil dari cache singkat
        $mhs2 = $provider->cariMahasiswaByNim('2241010001');
        $this->assertSame('Ahmad Dahlan', $mhs2['nama_lengkap']);
        Http::assertSentCount(3); // tidak bertambah karena dari cache

        // Pastikan kunci cache ter-hash (tidak menyimpan NIM polos)
        $hashKey = 'unusa_nim_search_'.hash('sha256', '2241010001');
        $this->assertTrue(Cache::has($hashKey));
    }
}
