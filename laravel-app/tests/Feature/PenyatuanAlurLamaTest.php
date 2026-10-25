<?php

namespace Tests\Feature;

use App\Enums\KegiatanStatus;
use App\Enums\PeranSertifikat;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Certificate;
use App\Models\Kegiatan;
use App\Models\KegiatanPresensi;
use App\Models\Sertifikat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PenyatuanAlurLamaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /**
     * 1. Route publik /absensi memakai tabel kegiatan dan menyimpan ke kegiatan_presensi.
     */
    public function test_public_absensi_uses_kegiatan_and_stores_to_kegiatan_presensi(): void
    {
        $kegiatan = Kegiatan::create([
            'nama_kegiatan' => 'Workshop UI/UX Modern 2026',
            'deskripsi' => 'Belajar desain antarmuka modern.',
            'kategori' => 'workshop',
            'metode_pelaksanaan' => 'offline',
            'sasaran_peserta' => 'umum',
            'tanggal_mulai' => now()->addDay(),
            'tanggal_selesai' => now()->addDay()->addHours(3),
            'lokasi_type' => 'unusa_campus',
            'lokasi_nama' => 'Auditorium Tower Unusa',
            'lokasi_detail' => 'Kampus B UNUSA',
            'file_panduan_pdf' => 'kegiatan/panduan.pdf',
            'biaya' => 0,
            'kuota' => 80,
            'status' => KegiatanStatus::Published,
        ]);

        // GET /absensi melihat nama kegiatan
        $response = $this->get(route('absensi.public'));
        $response->assertOk()
            ->assertSee('Workshop UI/UX Modern 2026');

        // POST /absensi
        $image = UploadedFile::fake()->image('selfie_presensi.jpg', 600, 600);
        $postResponse = $this->post(route('absensi.public.store'), [
            'activity_id' => $kegiatan->id,
            'student_name' => 'Budi Santoso',
            'student_nim' => '2240021999',
            'university' => 'Universitas Nahdlatul Ulama Surabaya',
            'student_email' => 'budi@unusa.ac.id',
            'student_phone' => '081234567890',
            'proof_image' => $image,
            'location_name' => 'Auditorium Tower Lt 9',
        ]);

        $postResponse->assertSessionHas('success');

        // Memastikan tersimpan di kegiatan_presensi (alur baru)
        $presensi = KegiatanPresensi::where('kegiatan_id', $kegiatan->id)
            ->where('nim', '2240021999')
            ->first();

        $this->assertNotNull($presensi);
        $this->assertEquals('Budi Santoso', $presensi->nama_lengkap);
        $this->assertEquals('budi@unusa.ac.id', $presensi->email);
        Storage::disk('public')->assertExists($presensi->signature_path);
    }

    /**
     * 2. Route publik /verifikasi memverifikasi sertifikat alur baru dan fallback sertifikat lama.
     */
    public function test_verifikasi_verifies_sertifikat_baru_and_legacy_certificates(): void
    {
        $kegiatan = Kegiatan::create([
            'nama_kegiatan' => 'Seminar Kecerdasan Buatan',
            'deskripsi' => 'Seminar AI terkini.',
            'kategori' => 'seminar',
            'metode_pelaksanaan' => 'online',
            'sasaran_peserta' => 'umum',
            'tanggal_mulai' => now()->subDays(5),
            'lokasi_type' => 'online',
            'lokasi_nama' => 'Zoom Cloud Meetings',
            'file_panduan_pdf' => 'kegiatan/panduan.pdf',
            'biaya' => 0,
            'kuota' => 100,
            'status' => KegiatanStatus::Completed,
        ]);

        // A. Sertifikat Baru
        $sertifikatBaru = Sertifikat::create([
            'nomor_sertifikat' => 'CERT-AI-2026-X1Y2Z3',
            'kegiatan_id' => $kegiatan->id,
            'nama_penerima' => 'Ahmad Rian',
            'peran_sertifikat' => PeranSertifikat::Peserta,
            'file_pdf_path' => 'certificates/sample.pdf',
            'document_hash' => hash('sha256', 'dummy_hash_rian'),
            'is_valid' => true,
            'issued_at' => now(),
        ]);

        $responseBaru = $this->get('/verifikasi?code=CERT-AI-2026-X1Y2Z3');
        $responseBaru->assertOk()
            ->assertSee('CERT-AI-2026-X1Y2Z3')
            ->assertSee('Ahmad Rian')
            ->assertSee('VALID');

        // B. Sertifikat Lama (Legacy) di tabel certificates
        $certLama = Certificate::create([
            'code' => 'CERT-LEGACY-2024-9988',
            'activity_id' => null,
            'user_name' => 'Siti Aminah',
            'activity_title' => 'Workshop Masa Lalu 2024',
            'status' => 'Berlaku',
            'issued_date' => now()->subYears(2),
        ]);

        $responseLama = $this->get('/verifikasi?code=CERT-LEGACY-2024-9988');
        $responseLama->assertOk()
            ->assertSee('CERT-LEGACY-2024-9988')
            ->assertSee('Siti Aminah')
            ->assertSee('Workshop Masa Lalu 2024');
    }

    /**
     * 3. Command artisan semadiksi:migrasi-alur-lama dry-run tidak mengubah database.
     */
    public function test_migrasi_command_dry_run_does_not_modify_database(): void
    {
        $activity = Activity::create([
            'title' => 'Kegiatan Lama 1',
            'category' => 'Seminar',
            'type' => 'Offline',
            'price' => 'Gratis',
            'quota' => 50,
            'status' => 'Selesai',
        ]);

        Attendance::create([
            'activity_id' => $activity->id,
            'activity_title' => $activity->title,
            'student_name' => 'Peserta Uji',
            'student_nim' => '11223344',
            'university' => 'UNUSA',
            'proof_image_url' => 'attendances/test.jpg',
            'status' => 'Hadir',
        ]);

        Certificate::create([
            'code' => 'CERT-OLD-TEST-1111',
            'activity_id' => $activity->id,
            'user_name' => 'Peserta Uji',
            'activity_title' => $activity->title,
            'status' => 'Berlaku',
        ]);

        // Jalankan dry run
        $this->artisan('semadiksi:migrasi-alur-lama')
            ->assertExitCode(0)
            ->expectsOutputToContain('DRY-RUN (Simulasi - Hanya Melapor)')
            ->expectsOutputToContain('STATUS: DRY-RUN SELESAI');

        // Basis data alur baru harus tetap kosong
        $this->assertEquals(0, Kegiatan::where('legacy_activity_id', $activity->id)->count());
        $this->assertEquals(0, KegiatanPresensi::where('nim', '11223344')->count());
        $this->assertEquals(0, Sertifikat::where('nomor_sertifikat', 'CERT-OLD-TEST-1111')->count());
    }

    /**
     * 4. Command artisan semadiksi:migrasi-alur-lama --eksekusi menyalin data dan bersifat idempoten.
     */
    public function test_migrasi_command_eksekusi_copies_data_idempotently_and_handles_ambiguity(): void
    {
        // 1. Activity valid
        $activity = Activity::create([
            'title' => 'Kegiatan Valid Lama',
            'category' => 'Pelatihan',
            'type' => 'Offline',
            'price' => 'Gratis',
            'quota' => 60,
            'status' => 'Dibuka',
        ]);

        // 2. Attendance valid yang merujuk ke activity
        Attendance::create([
            'activity_id' => $activity->id,
            'activity_title' => $activity->title,
            'student_name' => 'Fauzi Ramadhan',
            'student_nim' => '2240021777',
            'student_email' => 'fauzi@unusa.ac.id',
            'university' => 'UNUSA',
            'proof_image_url' => 'attendances/fauzi.jpg',
            'status' => 'Hadir',
        ]);

        // 3. Attendance ambigu (activity_id null)
        Attendance::create([
            'activity_id' => null,
            'activity_title' => 'Kegiatan Tanpa ID',
            'student_name' => 'Tanpa Induk',
            'student_nim' => '99999999',
            'university' => 'Luar',
            'proof_image_url' => 'attendances/anon.jpg',
            'status' => 'Hadir',
        ]);

        // 4. Certificate valid
        Certificate::create([
            'code' => 'CERT-VALID-2024-5555',
            'activity_id' => $activity->id,
            'user_name' => 'Fauzi Ramadhan',
            'activity_title' => $activity->title,
            'status' => 'Berlaku',
            'issued_date' => now()->subMonth(),
        ]);

        // 5. Certificate ambigu (activity_id null)
        Certificate::create([
            'code' => 'CERT-AMBIGUOUS-9999',
            'activity_id' => null,
            'user_name' => 'Tanpa Induk',
            'activity_title' => 'Tanpa Induk Kegiatan',
            'status' => 'Berlaku',
        ]);

        // Jalankan dengan --eksekusi
        $this->artisan('semadiksi:migrasi-alur-lama', ['--eksekusi' => true])
            ->assertExitCode(0)
            ->expectsOutputToContain('STATUS: EKSEKUSI SELESAI');

        // Pastikan kegiatan berhasil disalin
        $kegiatan = Kegiatan::where('legacy_activity_id', $activity->id)->first();
        $this->assertNotNull($kegiatan);
        $this->assertEquals('Kegiatan Valid Lama', $kegiatan->nama_kegiatan);

        // Pastikan presensi valid disalin
        $presensi = KegiatanPresensi::where('kegiatan_id', $kegiatan->id)
            ->where('nim', '2240021777')
            ->first();
        $this->assertNotNull($presensi);
        $this->assertEquals('Fauzi Ramadhan', $presensi->nama_lengkap);

        // Pastikan presensi ambigu TIDAK disalin (tetap sebagai arsip baca-saja)
        $this->assertNull(KegiatanPresensi::where('nim', '99999999')->first());
        $this->assertEquals(2, Attendance::count()); // Data lama tetap utuh

        // Pastikan sertifikat valid disalin
        $sertifikat = Sertifikat::where('nomor_sertifikat', 'CERT-VALID-2024-5555')->first();
        $this->assertNotNull($sertifikat);
        $this->assertEquals('Fauzi Ramadhan', $sertifikat->nama_penerima);

        // Pastikan sertifikat ambigu TIDAK disalin ke tabel baru
        $this->assertNull(Sertifikat::where('nomor_sertifikat', 'CERT-AMBIGUOUS-9999')->first());
        $this->assertEquals(2, Certificate::count()); // Data lama tetap utuh

        // Uji IDEMPOTENSI: jalankan lagi dengan --eksekusi, jumlah record alur baru tidak bertambah
        $this->artisan('semadiksi:migrasi-alur-lama', ['--eksekusi' => true])
            ->assertExitCode(0);

        $this->assertEquals(1, Kegiatan::where('legacy_activity_id', $activity->id)->count());
        $this->assertEquals(1, KegiatanPresensi::where('kegiatan_id', $kegiatan->id)->where('nim', '2240021777')->count());
        $this->assertEquals(1, Sertifikat::where('nomor_sertifikat', 'CERT-VALID-2024-5555')->count());
    }
}
