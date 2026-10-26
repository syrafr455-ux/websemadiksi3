<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_loads_successfully(): void
    {
        $response = $this->get(route('home'));
        $response->assertStatus(200);
        $response->assertSee('SEMADIKSI');
    }

    public function test_profil_semadiksi_page_loads_successfully(): void
    {
        $response = $this->get(route('profil.semadiksi'));
        $response->assertStatus(200);
        $response->assertSee('Profil SEMADIKSI', false);
    }

    public function test_berita_acara_index_loads_successfully(): void
    {
        $response = $this->get(route('berita.acara.index'));
        $response->assertStatus(200);
        $response->assertSee('Arsip Berita Acara');
    }

    public function test_verifikasi_sertifikat_page_loads_successfully(): void
    {
        $response = $this->get(route('verifikasi.sertifikat'));
        $response->assertStatus(200);
        $response->assertSee('Verifikasi Keaslian Sertifikat');
    }

    public function test_absensi_public_page_loads_successfully(): void
    {
        $response = $this->get(route('absensi.public'));
        $response->assertStatus(200);
        $response->assertSee('Presensi Mandiri');
    }

    public function test_login_page_loads_successfully(): void
    {
        $response = $this->get(route('login'));
        $response->assertStatus(200);
        $response->assertSee('Masuk');
    }

    public function test_admin_login_page_loads_successfully(): void
    {
        $response = $this->get(route('admin.login'));
        $response->assertStatus(200);
        $response->assertSee('Panel Administrator');
    }
}
