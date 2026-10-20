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
}
