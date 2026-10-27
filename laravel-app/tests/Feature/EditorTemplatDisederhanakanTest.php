<?php

namespace Tests\Feature;

use App\Enums\KegiatanStatus;
use App\Models\Kegiatan;
use App\Models\SertifikatTemplat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Galat teks yang jujur di server dan orientasi otomatis dari gambar latar. */
class EditorTemplatDisederhanakanTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Kegiatan $kegiatan;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->admin = User::factory()->superAdmin()->create();
        $this->kegiatan = Kegiatan::create([
            'created_by' => $this->admin->id, 'nama_kegiatan' => 'Lomba Essay', 'kategori' => 'lomba', 'metode_pelaksanaan' => 'offline',
            'sasaran_peserta' => 'umum', 'tanggal_mulai' => now()->subDays(3), 'tanggal_selesai' => now()->subDays(2), 'lokasi_type' => 'unusa_campus',
            'lokasi_nama' => 'Auditorium', 'kuota' => 10, 'biaya' => 0, 'file_panduan_pdf' => 'kegiatan/panduan.pdf', 'status' => KegiatanStatus::Published,
        ]);
    }

    private function simpan(array $teks): TestResponse
    {
        return $this->actingAs($this->admin)->putJson(route('admin.kegiatan.sertifikat.templat', $this->kegiatan->id), [
            'jenis' => null, 'orientasi' => 'landscape', 'layout' => ['nama' => ['x' => 10, 'y' => 36]], 'teks' => $teks,
        ]);
    }

    private function propsHalaman(): array
    {
        return $this->actingAs($this->admin)->get(route('admin.kegiatan.sertifikat', $this->kegiatan->id))->assertOk()->original->getData()['page']['props'];
    }

    public function test_teks_kosong_atau_null_berarti_bawaan_bukan_galat_maksimal_karakter(): void
    {
        // Laravel mengubah '' menjadi null pada JSON; sebelumnya null dilaporkan sebagai "maksimal N karakter".
        $this->simpan(['peserta' => ['judul' => '', 'pengantar' => null, 'penutup' => '   ']])->assertOk();

        $teks = $this->propsHalaman()['templat']['teks']['peserta'];
        $this->assertSame('SERTIFIKAT PESERTA', $teks['judul']);
        $this->assertSame('Diberikan kepada', $teks['pengantar']);
        $this->assertStringContainsString('{kegiatan}', $teks['penutup']);
    }

    public function test_teks_terlalu_panjang_ditolak_dengan_pesan_jumlah_karakter(): void
    {
        $res = $this->simpan(['peserta' => ['judul' => str_repeat('J', 162), 'penutup' => str_repeat('K', 601)]])->assertStatus(422);

        $this->assertSame('Judul terlalu panjang (162 dari 150 karakter)', $res->json('errors')['teks.peserta.judul'][0]);
        $this->assertSame('Kalimat penutup terlalu panjang (601 dari 600 karakter)', $res->json('errors')['teks.peserta.penutup'][0]);
        $this->simpan(['peserta' => ['judul' => str_repeat('J', 150)]])->assertOk();
    }

    public function test_teks_bukan_string_dilaporkan_sebagai_bukan_teks_bukan_terlalu_panjang(): void
    {
        $res = $this->simpan(['peserta' => ['judul' => ['bukan', 'teks']]])->assertStatus(422);

        $pesan = $res->json('errors')['teks.peserta.judul'][0];
        $this->assertStringContainsString('harus berupa teks', $pesan);
        $this->assertStringNotContainsString('terlalu panjang', $pesan);
    }

    public function test_editor_menerima_batas_teks_dari_server_sebagai_satu_sumber(): void
    {
        $this->assertSame(['judul' => 150, 'pengantar' => 200, 'penutup' => 600], $this->propsHalaman()['templat']['aturan']['batas_teks']);
    }

    public function test_orientasi_ditentukan_otomatis_dari_gambar_latar_dan_kembali_lanskap_tanpa_latar(): void
    {
        $url = route('admin.kegiatan.sertifikat.latar.unggah', $this->kegiatan->id);

        $this->assertSame('landscape', $this->propsHalaman()['templat']['orientasi']);

        $this->actingAs($this->admin)->postJson($url, ['latar' => UploadedFile::fake()->image('potret.png', 210, 297)])->assertOk()
            ->assertJsonPath('templat.orientasi', 'portrait')->assertJsonPath('peringatan', null);
        $this->assertSame('portrait', SertifikatTemplat::where('kegiatan_id', $this->kegiatan->id)->value('orientasi'));

        $this->actingAs($this->admin)->postJson($url, ['latar' => UploadedFile::fake()->image('lanskap.png', 297, 210)])->assertOk()
            ->assertJsonPath('templat.orientasi', 'landscape');

        $this->actingAs($this->admin)->postJson($url, ['latar' => UploadedFile::fake()->image('potret.png', 210, 297)])->assertOk();
        $this->actingAs($this->admin)->deleteJson(route('admin.kegiatan.sertifikat.latar.hapus', $this->kegiatan->id))->assertOk()
            ->assertJsonPath('templat.orientasi', 'landscape');
    }

    public function test_tata_letak_tersimpan_tetap_valid_dan_qr_tetap_dalam_batas_setelah_orientasi_berubah(): void
    {
        $url = route('admin.kegiatan.sertifikat.latar.unggah', $this->kegiatan->id);
        $this->actingAs($this->admin)->postJson($url, ['latar' => UploadedFile::fake()->image('potret.png', 210, 297)])->assertOk();

        $qr = SertifikatTemplat::where('kegiatan_id', $this->kegiatan->id)->first()->layout['qr'];
        $sisi = $qr['lebar'] / 100 * 210;
        $this->assertGreaterThanOrEqual(17.99, $sisi);
        $this->assertLessThanOrEqual(60.01, $sisi);
        $this->assertGreaterThanOrEqual(4.99, $qr['x'] / 100 * 210);
        $this->assertLessThanOrEqual(210 - 4.99, $qr['x'] / 100 * 210 + $sisi);
        $this->assertLessThanOrEqual(297 - 4.99, $qr['y'] / 100 * 297 + $qr['tinggi'] / 100 * 297);
    }

    public function test_halaman_sertifikat_kegiatan_tidak_lagi_mengirim_daftar_kegiatan_untuk_salin(): void
    {
        $this->assertArrayNotHasKey('kegiatanLain', $this->propsHalaman());
    }
}
