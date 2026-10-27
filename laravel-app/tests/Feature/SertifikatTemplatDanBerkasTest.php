<?php

namespace Tests\Feature;

use App\Enums\JenisSertifikat;
use App\Enums\KegiatanStatus;
use App\Enums\PeranSertifikat;
use App\Enums\StatusSertifikat;
use App\Models\AuditLog;
use App\Models\Kegiatan;
use App\Models\Sertifikat;
use App\Models\SertifikatTemplat;
use App\Models\User;
use App\Services\Sertifikat\BerkasSertifikatService;
use App\Services\Sertifikat\BuatPdfSertifikat;
use App\Services\Sertifikat\TemplatSertifikatService;
use App\Support\Sertifikat\FitTeks;
use App\Support\Sertifikat\TataLetak;
use App\Support\Sertifikat\TeksTemplat;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Bagian 4 dan 6: validasi templat, latar, pembuatan PDF lazy, hash, dan keamanan unduhan. */
class SertifikatTemplatDanBerkasTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Kegiatan $kegiatan;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        Carbon::setTestNow('2026-10-27 10:00:00');
        $this->admin = User::factory()->superAdmin()->create();
        $this->kegiatan = Kegiatan::create([
            'created_by' => $this->admin->id, 'nama_kegiatan' => 'Seminar Teknologi Nasional', 'kategori' => 'seminar',
            'metode_pelaksanaan' => 'offline', 'sasaran_peserta' => 'umum', 'tanggal_mulai' => '2026-10-20 01:00:00',
            'tanggal_selesai' => '2026-10-20 09:00:00', 'lokasi_type' => 'unusa_campus', 'lokasi_nama' => 'Auditorium',
            'kuota' => 100, 'biaya' => 0, 'file_panduan_pdf' => 'kegiatan/panduan.pdf', 'status' => KegiatanStatus::Published,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function sertifikat(array $atribut = []): Sertifikat
    {
        static $urut = 0;
        $urut++;

        return Sertifikat::create(array_merge([
            'kegiatan_id' => $this->kegiatan->id, 'nomor_sertifikat' => sprintf('CERT-TES-2026-%04d', $urut),
            'nama_penerima' => 'Siti Aminah', 'asal_institusi' => 'UNUSA', 'jenis' => JenisSertifikat::Peserta,
            'peran' => PeranSertifikat::Peserta, 'status' => StatusSertifikat::Aktif, 'issued_at' => now(),
            'penandatangan_snapshot' => [['nama_lengkap' => 'Dr. Rektor', 'jabatan' => 'Rektor', 'identitas_terformat' => 'NIP: 1975****01']],
        ], $atribut));
    }

    // ---------- Bagian 4: skema, validasi tata letak dan teks ----------

    public function test_kolom_yang_dipakai_templat_dan_berkas_ada_di_skema(): void
    {
        foreach (SertifikatTemplat::KOLOM as $kolom) {
            $this->assertTrue(Schema::hasColumn('sertifikat_templat', $kolom), "sertifikat_templat.{$kolom}");
        }
        foreach (Sertifikat::KOLOM_BERKAS as $kolom) {
            $this->assertTrue(Schema::hasColumn('sertifikat', $kolom), "sertifikat.{$kolom}");
        }
    }

    public function test_tata_letak_menolak_kunci_asing_nilai_di_luar_rentang_warna_dan_font_tidak_sah(): void
    {
        $kasus = [
            'elemen asing' => [['hacker' => ['x' => 1]], 'layout.hacker'],
            'properti asing' => [['nama' => ['onclick' => 'x']], 'layout.nama.onclick'],
            'x di luar rentang' => [['nama' => ['x' => 150]], 'layout.nama.x'],
            'ukuran font terlalu kecil' => [['nama' => ['ukuran_font' => 2]], 'layout.nama.ukuran_font'],
            'warna tidak valid' => [['nama' => ['warna' => 'red; background:url(x)']], 'layout.nama.warna'],
            'font di luar daftar putih' => [['nama' => ['font' => 'Comic Sans']], 'layout.nama.font'],
            'rata asing' => [['nama' => ['rata' => 'justify']], 'layout.nama.rata'],
            'nama disembunyikan' => [['nama' => ['tampil' => false]], 'layout.nama.tampil'],
            'qr disembunyikan' => [['qr' => ['tampil' => false]], 'layout.qr.tampil'],
            'nomor seri disembunyikan' => [['nomor_seri' => ['tampil' => false]], 'layout.nomor_seri.tampil'],
        ];

        foreach ($kasus as $nama => [$layout, $kunciGalat]) {
            try {
                TataLetak::validasi($layout, 'landscape');
                $this->fail("Kasus '{$nama}' seharusnya ditolak.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey($kunciGalat, $e->errors(), $nama);
            }
        }
    }

    public function test_qr_selalu_persegi_18_sampai_60_mm_dan_di_dalam_halaman_dengan_margin_5_mm(): void
    {
        foreach (['landscape', 'portrait'] as $orientasi) {
            [$w, $h] = TataLetak::ukuranHalaman($orientasi);
            $ekstrem = [
                'terlalu kecil' => ['x' => 50, 'y' => 50, 'lebar' => 1, 'tinggi' => 1],
                'terlalu besar' => ['x' => 50, 'y' => 50, 'lebar' => 100, 'tinggi' => 100],
                'melewati tepi kanan bawah' => ['x' => 100, 'y' => 100, 'lebar' => 20, 'tinggi' => 20],
                'melewati tepi kiri atas' => ['x' => 0, 'y' => 0, 'lebar' => 20, 'tinggi' => 5],
            ];
            foreach ($ekstrem as $nama => $kotak) {
                $qr = TataLetak::validasi(['qr' => $kotak], $orientasi)['qr'];
                $sisiX = $qr['lebar'] / 100 * $w;
                $sisiY = $qr['tinggi'] / 100 * $h;
                $this->assertEqualsWithDelta($sisiX, $sisiY, 0.05, "{$orientasi}/{$nama}: harus persegi");
                $this->assertGreaterThanOrEqual(17.99, $sisiX, "{$orientasi}/{$nama}: minimal 18 mm");
                $this->assertLessThanOrEqual(60.01, $sisiX, "{$orientasi}/{$nama}: maksimal 60 mm");
                $this->assertGreaterThanOrEqual(4.99, $qr['x'] / 100 * $w, "{$orientasi}/{$nama}: margin kiri");
                $this->assertGreaterThanOrEqual(4.99, $qr['y'] / 100 * $h, "{$orientasi}/{$nama}: margin atas");
                $this->assertLessThanOrEqual($w - 4.99, $qr['x'] / 100 * $w + $sisiX, "{$orientasi}/{$nama}: margin kanan");
                $this->assertLessThanOrEqual($h - 4.99, $qr['y'] / 100 * $h + $sisiY, "{$orientasi}/{$nama}: margin bawah");
                $this->assertTrue($qr['tampil']);
            }
        }
    }

    public function test_elemen_lain_dijepit_ke_dalam_halaman(): void
    {
        $layout = TataLetak::validasi(['nama' => ['x' => 90, 'y' => 95, 'lebar' => 40, 'tinggi' => 20]], 'landscape')['nama'];

        $this->assertLessThanOrEqual(100.0, $layout['x'] + $layout['lebar']);
        $this->assertLessThanOrEqual(100.0, $layout['y'] + $layout['tinggi']);
    }

    public function test_teks_menolak_penampung_tak_dikenal_dan_menerima_yang_sah(): void
    {
        $this->expectException(ValidationException::class);
        try {
            TeksTemplat::validasi(['peserta' => ['penutup' => 'Untuk {nama} dari {rahasia}']]);
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('teks.peserta.penutup', $e->errors());
            throw $e;
        }
    }

    public function test_teks_sah_diterima_dan_nilai_di_escape_saat_dirender(): void
    {
        $this->assertSame(
            ['peserta' => ['penutup' => 'Kepada {nama} di {kegiatan}']],
            TeksTemplat::validasi(['peserta' => ['penutup' => 'Kepada {nama} di {kegiatan}']]),
        );

        $html = app(BuatPdfSertifikat::class)->html(
            TataLetak::bawaan(), ['judul' => 'SERTIFIKAT', 'pengantar' => 'Kepada', 'penutup' => 'Hadir di {kegiatan}'],
            'landscape', null,
            ['nama' => '<script>alert(1)</script> O\'Brien', 'kegiatan' => '<b>Seminar</b>', 'nomor' => 'CERT-1'],
            'https://contoh.test/verifikasi?code=CERT-1', [],
        );

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('&lt;b&gt;Seminar&lt;/b&gt;', $html);
    }

    public function test_fit_teks_mengecilkan_nama_panjang_sampai_batas_minimum(): void
    {
        $pendek = FitTeks::ukuranFont('Siti Aminah', 220, 20, 26);
        $panjang = FitTeks::ukuranFont(str_repeat('Muhammad Abdurrahman Al-Fatih ', 8), 220, 20, 26);
        $takTerbatas = FitTeks::ukuranFont(str_repeat('X', 600), 60, 8, 26, 7);

        $this->assertSame(26.0, $pendek);
        $this->assertLessThan(26.0, $panjang);
        $this->assertSame(7.0, $takTerbatas, 'Tidak pernah lebih kecil dari batas minimum.');
        $this->assertGreaterThan(1, FitTeks::jumlahBaris(str_repeat('Sangatpanjangtanpaspasi', 10), 50, 12));
    }

    // ---------- Bagian 4: latar dan penyimpanan lewat HTTP ----------

    public function test_simpan_templat_menaikkan_versi_mencatat_audit_dan_reset_mengembalikan_bawaan(): void
    {
        $url = route('admin.kegiatan.sertifikat.templat', $this->kegiatan->id);
        $badan = ['jenis' => null, 'orientasi' => 'landscape', 'layout' => ['nama' => ['x' => 12, 'y' => 40]], 'teks' => ['peserta' => ['judul' => 'SERTIFIKAT KHUSUS']]];

        $this->actingAs($this->admin)->putJson($url, $badan)->assertOk()->assertJsonPath('versi', 1);
        $this->actingAs($this->admin)->putJson($url, $badan)->assertOk()->assertJsonPath('versi', 2);

        $this->assertSame(1, SertifikatTemplat::where('kegiatan_id', $this->kegiatan->id)->count());
        $log = AuditLog::where('action', 'sertifikat_templat_simpan')->latest('id')->first();
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame(2, $log->metadata['versi'] ?? null);

        $this->actingAs($this->admin)->deleteJson(route('admin.kegiatan.sertifikat.templat.reset', $this->kegiatan->id), ['jenis' => null])->assertOk();
        $this->assertSame(0, SertifikatTemplat::where('kegiatan_id', $this->kegiatan->id)->count());
    }

    public function test_simpan_templat_menolak_data_tidak_sah_dengan_pesan_indonesia(): void
    {
        $this->actingAs($this->admin)
            ->putJson(route('admin.kegiatan.sertifikat.templat', $this->kegiatan->id), [
                'orientasi' => 'landscape', 'layout' => ['qr' => ['tampil' => false]], 'teks' => [],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['layout.qr.tampil']);
    }

    public function test_templat_per_jenis_menimpa_tata_letak_dan_teks_hanya_untuk_jenis_itu(): void
    {
        $url = route('admin.kegiatan.sertifikat.templat', $this->kegiatan->id);
        $this->actingAs($this->admin)->putJson($url, ['jenis' => null, 'orientasi' => 'landscape', 'layout' => ['nama' => ['x' => 10, 'y' => 36]], 'teks' => []])->assertOk();
        $this->actingAs($this->admin)->putJson($url, ['jenis' => 'juri', 'orientasi' => 'landscape', 'layout' => ['nama' => ['x' => 20, 'y' => 50]], 'teks' => ['juri' => ['judul' => 'PIAGAM JURI KHUSUS']]])->assertOk();

        $service = app(TemplatSertifikatService::class);
        $juri = $service->untuk($this->kegiatan, JenisSertifikat::Juri);
        $peserta = $service->untuk($this->kegiatan, JenisSertifikat::Peserta);

        $this->assertEquals(20.0, $juri['layout']['nama']['x']);
        $this->assertSame('PIAGAM JURI KHUSUS', $juri['teks']['judul']);
        $this->assertEquals(10.0, $peserta['layout']['nama']['x']);
        $this->assertSame('SERTIFIKAT PESERTA', $peserta['teks']['judul']);
    }

    public function test_unggah_latar_menolak_mime_palsu_svg_terlalu_besar_dan_dimensi_berlebih(): void
    {
        $url = route('admin.kegiatan.sertifikat.latar.unggah', $this->kegiatan->id);

        $palsu = UploadedFile::fake()->createWithContent('latar.png', 'bukan gambar sama sekali');
        $svg = UploadedFile::fake()->createWithContent('latar.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><script>alert(1)</script></svg>');
        $svgBerpuraPuraPng = UploadedFile::fake()->createWithContent('latar.png', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
        $besar = UploadedFile::fake()->create('besar.png', 6000, 'image/png');
        $gd = imagecreatetruecolor(4001, 10);
        ob_start();
        imagepng($gd);
        $lebar = UploadedFile::fake()->createWithContent('lebar.png', (string) ob_get_clean());

        foreach (['mime palsu' => $palsu, 'svg' => $svg, 'svg bernama png' => $svgBerpuraPuraPng, 'terlalu besar' => $besar, 'dimensi berlebih' => $lebar] as $nama => $berkas) {
            $this->actingAs($this->admin)->postJson($url, ['latar' => $berkas])->assertStatus(422, $nama)->assertJsonValidationErrors('latar');
        }
        $this->assertSame(0, SertifikatTemplat::count());
    }

    public function test_unggah_latar_valid_disimpan_privat_dengan_nama_acak_dan_rasio_dicek(): void
    {
        $url = route('admin.kegiatan.sertifikat.latar.unggah', $this->kegiatan->id);

        $res = $this->actingAs($this->admin)->postJson($url, ['latar' => UploadedFile::fake()->image('Latar Asli.png', 297, 210)])->assertOk();
        $res->assertJsonPath('peringatan', null);

        $path = SertifikatTemplat::where('kegiatan_id', $this->kegiatan->id)->value('latar_path');
        $this->assertStringStartsWith('sertifikat/latar/', $path);
        $this->assertStringNotContainsString('Latar', $path);
        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);

        $salah = $this->actingAs($this->admin)->postJson($url, ['latar' => UploadedFile::fake()->image('kotak.png', 100, 100)])->assertOk();
        $this->assertNotNull($salah->json('peringatan'));
        Storage::disk('local')->assertMissing($path);
    }

    public function test_non_super_admin_tidak_dapat_mengelola_templat(): void
    {
        $staf = User::factory()->verifikator()->create();
        $this->actingAs($staf)->putJson(route('admin.kegiatan.sertifikat.templat', $this->kegiatan->id), ['orientasi' => 'landscape', 'layout' => [], 'teks' => []])->assertForbidden();
        $this->actingAs($staf)->postJson(route('admin.kegiatan.sertifikat.pratinjau', $this->kegiatan->id), ['orientasi' => 'landscape', 'layout' => [], 'teks' => []])->assertForbidden();
        $this->actingAs($staf)->postJson(route('admin.kegiatan.sertifikat.berkas', $this->kegiatan->id))->assertForbidden();
    }

    // ---------- Bagian 6: pratinjau, berkas lazy, hash, keamanan ----------

    public function test_pratinjau_menghasilkan_pdf_valid_tanpa_menghabiskan_nomor_seri(): void
    {
        $sebelum = Sertifikat::count();

        $res = $this->actingAs($this->admin)->post(route('admin.kegiatan.sertifikat.pratinjau', $this->kegiatan->id), [
            'orientasi' => 'portrait', 'layout' => ['nama' => ['x' => 15, 'y' => 30]], 'teks' => ['peserta' => ['judul' => 'CONTOH JUDUL']],
        ]);

        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $res->getContent());
        $this->assertSame($sebelum, Sertifikat::count());
    }

    public function test_penerbitan_tidak_membuat_berkas_dan_verifikasi_tanpa_berkas_tetap_valid(): void
    {
        $this->actingAs($this->admin)->post(route('admin.certificates.manual'), [
            'kegiatan_id' => $this->kegiatan->id, 'nama_penerima' => 'Tamu Undangan', 'asal_institusi' => 'ITB', 'jenis' => 'pembicara',
        ])->assertRedirect();
        $sertifikat = Sertifikat::where('nama_penerima', 'Tamu Undangan')->firstOrFail();

        $this->assertNull($sertifikat->file_pdf_path);
        $this->assertNull($sertifikat->document_hash);
        $this->assertSame([], Storage::disk('local')->allFiles('sertifikat/berkas'));

        $halaman = $this->get(route('verifikasi.sertifikat', ['code' => $sertifikat->nomor_sertifikat]))->assertOk();
        $halaman->assertSee('Valid')->assertDontSee('Tidak Valid');
    }

    public function test_validity_tiga_kasus_tanpa_berkas_berkas_utuh_dan_berkas_diubah(): void
    {
        $sertifikat = $this->sertifikat();
        $kode = $sertifikat->nomor_sertifikat;

        $this->get(route('verifikasi.sertifikat', ['code' => $kode]))->assertOk()->assertSee('Valid')->assertDontSee('Tidak Valid');

        $dibuat = app(BerkasSertifikatService::class)->pastikan($sertifikat);
        $this->assertTrue($dibuat->adaBerkas());
        $this->get(route('verifikasi.sertifikat', ['code' => $kode]))->assertOk()->assertSee('Valid')->assertDontSee('Tidak Valid');

        Storage::disk('local')->put($dibuat->file_pdf_path, '%PDF-1.4 DIUBAH ORANG LAIN');
        $this->get(route('verifikasi.sertifikat', ['code' => $kode]))->assertOk()->assertSee('Tidak Valid');
    }

    public function test_unduh_pertama_membuat_berkas_privat_dengan_hash_dan_unduh_kedua_tidak_membuat_ulang(): void
    {
        $pemilik = User::factory()->mahasiswaKipk()->create();
        $sertifikat = $this->sertifikat(['user_id' => $pemilik->id, 'penerima_key' => 'user:'.$pemilik->id]);

        $this->actingAs($pemilik)->get(route('sertifikat.download', $sertifikat->id))->assertOk();

        $satu = $sertifikat->fresh();
        $this->assertSame('local', $satu->file_disk);
        $this->assertMatchesRegularExpression('#^sertifikat/berkas/[A-Za-z0-9]{40}\.pdf$#', $satu->file_pdf_path);
        $isi = Storage::disk('local')->get($satu->file_pdf_path);
        $this->assertStringStartsWith('%PDF', $isi);
        $this->assertSame(hash('sha256', $isi), $satu->document_hash);
        Storage::disk('public')->assertMissing($satu->file_pdf_path);

        $this->actingAs($pemilik)->get(route('sertifikat.download', $sertifikat->id))->assertOk();
        $dua = $sertifikat->fresh();
        $this->assertSame($satu->file_pdf_path, $dua->file_pdf_path);
        $this->assertSame($satu->document_hash, $dua->document_hash);
        $this->assertCount(1, Storage::disk('local')->allFiles('sertifikat/berkas'));
    }

    public function test_dua_permintaan_serentak_tidak_membuat_dua_berkas(): void
    {
        $sertifikat = $this->sertifikat();
        $salinanA = Sertifikat::find($sertifikat->id);
        $salinanB = Sertifikat::find($sertifikat->id); // keadaan basi: belum tahu berkas sudah dibuat

        $service = app(BerkasSertifikatService::class);
        $a = $service->pastikan($salinanA);
        $b = $service->pastikan($salinanB);

        $this->assertSame($a->file_pdf_path, $b->file_pdf_path);
        $this->assertSame($a->document_hash, $b->document_hash);
        $this->assertCount(1, Storage::disk('local')->allFiles('sertifikat/berkas'));
    }

    public function test_unduhan_dibatasi_pemilik_dan_super_admin(): void
    {
        $pemilik = User::factory()->mahasiswaKipk()->create();
        $lain = User::factory()->mahasiswaKipk()->create();
        $sertifikat = $this->sertifikat(['user_id' => $pemilik->id, 'penerima_key' => 'user:'.$pemilik->id]);

        $this->get(route('sertifikat.download', $sertifikat->id))->assertUnauthorized();
        $this->actingAs($lain)->get(route('sertifikat.download', $sertifikat->id))->assertForbidden();
        $this->actingAs($this->admin)->get(route('sertifikat.download', $sertifikat->id))->assertOk();
        $this->assertCount(1, Storage::disk('local')->allFiles('sertifikat/berkas'), 'Super admin yang mengunduh membuat berkas.');
    }

    public function test_sertifikat_lama_dan_berkasnya_tidak_disentuh(): void
    {
        $isiLama = '%PDF-1.4 sertifikat lama';
        Storage::disk('public')->put('certificates/1/CERT-ASYK-2026-8280.pdf', $isiLama);
        $lama = $this->sertifikat([
            'nomor_sertifikat' => 'CERT-ASYK-2026-8280', 'file_pdf_path' => 'certificates/1/CERT-ASYK-2026-8280.pdf',
            'file_disk' => 'public', 'document_hash' => hash('sha256', $isiLama),
        ]);

        app(BerkasSertifikatService::class)->pastikan($lama);
        $this->actingAs($this->admin)->postJson(route('admin.kegiatan.sertifikat.berkas', $this->kegiatan->id))->assertOk();

        $segar = $lama->fresh();
        $this->assertSame('certificates/1/CERT-ASYK-2026-8280.pdf', $segar->file_pdf_path);
        $this->assertSame(hash('sha256', $isiLama), $segar->document_hash);
        $this->assertSame($isiLama, Storage::disk('public')->get('certificates/1/CERT-ASYK-2026-8280.pdf'));
        $this->assertSame([], Storage::disk('local')->allFiles('sertifikat/berkas'));
    }

    public function test_batch_memproses_20_per_permintaan_dan_kegagalan_satu_tidak_menghentikan_yang_lain(): void
    {
        foreach (range(1, 25) as $i) {
            $this->sertifikat(['nama_penerima' => "Peserta {$i}"]);
        }
        $url = route('admin.kegiatan.sertifikat.berkas', $this->kegiatan->id);

        $pertama = $this->actingAs($this->admin)->postJson($url)->assertOk()->json();
        $this->assertSame(20, $pertama['diproses']);
        $this->assertSame(5, $pertama['sisa']);

        $kedua = $this->actingAs($this->admin)->postJson($url, ['kursor' => $pertama['kursor']])->assertOk()->json();
        $this->assertSame(5, $kedua['diproses']);
        $this->assertSame(0, $kedua['sisa']);
        $this->assertSame(25, Sertifikat::whereNotNull('file_pdf_path')->count());
    }

    public function test_kegagalan_satu_berkas_ditandai_dan_yang_lain_tetap_dibuat(): void
    {
        // Templat penimpa jenis juri rusak (tanpa kunci x), jadi hanya sertifikat juri yang gagal.
        SertifikatTemplat::create([
            'kegiatan_id' => $this->kegiatan->id, 'jenis' => 'juri', 'orientasi' => 'landscape',
            'layout' => ['nama' => ['tampil' => true]], 'teks' => [], 'versi' => 1,
        ]);
        $baik = $this->sertifikat(['nama_penerima' => 'Peserta Baik']);
        $rusak = $this->sertifikat(['nama_penerima' => 'Juri Rusak', 'jenis' => JenisSertifikat::Juri]);
        $baik2 = $this->sertifikat(['nama_penerima' => 'Peserta Baik Dua']);

        $hasil = app(BerkasSertifikatService::class)->batch($this->kegiatan);

        $this->assertSame(3, $hasil['diproses']);
        $this->assertSame(2, $hasil['berhasil']);
        $this->assertSame(1, $hasil['gagal']);
        $this->assertTrue($baik->fresh()->adaBerkas());
        $this->assertTrue($baik2->fresh()->adaBerkas());
        $this->assertSame('gagal', $rusak->fresh()->file_status);
        $this->assertNotEmpty($rusak->fresh()->file_alasan_gagal);
        $this->assertNull($rusak->fresh()->file_pdf_path);
    }

    public function test_buat_ulang_berkas_hanya_eksplisit_dengan_alasan_dan_mencatat_hash_lama_dan_baru(): void
    {
        $sertifikat = app(BerkasSertifikatService::class)->pastikan($this->sertifikat());
        $hashLama = $sertifikat->document_hash;

        SertifikatTemplat::create([
            'kegiatan_id' => $this->kegiatan->id, 'jenis' => null, 'orientasi' => 'landscape',
            'layout' => TataLetak::validasi(['nama' => ['x' => 20, 'y' => 40]], 'landscape'), 'teks' => ['peserta' => ['judul' => 'JUDUL BARU']], 'versi' => 1,
        ]);
        $this->assertSame($hashLama, app(BerkasSertifikatService::class)->pastikan($sertifikat->fresh())->document_hash, 'Berkas yang ada tidak dibuat ulang otomatis.');

        $url = route('admin.certificates.rebuild-file', $sertifikat->id);
        $this->actingAs($this->admin)->postJson($url, [])->assertStatus(422)->assertJsonValidationErrors('alasan');
        $this->actingAs($this->admin)->postJson($url, ['alasan' => 'Perbaikan tata letak nama'])->assertOk()->assertJsonPath('berhasil', true);

        $riwayat = $sertifikat->fresh()->riwayatPerubahan()->first();
        $this->assertSame('document_hash', $riwayat->kolom_diubah);
        $this->assertSame($hashLama, $riwayat->nilai_lama);
        $this->assertSame($sertifikat->fresh()->document_hash, $riwayat->nilai_baru);
        $this->assertNotSame($hashLama, $riwayat->nilai_baru);
    }

    public function test_qr_memuat_tautan_verifikasi_absolut_dan_nama_panjang_atau_khusus_tetap_menghasilkan_pdf(): void
    {
        $sertifikat = $this->sertifikat(['nomor_sertifikat' => 'CERT-QR-2026-0001']);
        $this->assertSame(rtrim(config('app.url'), '/').'/verifikasi?code=CERT-QR-2026-0001', $sertifikat->verification_url);
        $this->assertStringStartsWith('http', $sertifikat->verification_url);

        $pembuat = app(BuatPdfSertifikat::class);
        $html = $pembuat->html(TataLetak::bawaan(), ['judul' => 'J', 'pengantar' => 'P', 'penutup' => ''], 'landscape', null,
            ['nama' => 'X', 'nomor' => 'CERT-QR-2026-0001'], $sertifikat->verification_url, []);
        $this->assertStringContainsString($pembuat->qrDataUri($sertifikat->verification_url), $html);
        $this->assertNotSame($pembuat->qrDataUri($sertifikat->verification_url), $pembuat->qrDataUri('https://lain.test/'));

        foreach (['Dr. H. Muhammad Fauzan Al-Ghifari, S.Kom., M.T. bin Abdurrahman', "D'Angelo Núñez-Åström", str_repeat('Abdurrahman ', 20), '李小龍'] as $nama) {
            $berkas = app(BerkasSertifikatService::class)->pastikan($this->sertifikat(['nama_penerima' => $nama]));
            $this->assertTrue($berkas->adaBerkas(), "Berkas untuk nama '{$nama}' gagal dibuat: ".$berkas->file_alasan_gagal);
            $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($berkas->file_pdf_path));
        }
    }
}
