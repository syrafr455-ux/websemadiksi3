<?php

namespace App\Services\Unusa;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Klien API UNUSA (OIDC client_credentials). Timeout 5 detik, 2 kali percobaan ulang untuk gagal
 * tersambung, verifikasi sertifikat TLS selalu aktif, token di-cache sampai kedaluwarsa dikurangi 30 detik.
 * Galat dipetakan ke UnusaApiException (lihat docs/api-unusa.md). Jangan mencatat token atau data pribadi.
 */
class UnusaApiClient
{
    private const KUNCI_TOKEN = 'unusa_api_token';

    private const MARJIN_TOKEN_DETIK = 30;

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
        try {
            $respons = $panggil();
        } catch (ConnectionException) {
            throw new UnusaApiException(UnusaApiException::TIDAK_TERJANGKAU);
        }

        if (in_array($respons->status(), [401, 403], true)) {
            throw new UnusaApiException(UnusaApiException::HAK_AKSES);
        }
        if ($respons->status() === 404 && $izinkan404) {
            return $respons;
        }
        if (! $respons->successful()) {
            throw new UnusaApiException(UnusaApiException::LAINNYA);
        }

        return $respons;
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
