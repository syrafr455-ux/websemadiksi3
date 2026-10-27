<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\KegiatanStatus;
use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Certificate;
use App\Models\CustomForm;
use App\Models\CustomFormSubmission;
use App\Models\Kegiatan;
use App\Models\KegiatanRegistration;
use App\Models\User;
use App\Services\Kegiatan\PresensiAkhirTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageCleanupAndAdminStatsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 1. Landing page tidak lagi memuat angka statistik dan menu yang dihapus.
     */
    public function test_landing_page_does_not_contain_statistics_or_removed_navigation_items(): void
    {
        // Buat dummy data di database
        $mahasiswa = User::factory()->create(['role' => UserRole::MahasiswaKipk, 'is_blocked' => false]);
        $activity = Activity::create([
            'title' => 'Seminar Teknologi Nasional',
            'category' => 'Seminar',
            'type' => 'Online',
            'date_text' => '20 Oktober 2026',
            'location' => 'Zoom Meeting',
            'price' => 'Gratis',
            'quota' => 100,
            'xp_points' => 50,
            'is_closed' => false,
        ]);
        Certificate::create([
            'user_id' => $mahasiswa->id,
            'activity_id' => $activity->id,
            'user_name' => 'Peserta Uji',
            'activity_title' => 'Seminar Teknologi Nasional',
            'code' => 'CERT-LANDING-001',
            'status' => 'Berlaku',
            'issued_date' => now()->toDateString(),
        ]);
        Attendance::create([
            'user_id' => $mahasiswa->id,
            'activity_id' => $activity->id,
            'activity_title' => 'Seminar Teknologi Nasional',
            'activity_category' => 'Seminar',
            'activity_date' => '20 Oktober 2026',
            'student_name' => 'Peserta Uji',
            'student_nim' => '123456789',
            'university' => 'UNUSA',
            'proof_image_url' => 'https://example.com/proof.jpg',
            'proof_file_name' => 'proof.jpg',
            'status' => 'Hadir',
            'submitted_at' => now(),
        ]);

        $response = $this->get(route('home'));

        $response->assertStatus(200);

        // Pastikan variabel stats internal tidak dikirim ke view
        $response->assertViewMissing('stats');

        // Pastikan menu & teks yang diminta dihapus TIDAK ada lagi
        $response->assertDontSee('Presensi Cepat');
        $response->assertDontSee('Verifikasi Sertifikat');
        $response->assertDontSee('Cek Validitas Sertifikat');
        $response->assertDontSee('Sinkronisasi PostgreSQL Real-Time');

        // Pastikan menu resmi tetap lengkap
        $response->assertSee('Beranda');
        $response->assertSee('Profil');
        $response->assertSee('Berita Acara');
        $response->assertSee('Masuk');
        $response->assertSee('Daftar Akun');

        // Pastikan kartu informasi layanan statis muncul di sisi kanan hero
        $response->assertSee('Layanan Terpadu Mahasiswa');
        $response->assertSee('Pelaporan KIP-K');
        $response->assertSee('Agenda Kegiatan');
        $response->assertSee('E-Sertifikat Digital');
        $response->assertSee('Presensi Mandiri');
    }

    /**
     * 2. /admin menampilkan statistik untuk super_admin dengan angka asli database.
     */
    public function test_admin_dashboard_shows_statistics_for_super_admin(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'is_blocked' => false,
        ]);

        // Buat 3 mahasiswa aktif dan 1 non-aktif
        User::factory()->count(3)->create(['role' => UserRole::MahasiswaKipk, 'is_blocked' => false]);
        User::factory()->create(['role' => UserRole::MahasiswaKipk, 'is_blocked' => true]);

        // Buat 2 kegiatan
        $kegiatanSatu = Activity::create([
            'title' => 'Kegiatan 1',
            'category' => 'Workshop',
            'type' => 'Offline',
            'date_text' => '15 Oktober 2026',
            'location' => 'Auditorium UNUSA',
            'price' => 'Gratis',
            'quota' => 50,
            'xp_points' => 25,
            'is_closed' => false,
        ]);
        Activity::create([
            'title' => 'Kegiatan 2',
            'category' => 'Webinar',
            'type' => 'Online',
            'date_text' => '16 Oktober 2026',
            'location' => 'Zoom',
            'price' => 'Gratis',
            'quota' => 50,
            'xp_points' => 25,
            'is_closed' => false,
        ]);

        // Buat sertifikat
        Certificate::create([
            'user_id' => $admin->id,
            'activity_id' => $kegiatanSatu->id,
            'user_name' => 'Mahasiswa Contoh',
            'activity_title' => 'Kegiatan 1',
            'code' => 'CERT-ADMIN-TEST',
            'status' => 'Berlaku',
            'issued_date' => now()->toDateString(),
        ]);

        // Buat formulir pengajuan pencairan
        $pencairanForm = CustomForm::create([
            'form_key' => 'pencairan',
            'title' => 'Pengajuan Pencairan Dana KIP-K',
            'is_active' => true,
        ]);
        CustomFormSubmission::create([
            'custom_form_id' => $pencairanForm->id,
            'user_id' => $admin->id,
            'applicant_name' => 'Mahasiswa Test',
            'applicant_nim' => '2240001',
            'applicant_email' => 'mhs@unusa.ac.id',
            'answers' => ['rekening' => '123456'],
            'status' => 'Menunggu Review',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertStatus(200);
        $response->assertViewHas('stats');

        // Memastikan kartu 'Data Terkini Sistem' tampil di admin
        $response->assertSee('Data Terkini Sistem');
        $response->assertSee('Mahasiswa Aktif');
        $response->assertSee('Total Kegiatan');
        $response->assertSee('Sertifikat Terbit');
        $response->assertSee('Kehadiran Peserta');
        $response->assertSee('Pencairan KIP-K Semester');

        // Data model warisan di atas tampil terpisah, tidak dijumlahkan ke kartu utama
        // (rincian angka diuji di AdminDashboardStatsTest).
        $response->assertSee('Data lama: 2 kegiatan');
        $response->assertSee('Data lama: 1 sertifikat');

        // Pastikan label palsu tidak ada
        $response->assertDontSee('Sinkronisasi PostgreSQL Real-Time');
    }

    /**
     * 3. /admin menolak role lain selain super_admin.
     */
    public function test_admin_dashboard_rejects_non_super_admin(): void
    {
        // Mahasiswa KIP-K
        $student = User::factory()->create(['role' => UserRole::MahasiswaKipk, 'is_blocked' => false]);
        $this->actingAs($student)->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));

        // User Umum
        $userUmum = User::factory()->create(['role' => UserRole::UserUmum, 'is_blocked' => false]);
        $this->actingAs($userUmum)->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));

        // Guest (belum login)
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));

        // Verifikator Berkas -> dialihkan ke modul berkasnya
        $verifikator = User::factory()->create(['role' => UserRole::VerifikatorBerkas, 'is_blocked' => false]);
        $this->actingAs($verifikator)->get(route('admin.dashboard'))->assertRedirect(route('admin.berkas'));

        // Penilai Karya -> dialihkan ke modul penilaiannya
        $penilai = User::factory()->create(['role' => UserRole::PenilaiKarya, 'is_blocked' => false]);
        $this->actingAs($penilai)->get(route('admin.dashboard'))->assertRedirect(route('admin.penilaian'));
    }

    /**
     * 4. /verifikasi?code=... tetap bisa dibuka publik tanpa login.
     */
    public function test_public_verification_accessible_without_login(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::MahasiswaKipk,
            'is_blocked' => false,
        ]);

        $activity = Activity::create([
            'title' => 'Workshop UI/UX Design',
            'category' => 'Workshop',
            'type' => 'Offline',
            'date_text' => '10 Oktober 2026',
            'location' => 'Lab Komputer',
            'price' => 'Gratis',
            'quota' => 30,
            'xp_points' => 10,
            'is_closed' => false,
        ]);

        $certificate = Certificate::create([
            'user_id' => $user->id,
            'activity_id' => $activity->id,
            'user_name' => 'Ahmad Fulan',
            'activity_title' => $activity->title,
            'code' => 'CERT-VERIF-TEST-1234',
            'status' => 'Berlaku',
            'issued_date' => now()->toDateString(),
        ]);

        // Akses publik ke /verifikasi tanpa login
        $response = $this->get('/verifikasi?code=CERT-VERIF-TEST-1234');
        $response->assertStatus(200);
        $response->assertSee('CERT-VERIF-TEST-1234');
        $response->assertSee('Ahmad Fulan');

        // Akses publik ke /verifikasi-sertifikat tanpa login
        $response2 = $this->get('/verifikasi-sertifikat?code=CERT-VERIF-TEST-1234');
        $response2->assertStatus(200);
    }

    /**
     * 5. Halaman presensi peserta tetap bisa diakses sesuai aturan yang ada.
     */
    public function test_attendance_pages_remain_accessible(): void
    {
        // 1. Presensi Mandiri /absensi
        $response = $this->get(route('absensi.public'));
        $response->assertStatus(200);
        $response->assertSee('Presensi Mandiri');

        // 2. Presensi Kegiatan (Self Attendance) sesuai aturan dua fase & izin QR
        $kegiatan = Kegiatan::create([
            'nama_kegiatan' => 'Seminar Inovasi Digital',
            'deskripsi' => 'Deskripsi kegiatan seminar.',
            'kategori' => 'seminar',
            'metode_pelaksanaan' => 'offline',
            'sasaran_peserta' => 'umum',
            'tanggal_mulai' => now()->subHour(),
            'tanggal_selesai' => now()->addHours(2),
            'lokasi_type' => 'unusa_campus',
            'lokasi_nama' => 'Auditorium Tower Unusa',
            'lokasi_detail' => 'Kampus B UNUSA',
            'file_panduan_pdf' => 'kegiatan/panduan.pdf',
            'biaya' => 0,
            'kuota' => 100,
            'status' => KegiatanStatus::Published,
            'is_seat_booking_active' => false,
            'is_submission_active' => false,
            'presensi_akhir_dibuka' => true,
            'presensi_akhir_mulai' => now()->subMinutes(15),
            'presensi_akhir_selesai' => now()->addHours(2),
        ]);

        $peserta = User::factory()->create([
            'role' => UserRole::MahasiswaKipk,
            'name' => 'Peserta Uji',
            'nim' => '2240021088',
            'email' => 'pesertauji@unusa.ac.id',
        ]);

        KegiatanRegistration::create([
            'kegiatan_id' => $kegiatan->id,
            'booker_user_id' => $peserta->id,
            'participant_user_id' => $peserta->id,
            'nomor_tiket' => 'TKT-LANDING-001',
            'ticket_code' => 'TKT-LANDING-001',
            'participant_name' => $peserta->name,
            'participant_identity' => $peserta->nim,
            'status_pembayaran' => 'free',
            'status_kehadiran' => AttendanceStatus::CheckedIn,
            'checked_in_at' => now()->subMinutes(30),
        ]);

        $token = app(PresensiAkhirTokenService::class)->generateToken($kegiatan->id);
        $this->actingAs($peserta)->get(route('kegiatan.presensi-akhir.scan', [
            'kegiatan' => $kegiatan->id,
            'token' => $token,
        ]))->assertRedirect(route('kegiatan.presensi.show', $kegiatan->id));

        $responseKegiatan = $this->actingAs($peserta)->get(route('presensi.self', $kegiatan->id));
        $responseKegiatan->assertStatus(200);
        $responseKegiatan->assertSee('Presensi Mandiri & Tanda Tangan Digital', false);
    }
}
