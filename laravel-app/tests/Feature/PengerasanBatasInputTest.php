<?php

namespace Tests\Feature;

use App\Enums\JenisSertifikat;
use App\Enums\KegiatanStatus;
use App\Enums\PeranSertifikat;
use App\Enums\StatusSertifikat;
use App\Models\AuditLog;
use App\Models\Kegiatan;
use App\Models\PenerimaKipk;
use App\Models\Sertifikat;
use App\Models\User;
use App\Services\Impor\ImporPenerimaKipk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Batas input form dibandingkan kolom basis data. SQLite menelan nilai berlebih, PostgreSQL menolaknya dengan 500;
 * tes ini memastikan batas tepat lolos dan batas+1 menghasilkan galat validasi, bukan 500.
 */
class PengerasanBatasInputTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->admin = User::factory()->superAdmin()->create();
    }

    private function payloadKegiatan(array $tambahan = []): array
    {
        return array_merge([
            'nama_kegiatan' => 'Seminar Batas Input', 'kategori' => 'seminar', 'metode_pelaksanaan' => 'offline', 'sasaran_peserta' => 'umum',
            'tanggal_mulai' => '2026-12-25 09:00', 'tanggal_selesai' => '2026-12-25 11:00', 'lokasi_type' => 'unusa_campus',
            'lokasi_kampus' => 'Kampus B (Jemursari)', 'lokasi_gedung' => 'Tower UNUSA', 'lokasi_ruangan' => 'Auditorium Lt. 9',
            'lokasi_nama' => 'Tower UNUSA - Kampus B', 'biaya' => 0, 'kuota' => 100, 'aksi_submit' => 'draft',
        ], $tambahan);
    }

    private function kirimKegiatan(array $tambahan = []): TestResponse
    {
        return $this->actingAs($this->admin)->post(route('admin.kegiatan.store'), $this->payloadKegiatan($tambahan));
    }

    // ---------- Form kegiatan: biaya, kuota, poin XP, jumlah baris denah ----------

    public function test_biaya_kegiatan_maksimal_99_999_999(): void
    {
        $this->kirimKegiatan(['nama_kegiatan' => 'Biaya Batas', 'biaya' => 99999999])->assertSessionHasNoErrors();
        $this->assertSame(1, Kegiatan::where('nama_kegiatan', 'Biaya Batas')->count());

        $this->kirimKegiatan(['nama_kegiatan' => 'Biaya Lewat', 'biaya' => 100000000])->assertSessionHasErrors(['biaya' => 'Biaya maksimal 99.999.999.']);
        $this->assertSame(0, Kegiatan::where('nama_kegiatan', 'Biaya Lewat')->count());
    }

    public function test_kuota_kegiatan_maksimal_100_000(): void
    {
        $this->kirimKegiatan(['nama_kegiatan' => 'Kuota Batas', 'kuota' => 100000])->assertSessionHasNoErrors();
        $this->assertSame(1, Kegiatan::where('nama_kegiatan', 'Kuota Batas')->count());

        $this->kirimKegiatan(['nama_kegiatan' => 'Kuota Lewat', 'kuota' => 100001])->assertSessionHasErrors('kuota');
        $this->kirimKegiatan(['nama_kegiatan' => 'Kuota Raksasa', 'kuota' => 99999999999])->assertSessionHasErrors('kuota');
        $this->assertSame(0, Kegiatan::whereIn('nama_kegiatan', ['Kuota Lewat', 'Kuota Raksasa'])->count());
    }

    public function test_poin_xp_kegiatan_maksimal_10_000(): void
    {
        $this->kirimKegiatan(['nama_kegiatan' => 'XP Batas', 'poin_xp' => 10000])->assertSessionHasNoErrors();
        $this->assertSame(1, Kegiatan::where('nama_kegiatan', 'XP Batas')->count());

        $this->kirimKegiatan(['nama_kegiatan' => 'XP Lewat', 'poin_xp' => 10001])->assertSessionHasErrors('poin_xp');
        $this->kirimKegiatan(['nama_kegiatan' => 'XP Raksasa', 'poin_xp' => 99999999999])->assertSessionHasErrors('poin_xp');
        $this->assertSame(0, Kegiatan::whereIn('nama_kegiatan', ['XP Lewat', 'XP Raksasa'])->count());
    }

    public function test_jumlah_baris_denah_pada_form_kegiatan_maksimal_30_seperti_pengaturan_ruangan(): void
    {
        $baris = fn (int $n) => array_map(fn ($i) => 'R'.$i, range(1, $n));

        $this->kirimKegiatan(['nama_kegiatan' => 'Denah 30', 'seat_layout_config' => ['rows' => $baris(30), 'cols_per_row' => 2]])->assertSessionHasNoErrors();
        $this->kirimKegiatan(['nama_kegiatan' => 'Denah 31', 'seat_layout_config' => ['rows' => $baris(31), 'cols_per_row' => 2]])->assertSessionHasErrors('seat_layout_config.rows');
        $this->assertSame(0, Kegiatan::where('nama_kegiatan', 'Denah 31')->count());
    }

    public function test_nama_kegiatan_255_karakter_tidak_membuat_catatan_audit_melampaui_kolom(): void
    {
        $nama = str_repeat('K', 255);
        $this->kirimKegiatan(['nama_kegiatan' => $nama])->assertSessionHasNoErrors();

        $log = AuditLog::where('action', 'like', '%kegiatan%')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertLessThanOrEqual(255, mb_strlen((string) $log->description));
    }

    // ---------- Pengaturan ruangan: kapasitas ----------

    public function test_kapasitas_kursi_pengaturan_ruangan_maksimal_100_000(): void
    {
        $this->kirimKegiatan(['nama_kegiatan' => 'Ruangan Batas', 'kuota' => 10])->assertSessionHasNoErrors();
        $kegiatan = Kegiatan::where('nama_kegiatan', 'Ruangan Batas')->firstOrFail();
        $url = route('admin.kegiatan.venue-config', $kegiatan->id);
        $dasar = ['layout_changed' => false, 'is_seat_booking_active' => false];

        $this->actingAs($this->admin)->putJson($url, $dasar + ['total_seats' => 100001])->assertStatus(422)->assertJsonValidationErrors('total_seats');
        $this->actingAs($this->admin)->putJson($url, $dasar + ['total_seats' => 99999999999])->assertStatus(422)->assertJsonValidationErrors('total_seats');
        $this->assertNotSame(500, $this->actingAs($this->admin)->putJson($url, $dasar + ['total_seats' => 100000])->getStatusCode());
    }

    // ---------- Berkas KIP-K ----------

    public function test_kategori_berkas_kipk_maksimal_100_karakter(): void
    {
        $mahasiswa = User::factory()->mahasiswaKipk()->create();
        $kirim = fn (string $kategori) => $this->actingAs($mahasiswa)->post(route('dashboard.berkas.upload'), [
            'category' => $kategori, 'tahap' => 1, 'title' => 'Kartu KIP-K', 'document_file' => UploadedFile::fake()->create('kartu.pdf', 10, 'application/pdf'),
        ]);

        $kirim(str_repeat('K', 100))->assertSessionHasNoErrors();
        $kirim(str_repeat('K', 101))->assertSessionHasErrors('category');
    }

    // ---------- Sertifikat: email penerima ----------

    private function emailPanjang(int $panjang): string
    {
        // Bagian lokal 64 karakter, label domain 60 karakter (validator RFC menolak label mendekati 63), label terakhir menyesuaikan.
        $domainAwal = str_repeat(str_repeat('b', 60).'.', 3);

        return str_repeat('a', 64).'@'.$domainAwal.str_repeat('c', $panjang - 64 - 1 - strlen($domainAwal));
    }

    public function test_email_penerima_sertifikat_manual_maksimal_255_karakter(): void
    {
        $kegiatan = Kegiatan::create(['created_by' => $this->admin->id, 'status' => KegiatanStatus::Published, 'tanggal_mulai' => now()->subDays(3), 'tanggal_selesai' => now()->subDays(2)] + [
            'nama_kegiatan' => 'Seminar Email', 'kategori' => 'seminar', 'metode_pelaksanaan' => 'offline', 'sasaran_peserta' => 'umum', 'lokasi_type' => 'unusa_campus',
            'lokasi_nama' => 'A', 'kuota' => 10, 'biaya' => 0, 'file_panduan_pdf' => 'x.pdf',
        ]);
        $kirim = fn (string $nama, string $email) => $this->actingAs($this->admin)->post(route('admin.certificates.manual'), [
            'kegiatan_id' => $kegiatan->id, 'nama_penerima' => $nama, 'asal_institusi' => 'ITB', 'jenis' => 'pembicara', 'email_penerima' => $email,
        ]);

        $this->assertSame(255, strlen($this->emailPanjang(255)));
        $kirim('Penerima Batas', $this->emailPanjang(255))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(1, Sertifikat::where('nama_penerima', 'Penerima Batas')->count());

        $kirim('Penerima Lewat', $this->emailPanjang(256))->assertSessionHasErrors('email_penerima');
        $this->assertSame(0, Sertifikat::where('nama_penerima', 'Penerima Lewat')->count());
    }

    public function test_alasan_pencabutan_500_karakter_tidak_menjadi_500_pada_catatan_audit(): void
    {
        $kegiatan = Kegiatan::create(['created_by' => $this->admin->id, 'status' => KegiatanStatus::Published, 'tanggal_mulai' => now()->subDays(3), 'tanggal_selesai' => now()->subDays(2)] + [
            'nama_kegiatan' => 'Seminar Cabut', 'kategori' => 'seminar', 'metode_pelaksanaan' => 'offline', 'sasaran_peserta' => 'umum', 'lokasi_type' => 'unusa_campus',
            'lokasi_nama' => 'A', 'kuota' => 10, 'biaya' => 0, 'file_panduan_pdf' => 'x.pdf',
        ]);
        $sertifikat = Sertifikat::create([
            'kegiatan_id' => $kegiatan->id, 'nomor_sertifikat' => 'CERT-BATAS-0001', 'nama_penerima' => 'Penerima', 'asal_institusi' => 'ITB',
            'penerima_key' => Sertifikat::hitungPenerimaKey(null, 'Penerima', 'ITB'), 'jenis' => JenisSertifikat::Peserta,
            'peran_sertifikat' => PeranSertifikat::Peserta, 'status' => StatusSertifikat::Aktif, 'issued_at' => now(),
        ]);

        $this->actingAs($this->admin)->post(route('admin.certificates.revoke', $sertifikat->id), ['alasan' => str_repeat('A', 500)])
            ->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(StatusSertifikat::Dicabut, $sertifikat->fresh()->status);
        $this->assertLessThanOrEqual(255, mb_strlen((string) AuditLog::where('action', 'like', '%sertifikat%')->latest('id')->value('description')));
    }

    // ---------- Catatan audit: satu titik penjaga kolom description varchar(255) ----------

    public function test_catatan_audit_memotong_deskripsi_panjang_ke_255_karakter(): void
    {
        $log = AuditLog::record($this->admin->id, 'uji_batas', str_repeat('D', 600));

        $this->assertSame(255, mb_strlen((string) $log->fresh()->description));
        $this->assertNull(AuditLog::record($this->admin->id, 'uji_batas_null', null)->fresh()->description);
        $this->assertSame('Singkat', AuditLog::record($this->admin->id, 'uji_batas_pendek', 'Singkat')->fresh()->description);
    }

    // ---------- Presensi publik ----------

    public function test_presensi_publik_menolak_id_kegiatan_bukan_angka_dengan_galat_validasi(): void
    {
        $res = $this->post('/absensi', [
            'activity_id' => 'abc', 'student_name' => 'Peserta', 'student_nim' => '123', 'university' => 'UNUSA',
            'proof_image' => UploadedFile::fake()->image('bukti.jpg'),
        ]);

        $res->assertSessionHasErrors('activity_id');
    }

    // ---------- Impor daftar penerima KIP-K ----------

    private function csvKipk(string $nim, string $nama = 'Siti', string $prodi = 'Gizi', string $status = 'Ditetapkan'): string
    {
        return "nomor_pendaftaran,nama_lengkap,nim,program_studi,angkatan,status_penetapan\n12345678901234,{$nama},{$nim},{$prodi},2024,{$status}\n";
    }

    public function test_impor_penerima_kipk_menandai_baris_dengan_nilai_melebihi_kolom_sebagai_galat(): void
    {
        $impor = app(ImporPenerimaKipk::class);
        $status = fn (string $csv) => $impor->periksa($csv)['baris'][0]['status'];

        $this->assertSame('baru', $status($this->csvKipk(str_repeat('1', 30))));
        $this->assertSame('galat', $status($this->csvKipk(str_repeat('1', 31))));
        $this->assertSame('baru', $status($this->csvKipk('2241001', str_repeat('N', 255))));
        $this->assertSame('galat', $status($this->csvKipk('2241001', str_repeat('N', 256))));
        $this->assertSame('baru', $status($this->csvKipk('2241001', 'Siti', str_repeat('P', 255))));
        $this->assertSame('galat', $status($this->csvKipk('2241001', 'Siti', str_repeat('P', 256))));
        $this->assertSame('baru', $status($this->csvKipk('2241001', 'Siti', 'Gizi', str_repeat('S', 50))));
        $this->assertSame('galat', $status($this->csvKipk('2241001', 'Siti', 'Gizi', str_repeat('S', 51))));
    }

    public function test_impor_penerima_kipk_menyimpan_nilai_terpanjang_yang_lolos(): void
    {
        app(ImporPenerimaKipk::class)->simpan($this->csvKipk(str_repeat('1', 30), str_repeat('N', 255), str_repeat('P', 255), str_repeat('S', 50)), 'daftar.csv', $this->admin);

        $penerima = PenerimaKipk::firstOrFail();
        $this->assertSame(30, strlen($penerima->nim));
        $this->assertSame(255, mb_strlen($penerima->nama_lengkap));
    }

    public function test_nama_berkas_impor_maksimal_100_karakter_agar_kolom_sumber_cukup(): void
    {
        $berkas = fn (int $panjang) => UploadedFile::fake()->createWithContent(str_repeat('a', $panjang - 4).'.csv', $this->csvKipk('2241001'));

        $this->actingAs($this->admin)->post(route('admin.penerima-kipk.unggah'), ['file' => $berkas(100)])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('admin.penerima-kipk.unggah'), ['file' => $berkas(101)])->assertSessionHasErrors('file');
        $this->actingAs($this->admin)->post(route('admin.mahasiswa-unusa.unggah'), ['file' => $berkas(101)])->assertSessionHasErrors('file');
    }
}
