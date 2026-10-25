<?php

namespace App\Services\Unusa;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Klien API UNUSA (OIDC client_credentials). Timeout 5 detik, 2 kali percobaan ulang untuk gagal
 * tersambung, verifikasi sertifikat TLS selalu aktif, token di-cache sampai kedaluwarsa dikurangi 30 detik.
 * Dilengkapi batas laju sisi klien, penanganan 429 & Retry-After, pemutus sirkuit (circuit breaker)
 * sederhana untuk kegagalan berulang. Galat dipetakan ke UnusaApiException (lihat docs/api-unusa.md).
 * Jangan mencatat token atau data pribadi ke dalam log.
 */
class UnusaApiClient
{
    private const KUNCI_TOKEN = 'unusa_api_token';

    private const MARJIN_TOKEN_DETIK = 30;

    public const KUNCI_CIRCUIT_FAILURES = 'unusa_api_consecutive_failures';

    public const KUNCI_CIRCUIT_OPEN_UNTIL = 'unusa_api_circuit_open_until';

    public const KUNCI_RETRY_AFTER_UNTIL = 'unusa_api_retry_after_until';

    public const KUNCI_RATE_LIMITER = 'unusa_api_client_rate_limiter';

    /**
     * Endpoint token dari dokumen discovery OIDC issuer.
     */
    public function endpointToken(): string
    {
        $issuer = $this->konfigurasi('issuer');
        $respons = $this->kirim(fn () => $this->permintaan()->get(rtrim($issuer, '/').'/.well-known/openid-configuration'));

        $endpoint = $respons->json('token_endpoint');
        if (! is_string($endpoint) || $endpoint === '') {
            throw new UnusaApiException(UnusaApiException::LAINNYA);
        }

        return $endpoint;
    }

    public function token(): string
    {
        $tersimpan = Cache::get(self::KUNCI_TOKEN);
        if (is_string($tersimpan) && $tersimpan !== '') {
            return $tersimpan;
        }

        $clientId = $this->konfigurasi('client_id');
        $secret = $this->konfigurasi('client_secret');
        $endpoint = $this->endpointToken();

        $respons = $this->kirim(fn () => $this->permintaan()->asForm()->post($endpoint, array_filter([
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $secret,
            'scope' => config('unusa.api.scope'),
        ])));

        $token = $respons->json('access_token');
        if (! is_string($token) || $token === '') {
            throw new UnusaApiException(UnusaApiException::LAINNYA);
        }

        $detik = (int) $respons->json('expires_in', 0) - self::MARJIN_TOKEN_DETIK;
        if ($detik > 0) {
            Cache::put(self::KUNCI_TOKEN, $token, $detik);
        }

        return $token;
    }

    /**
     * GET ke base_url + path, dengan token bearer. Mengembalikan JSON; null bila 404 (data tidak ada).
     *
     * @return array<string, mixed>|null
     */
    public function get(string $path): ?array
    {
        $url = rtrim($this->konfigurasi('base_url'), '/').'/'.ltrim($path, '/');
        $token = $this->token();

        try {
            $respons = $this->kirim(fn () => $this->permintaan()->withToken($token)->acceptJson()->get($url), izinkan404: true);
        } catch (UnusaApiException $e) {
            if ($e->jenis === UnusaApiException::HAK_AKSES) {
                // Token mungkin dicabut sebelum kedaluwarsa: buang agar percobaan berikutnya meminta yang baru.
                Cache::forget(self::KUNCI_TOKEN);
            }
            throw $e;
        }

        return $respons->status() === 404 ? null : ($respons->json() ?? []);
    }

    private function permintaan(): PendingRequest
    {
        // Verifikasi TLS dibiarkan pada nilai bawaan (aktif). Jangan pernah memakai withoutVerifying().
        return Http::timeout((int) config('unusa.api.timeout', 5))
            ->connectTimeout((int) config('unusa.api.timeout', 5))
            // retry(n) = total percobaan; 1 + 2 kali ulang. Hanya gagal tersambung yang diulang.
            ->retry(1 + (int) config('unusa.api.retry', 2), 200, fn ($e) => $e instanceof ConnectionException, throw: false);
    }

    /**
     * @param  callable(): Response  $panggil
     */
    private function kirim(callable $panggil, bool $izinkan404 = false): Response
    {
        // 1. Periksa apakah Pemutus Sirkuit (Circuit Breaker) sedang aktif (terbuka)
        $circuitOpenUntil = Cache::get(self::KUNCI_CIRCUIT_OPEN_UNTIL);
        if ($circuitOpenUntil && now()->timestamp < $circuitOpenUntil) {
            $sisa = $circuitOpenUntil - now()->timestamp;
            throw new UnusaApiException(
                UnusaApiException::PEMUTUS_SIRKUIT,
                "Pemutus sirkuit API UNUSA aktif setelah kegagalan berulang. Cooldown sisa {$sisa} detik."
            );
        }

        // 2. Periksa apakah dalam masa jeda 429 Retry-After
        $retryAfterUntil = Cache::get(self::KUNCI_RETRY_AFTER_UNTIL);
        if ($retryAfterUntil && now()->timestamp < $retryAfterUntil) {
            $sisa = $retryAfterUntil - now()->timestamp;
            throw new UnusaApiException(
                UnusaApiException::BATAS_LAJU,
                "API UNUSA sedang membatasi laju (HTTP 429). Retry-After sisa {$sisa} detik."
            );
        }

        // 3. Periksa Batas Laju Sisi Klien
        $rateLimit = (int) config('unusa.api.rate_limit_per_minute', 60);
        if ($rateLimit > 0 && RateLimiter::tooManyAttempts(self::KUNCI_RATE_LIMITER, $rateLimit)) {
            throw new UnusaApiException(
                UnusaApiException::BATAS_LAJU,
                'Batas laju pemanggilan API UNUSA sisi klien tercapai. Coba lagi dalam beberapa saat.'
            );
        }

        if ($rateLimit > 0) {
            RateLimiter::hit(self::KUNCI_RATE_LIMITER, 60);
        }

        try {
            $respons = $panggil();
        } catch (ConnectionException) {
            $this->catatKegagalan();
            throw new UnusaApiException(UnusaApiException::TIDAK_TERJANGKAU);
        }

        // 4. Tangani Status HTTP 429 (Too Many Requests) dan Header Retry-After
        if ($respons->status() === 429) {
            $retryHeader = $respons->header('Retry-After');
            $cooldown = is_numeric($retryHeader) ? (int) $retryHeader : 60;
            Cache::put(self::KUNCI_RETRY_AFTER_UNTIL, now()->addSeconds($cooldown)->timestamp, $cooldown);
            $this->catatKegagalan();

            throw new UnusaApiException(
                UnusaApiException::BATAS_LAJU,
                "API UNUSA mengembalikan status 429 Too Many Requests (Retry-After: {$cooldown} detik)."
            );
        }

        if (in_array($respons->status(), [401, 403], true)) {
            throw new UnusaApiException(UnusaApiException::HAK_AKSES);
        }

        if ($respons->status() === 404 && $izinkan404) {
            $this->catatSukses();

            return $respons;
        }

        if (! $respons->successful()) {
            $this->catatKegagalan();
            throw new UnusaApiException(UnusaApiException::LAINNYA);
        }

        $this->catatSukses();

        return $respons;
    }

    private function catatSukses(): void
    {
        Cache::forget(self::KUNCI_CIRCUIT_FAILURES);
        Cache::forget(self::KUNCI_CIRCUIT_OPEN_UNTIL);
    }

    private function catatKegagalan(): void
    {
        $failures = (int) Cache::get(self::KUNCI_CIRCUIT_FAILURES, 0) + 1;
        Cache::put(self::KUNCI_CIRCUIT_FAILURES, $failures, 3600);

        $threshold = (int) config('unusa.api.circuit_breaker_threshold', 3);
        if ($failures >= $threshold) {
            $cooldown = (int) config('unusa.api.circuit_breaker_cooldown_seconds', 300);
            Cache::put(self::KUNCI_CIRCUIT_OPEN_UNTIL, now()->addSeconds($cooldown)->timestamp, $cooldown);
            Log::warning('Pemutus sirkuit API UNUSA diaktifkan setelah kegagalan berulang.', [
                'kegagalan_beruntun' => $failures,
                'cooldown_detik' => $cooldown,
            ]);
        }
    }

    private function konfigurasi(string $kunci): string
    {
        $nilai = config("unusa.api.{$kunci}");
        if (! is_string($nilai) || trim($nilai) === '') {
            throw new UnusaApiException(UnusaApiException::KONFIGURASI);
        }

        return $nilai;
    }
}
