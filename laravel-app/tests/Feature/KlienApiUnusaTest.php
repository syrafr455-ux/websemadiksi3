<?php

namespace Tests\Feature;

use App\Contracts\UnusaAcademicProvider;
use App\Models\MasterMahasiswaUnusa;
use App\Models\ProgramStudi;
use App\Services\Academic\ApiUnusaAcademicProvider;
use App\Services\Unusa\UnusaApiClient;
use App\Services\Unusa\UnusaApiException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KlienApiUnusaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['unusa.api' => [
            'base_url' => 'https://api.unusa.test', 'issuer' => 'https://sso.unusa.test/realms/unusa',
            'client_id' => 'semadiksi', 'client_secret' => 'rahasia', 'scope' => null,
            'path_mahasiswa' => '/mahasiswa/{nim}', 'timeout' => 5, 'retry' => 2,
        ]]);
        Cache::flush();
    }

    protected function fakeNormal(array $dataMahasiswa = [], int $expiresIn = 300): void
    {
        Http::fake([
            'sso.unusa.test/realms/unusa/.well-known/openid-configuration' => Http::response(['token_endpoint' => 'https://sso.unusa.test/token']),
            'sso.unusa.test/token' => Http::response(['access_token' => 'tok-abc', 'expires_in' => $expiresIn]),
            'api.unusa.test/mahasiswa/*' => Http::response($dataMahasiswa ?: ['nim' => '2241010001', 'nama' => 'Fauzan Hidayat', 'email' => 'f@student.unusa.ac.id', 'program_studi' => 'S1 Gizi', 'semester' => 5, 'prestasi' => ['a']]),
        ]);
    }

    public function test_provider_reads_student_from_api_with_bearer_token(): void
    {
        $this->fakeNormal();

        $mhs = app(ApiUnusaAcademicProvider::class)->cariMahasiswaByNim('2241010001');

        $this->assertSame('Fauzan Hidayat', $mhs['nama_lengkap']);
        $this->assertSame(5, $mhs['semester']);
        $this->assertSame(['a'], $mhs['prestasi']);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/mahasiswa/2241010001') && $r->hasHeader('Authorization', 'Bearer tok-abc'));
    }

    public function test_token_is_cached_until_expiry_minus_30_seconds(): void
    {
        $this->fakeNormal(expiresIn: 300);
        $klien = app(UnusaApiClient::class);

        $klien->get('/mahasiswa/1');
        $klien->get('/mahasiswa/2');
        Http::assertSentCount(4); // discovery + token + 2 data (token tidak diminta ulang)

        $this->travel(269)->seconds();
        $klien->get('/mahasiswa/3');
        Http::assertSentCount(5);

        $this->travel(2)->seconds(); // 271 dtk > 300 - 30
        $klien->get('/mahasiswa/4');
        Http::assertSentCount(8); // discovery + token baru + data
    }

    public function test_unreachable_server_is_retried_twice_then_mapped_to_vpn_message(): void
    {
        $percobaan = 0;
        Http::fake(function () use (&$percobaan) {
            $percobaan++;
            throw new ConnectionException('cURL error 28: Connection timed out');
        });

        try {
            app(UnusaApiClient::class)->get('/mahasiswa/1');
            $this->fail('Seharusnya melempar UnusaApiException');
        } catch (UnusaApiException $e) {
            $this->assertSame(UnusaApiException::TIDAK_TERJANGKAU, $e->jenis);
            $this->assertSame('Server UNUSA tidak terjangkau. Periksa VPN atau gateway.', $e->getMessage());
        }

        $this->assertSame(3, $percobaan); // 1 percobaan + 2 ulang
    }

    public function test_401_and_403_map_to_access_message_without_retry_and_drop_cached_token(): void
    {
        Http::fake([
            'sso.unusa.test/realms/unusa/.well-known/openid-configuration' => Http::response(['token_endpoint' => 'https://sso.unusa.test/token']),
            'sso.unusa.test/token' => Http::response(['access_token' => 'tok-abc', 'expires_in' => 300]),
            'api.unusa.test/*' => Http::response('', 403),
        ]);

        try {
            app(UnusaApiClient::class)->get('/mahasiswa/1');
            $this->fail('Seharusnya melempar UnusaApiException');
        } catch (UnusaApiException $e) {
            $this->assertSame(UnusaApiException::HAK_AKSES, $e->jenis);
            $this->assertStringContainsString('401/403', $e->getMessage());
        }

        Http::assertSentCount(3);
        $this->assertNull(Cache::get('unusa_api_token'));
    }

    public function test_token_endpoint_rejection_is_also_an_access_error(): void
    {
        Http::fake([
            'sso.unusa.test/realms/unusa/.well-known/openid-configuration' => Http::response(['token_endpoint' => 'https://sso.unusa.test/token']),
            'sso.unusa.test/token' => Http::response(['error' => 'unauthorized_client'], 401),
        ]);

        $this->expectException(UnusaApiException::class);
        $this->expectExceptionMessage('401/403');
        app(UnusaApiClient::class)->token();
    }

    public function test_provider_falls_back_to_csv_data_when_api_is_down_and_user_sees_no_technical_error(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));
        $prodi = ProgramStudi::where('kode', 'FKES-S1-GIZI')->firstOrFail();
        MasterMahasiswaUnusa::create(['nim' => '2241010001', 'nama_lengkap' => 'Fauzan Hidayat', 'email_kampus' => 'f@student.unusa.ac.id', 'program_studi_id' => $prodi->id]);

        config(['unusa.academic_provider' => 'api']);
        $mhs = app(UnusaAcademicProvider::class)->cariMahasiswaByNim('2241010001');

        $this->assertSame('Fauzan Hidayat', $mhs['nama_lengkap']);
        $this->assertNull(app(UnusaAcademicProvider::class)->cariMahasiswaByNim('9999999999'));
    }

    public function test_registration_flow_survives_api_outage_with_neutral_result(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));
        config(['unusa.academic_provider' => 'api']);

        $this->post(route('daftar.unusa.cek'), ['nim' => '2241010001'])->assertRedirect(route('register'));
        $hasil = $this->halamanInertia($this->get(route('register'))->assertOk())['props']['hasil'];

        $this->assertSame('tidak_ditemukan', $hasil['status']);
        $this->assertStringNotContainsString('VPN', json_encode($hasil));
    }

    public function test_incomplete_configuration_is_reported_for_admin(): void
    {
        config(['unusa.api.issuer' => null]);
        Http::fake();

        try {
            app(UnusaApiClient::class)->token();
            $this->fail('Seharusnya melempar UnusaApiException');
        } catch (UnusaApiException $e) {
            $this->assertSame(UnusaApiException::KONFIGURASI, $e->jenis);
        }
        Http::assertNothingSent();
    }

    public function test_tls_verification_is_never_disabled_in_the_client(): void
    {
        $sumber = file_get_contents(app_path('Services/Unusa/UnusaApiClient.php'));

        $this->assertStringNotContainsString('withoutVerifying', preg_replace('#//.*$#m', '', $sumber));
        $this->assertStringNotContainsString("'verify'", $sumber);
        $this->assertSame(5, config('unusa.api.timeout'));
    }
}
