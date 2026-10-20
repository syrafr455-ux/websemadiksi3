<?php

namespace Tests\Feature;

use App\Enums\KegiatanStatus;
use App\Models\Kegiatan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Halaman publik /kegiatan/{id} (detail & booking kursi).
 */
class KegiatanPublikTest extends TestCase
{
    use RefreshDatabase;

    protected function kegiatan(): Kegiatan
    {
        return Kegiatan::create([
            'nama_kegiatan' => 'Seminar Publik',
            'kategori' => 'seminar',
            'metode_pelaksanaan' => 'offline',
            'sasaran_peserta' => 'umum',
            'tanggal_mulai' => now()->addWeek(),
            'lokasi_type' => 'unusa_campus',
            'lokasi_nama' => 'Auditorium',
            'file_panduan_pdf' => 'kegiatan/panduan.pdf',
            'biaya' => 0,
            'kuota' => 50,
            'status' => KegiatanStatus::Published,
        ]);
    }

    public function test_detail_page_is_found_by_id_without_querying_a_slug_column(): void
    {
        $kegiatan = $this->kegiatan();

        // Kolom slug tidak ada: SQLite mentoleransi "slug" sebagai teks, PostgreSQL tidak (error 500).
        $query = [];
        DB::listen(function ($q) use (&$query) {
            $query[] = $q->sql;
        });

        $response = $this->get(route('kegiatan.detail', $kegiatan->id))->assertOk();

        preg_match('#<script data-page="app" type="application/json">(.*?)</script>#s', $response->getContent(), $m);
        $page = json_decode($m[1], true);
        $this->assertSame('Kegiatan/Show', $page['component']);
        $this->assertSame($kegiatan->id, $page['props']['kegiatan']['id']);
        $this->assertEmpty(array_filter($query, fn (string $sql) => str_contains($sql, 'slug')), 'Query tidak boleh merujuk kolom slug.');
    }

    public function test_unknown_or_non_numeric_id_returns_404(): void
    {
        $this->kegiatan();

        $this->get('/kegiatan/999999')->assertNotFound();
        $this->get('/kegiatan/seminar-publik')->assertNotFound();
        $this->get('/kegiatan/1abc')->assertNotFound();
    }

    public function test_deleted_kegiatan_is_not_shown(): void
    {
        $kegiatan = $this->kegiatan();
        $kegiatan->delete();

        $this->get(route('kegiatan.detail', $kegiatan->id))->assertNotFound();
    }
}
