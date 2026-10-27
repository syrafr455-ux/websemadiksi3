<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\KegiatanStatus;
use App\Enums\RegistrationPaymentStatus;
use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\Kegiatan;
use App\Models\KegiatanPresensi;
use App\Models\KegiatanRegistration;
use App\Models\User;
use App\Support\Wib;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AbsensiDashboardPesertaTest extends TestCase
{
    use RefreshDatabase;

    protected User $peserta;

    protected User $userLain;

    protected Kegiatan $kegiatanDiikuti;

    protected KegiatanRegistration $registrasiLunas;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Carbon::setTestNow(Carbon::parse('2026-11-10 10:00:00', 'Asia/Jakarta'));

        $this->peserta = User::factory()->create([
            'role' => UserRole::MahasiswaKipk,
            'name' => 'Ahmad Peserta',
            'nim' => '2240021001',
            'email' => 'ahmad@unusa.ac.id',
        ]);

        $this->userLain = User::factory()->create([
            'role' => UserRole::MahasiswaKipk,
            'name' => 'Budi Santoso',
            'nim' => '2240021002',
            'email' => 'budi@unusa.ac.id',
        ]);

        $this->kegiatanDiikuti = Kegiatan::create([
            'nama_kegiatan' => 'Seminar AI Modern 2026',
            'deskripsi' => 'Deskripsi.',
            'kategori' => 'seminar',
            'metode_pelaksanaan' => 'offline',
            'sasaran_peserta' => 'umum',
            'tanggal_mulai' => Wib::keUtc('2026-11-10 09:00'),
            'tanggal_selesai' => Wib::keUtc('2026-11-10 13:00'),
            'lokasi_type' => 'unusa_campus',
            'lokasi_nama' => 'Auditorium Tower',
            'file_panduan_pdf' => 'kegiatan/panduan.pdf',
            'biaya' => 0,
            'kuota' => 100,
            'status' => KegiatanStatus::Published,
        ]);

        $this->registrasiLunas = KegiatanRegistration::create([
            'kegiatan_id' => $this->kegiatanDiikuti->id,
            'booker_user_id' => $this->peserta->id,
            'participant_user_id' => $this->peserta->id,
            'nomor_tiket' => 'TKT-AI-001',
            'participant_name' => $this->peserta->name,
            'participant_identity' => $this->peserta->nim,
            'participant_phone' => '081234567890',
            'status_pembayaran' => RegistrationPaymentStatus::Free,
            'status_kehadiran' => AttendanceStatus::Registered,
        ]);
    }

    /**
     * 1. Halaman /dashboard/absensi menampilkan penjelasan alur dua tahap dan tautan Kegiatan Saya.
     */
    public function test_halaman_absensi_menampilkan_penjelasan_alur_dua_tahap(): void
    {
        $response = $this->actingAs($this->peserta)->get(route('dashboard.absensi'));

        $response->assertOk();
        $response->assertSee('Alur Presensi Resmi 2-Tahap');
        $response->assertSee('Tahap 1: Check-in Awal');
        $response->assertSee('Tahap 2: Presensi Akhir');
        $response->assertSee(route('dashboard.kegiatan.saya'));
    }

    /**
     * 2. Riwayat kehadiran menampilkan presensi alur baru dan lama (legacy) dengan penanda sumber.
     */
    public function test_riwayat_kehadiran_menampilkan_presensi_baru_dan_legacy(): void
    {
        // Presensi alur baru
        KegiatanPresensi::create([
            'kegiatan_id' => $this->kegiatanDiikuti->id,
            'user_id' => $this->peserta->id,
            'registration_id' => $this->registrasiLunas->id,
            'nama_lengkap' => $this->peserta->name,
            'nim' => $this->peserta->nim,
            'prodi' => 'Sistem Informasi',
            'email' => $this->peserta->email,
            'nomor_telepon' => '081234567890',
            'signature_path' => 'signatures/presensi-1.png',
            'check_in_at' => now(),
        ]);

        // Presensi legacy
        Attendance::create([
            'user_id' => $this->peserta->id,
            'student_name' => $this->peserta->name,
            'student_nim' => $this->peserta->nim,
            'activity_title' => 'Webinar Nasional Legacy 2024',
            'submitted_at' => now()->subYear(),
            'location_name' => 'Kampus B UNUSA',
            'proof_image_url' => 'https://example.com/legacy.jpg',
            'status' => 'Hadir',
        ]);

        $response = $this->actingAs($this->peserta)->get(route('dashboard.absensi'));

        $response->assertOk();
        $response->assertSee('Seminar AI Modern 2026');
        $response->assertSee('Webinar Nasional Legacy 2024');
        $response->assertSee('Presensi Resmi');
        $response->assertSee('Legacy');
    }

    /**
     * 3. Formulir swafoto mandiri lama telah dipensiunkan; POST ke /dashboard/absensi
     * TIDAK lagi menulis ke kegiatan_presensi atau mengubah status kehadiran.
     */
    public function test_formulir_swafoto_dipensiunkan_tidak_menulis_ke_kegiatan_presensi(): void
    {
        $fakeImage = UploadedFile::fake()->image('selfie.jpg', 600, 600);

        $response = $this->actingAs($this->peserta)->post(route('dashboard.absensi.store'), [
            'activity_id' => $this->kegiatanDiikuti->id,
            'proof_image' => $fakeImage,
            'location_name' => 'Auditorium Tower',
        ]);

        $response->assertRedirect(route('dashboard.absensi'));
        $response->assertSessionHas('info');

        // Pastikan TIDAK ADA row kegiatan_presensi yang ditulis
        $this->assertDatabaseMissing('kegiatan_presensi', [
            'kegiatan_id' => $this->kegiatanDiikuti->id,
            'user_id' => $this->peserta->id,
        ]);

        // Status registrasi tidak berubah
        $this->registrasiLunas->refresh();
        $this->assertEquals(AttendanceStatus::Registered, $this->registrasiLunas->status_kehadiran);
    }

    /**
     * 4. Halaman /dashboard/absensi menampilkan keadaan kosong jika belum ada catatan presensi.
     */
    public function test_halaman_absensi_menampilkan_keadaan_kosong_bila_belum_ada_catatan(): void
    {
        $response = $this->actingAs($this->userLain)->get(route('dashboard.absensi'));

        $response->assertOk();
        $response->assertSee('Belum ada riwayat presensi yang tercatat.');
    }

    /**
     * 5. POST ke /dashboard/absensi TIDAK memicu pembuatan sertifikat.
     */
    public function test_kirim_presensi_dashboard_tidak_memicu_pembuatan_sertifikat(): void
    {
        $fakeImage = UploadedFile::fake()->image('selfie.jpg', 600, 600);

        $response = $this->actingAs($this->peserta)->post(route('dashboard.absensi.store'), [
            'activity_id' => $this->kegiatanDiikuti->id,
            'proof_image' => $fakeImage,
            'location_name' => 'Auditorium Tower',
        ]);

        // Pastikan tidak ada sertifikat yang dibuat
        $this->assertDatabaseMissing('sertifikat', [
            'kegiatan_id' => $this->kegiatanDiikuti->id,
            'user_id' => $this->peserta->id,
        ]);
    }
}
