<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tes tidak boleh butuh internet: request HTTP keluar yang tidak di-fake langsung gagal.
        // Pemeriksaan DNS email (email:rfc,dns) dimatikan lewat VALIDASI_EMAIL_DNS=false di phpunit.xml.
        Http::preventStrayRequests();
    }

    /**
     * Halaman Inertia awal (komponen + props) yang tertanam di HTML.
     *
     * @return array{component: string, props: array<string, mixed>}
     */
    protected function halamanInertia($response): array
    {
        preg_match('#<script data-page="app" type="application/json">(.*?)</script>#s', $response->getContent(), $m);

        return json_decode($m[1] ?? '{}', true);
    }
}
