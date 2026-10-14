<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\KegiatanStatus;
use App\Enums\PeranSertifikat;
use App\Enums\RegistrationPaymentStatus;
use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Certificate;
use App\Models\CustomForm;
use App\Models\CustomFormSubmission;
use App\Models\Kegiatan;
use App\Models\KegiatanPresensi;
use App\Models\KegiatanRegistration;
use App\Models\Sertifikat;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Rabu, 14 Oktober 2026 10:00 WIB -> semester Gasal 2026/2027.
        $this->travelTo(Carbon::parse('2026-10-14 10:00:00', 'Asia/Jakarta'));

        $this->admin = User::factory()->superAdmin()->create();
    }

    protected function createKegiatan(array $attributes = []): Kegiatan
    {
        return Kegiatan::create(array_merge([
            'nama_kegiatan' => 'Seminar Statistik '.Str::random(5),
            'deskripsi' => 'Kegiatan uji dashboard.',
            'kategori' => 'seminar',
            'metode_pelaksanaan' => 'offline',
            'sasaran_peserta' => 'umum',
            'tanggal_mulai' => now()->addDay(),
            'tanggal_selesai' => now()->addDay()->addHours(2),
            'lokasi_type' => 'unusa_campus',
            'lokasi_nama' => 'Auditorium Tower UNUSA',
            'lokasi_detail' => 'Kampus B UNUSA',
            'file_panduan_pdf' => 'kegiatan/panduan.pdf',
            'biaya' => 0,
            'kuota' => 100,
            'status' => KegiatanStatus::Published,
            'is_seat_booking_active' => false,
            'is_submission_active' => false,
        ], $attributes));
    }

    protected function createRegistration(Kegiatan $kegiatan, AttendanceStatus $kehadiran): KegiatanRegistration
    {
        $peserta = User::factory()->role(UserRole::MahasiswaKipk)->create();

        return KegiatanRegistration::create([
            'kegiatan_id' => $kegiatan->id,
            'booker_user_id' => $peserta->id,
            'participant_user_id' => $peserta->id,
            'nomor_tiket' => 'TKT-'.strtoupper(Str::random(8)),
            'participant_name' => $peserta->name,
            'status_pembayaran' => RegistrationPaymentStatus::Free,
            'status_kehadiran' => $kehadiran,
            'checked_in_at' => $kehadiran === AttendanceStatus::CheckedIn ? now() : null,
        ]);
    }

    protected function createPresensi(Kegiatan $kegiatan, string $nama, ?Carbon $checkInAt = null): KegiatanPresensi
    {
        return KegiatanPresensi::create([
            'kegiatan_id' => $kegiatan->id,
            'nama_lengkap' => $nama,
            'nim' => (string) fake()->unique()->numerify('2240######'),
            'prodi' => 'S1 Sistem Informasi',
            'email' => fake()->unique()->safeEmail(),
            'nomor_telepon' => '081234567890',
            'signature_path' => 'presensi/ttd.png',
            'check_in_at' => $checkInAt ?? now(),
        ]);
    }

    protected function createSertifikat(Kegiatan $kegiatan, bool $isValid): Sertifikat
    {
        return Sertifikat::create([
            'nomor_sertifikat' => Sertifikat::generateCertificateNumber($kegiatan),
            'kegiatan_id' => $kegiatan->id,
            'user_id' => User::factory()->role(UserRole::MahasiswaKipk)->create()->id,
            'peran_sertifikat' => PeranSertifikat::Peserta,
            'file_pdf_path' => 'sertifikat/uji.pdf',
            'document_hash' => hash('sha256', Str::random(16)),
            'is_valid' => $isValid,
        ]);
    }

    protected function createSubmission(CustomForm $form, string $status, string $createdAtWib): CustomFormSubmission
    {
        $submission = CustomFormSubmission::create([
            'custom_form_id' => $form->id,
            'applicant_name' => 'Mahasiswa Uji',
            'answers' => ['rekening' => '123456'],
            'status' => $status,
        ]);

        $submission->forceFill(['created_at' => Carbon::parse($createdAtWib, 'Asia/Jakarta')->utc()])->save();

        return $submission;
    }

    protected function stats(): array
    {
        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));
        $response->assertOk();

        return $response->viewData('stats');
    }

    public function test_all_cards_show_zero_when_new_tables_are_empty(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));
        $response->assertOk();

        $stats = $response->viewData('stats');
        $this->assertSame(0, $stats['active_students']);
        $this->assertSame(0, $stats['total_students']);
        $this->assertSame(0, $stats['total_kegiatan']);
        $this->assertSame(0, $stats['kegiatan_aktif']);
        $this->assertSame(0, $stats['sertifikat_terbit']);
        $this->assertSame(0, $stats['sertifikat_dicabut']);
        $this->assertSame(0, $stats['presensi_akhir']);
        $this->assertSame(0, $stats['tiket_hadir']);
        $this->assertSame(0, $stats['pencairan']['menunggu']);
        $this->assertSame(0, $stats['pencairan']['disetujui']);
        $this->assertSame(0, $stats['pencairan']['menunggu_sebelumnya']);
        $this->assertSame(['kegiatan' => 0, 'sertifikat' => 0, 'presensi' => 0], $stats['legacy']);

        $response->assertSee('Kehadiran Peserta');
        $response->assertSee('Pencairan KIP-K Semester Gasal 2026/2027');
        $response->assertSee('0 Menunggu Review');
        $response->assertSee('0 Disetujui');
        $response->assertSee('Belum ada presensi masuk.');
        $response->assertDontSee('Data lama');
        $response->assertDontSee('dari periode sebelumnya');
    }

    public function test_cards_count_from_new_flow_tables(): void
    {
        // Mahasiswa: 3 aktif + 1 diblokir. User umum dan staf tidak ikut dihitung.
        User::factory()->count(3)->role(UserRole::MahasiswaKipk)->create();
        User::factory()->role(UserRole::MahasiswaKipk)->create(['is_blocked' => true]);
        User::factory()->role(UserRole::UserUmum)->create();
        User::factory()->role(UserRole::VerifikatorBerkas)->create();

        // Kegiatan: 1 published, 1 ongoing, 1 draft, 1 terhapus (soft delete).
        $seminar = $this->createKegiatan();
        $this->createKegiatan(['status' => KegiatanStatus::Ongoing]);
        $this->createKegiatan(['status' => KegiatanStatus::Draft]);
        $this->createKegiatan()->delete();

        // Sertifikat: 2 valid, 1 dicabut.
        $this->createSertifikat($seminar, true);
        $this->createSertifikat($seminar, true);
        $this->createSertifikat($seminar, false);

        // Kehadiran: 2 presensi akhir; 3 tiket hadir, 1 tiket belum hadir.
        $this->createPresensi($seminar, 'Ahmad Fauzan');
        $this->createPresensi($seminar, 'Budi Santoso');
        $this->createRegistration($seminar, AttendanceStatus::CheckedIn);
        $this->createRegistration($seminar, AttendanceStatus::CheckedIn);
        $this->createRegistration($seminar, AttendanceStatus::CheckedIn);
        $this->createRegistration($seminar, AttendanceStatus::Registered);

        $stats = $this->stats();

        // Mahasiswa dari createRegistration/createSertifikat (7 orang, semua aktif) ikut terhitung.
        $this->assertSame(3 + 7, $stats['active_students']);
        $this->assertSame(4 + 7, $stats['total_students']);
        $this->assertSame(3, $stats['total_kegiatan']);
        $this->assertSame(2, $stats['kegiatan_aktif']);
        $this->assertSame(2, $stats['sertifikat_terbit']);
        $this->assertSame(1, $stats['sertifikat_dicabut']);
        $this->assertSame(2, $stats['presensi_akhir']);
        $this->assertSame(3, $stats['tiket_hadir']);
    }

    public function test_active_students_only_counts_mahasiswa_kipk_role(): void
    {
        User::factory()->count(2)->role(UserRole::MahasiswaKipk)->create();
        User::factory()->role(UserRole::MahasiswaKipk)->create(['is_blocked' => true]);
        User::factory()->role(UserRole::UserUmum)->create();
        User::factory()->role(UserRole::PenilaiKarya)->create();

        $stats = $this->stats();

        $this->assertSame(2, $stats['active_students']);
        $this->assertSame(3, $stats['total_students']);
    }

    public function test_pencairan_counts_current_semester_from_pencairan_form_only(): void
    {
        $pencairan = CustomForm::create(['form_key' => 'pencairan', 'title' => 'Pengajuan Pencairan', 'is_active' => true]);
        $pelaporan = CustomForm::create(['form_key' => 'pelaporan', 'title' => 'Pelaporan', 'is_active' => true]);

        // Semester berjalan: Gasal 2026/2027, mulai 1 Agustus 2026 00:00 WIB.
        $this->createSubmission($pencairan, 'Menunggu Review', '2026-08-01 00:00:00');
        $this->createSubmission($pencairan, 'Menunggu Review', '2026-10-13 09:00:00');
        $this->createSubmission($pencairan, 'Disetujui', '2026-09-10 13:00:00');
        $this->createSubmission($pencairan, 'Perlu Revisi', '2026-09-11 13:00:00');
        $this->createSubmission($pencairan, 'Ditolak', '2026-09-12 13:00:00');

        // Semester sebelumnya (Genap 2025/2026), termasuk detik terakhirnya.
        $this->createSubmission($pencairan, 'Menunggu Review', '2026-07-31 23:59:59');
        $this->createSubmission($pencairan, 'Disetujui', '2026-03-02 08:00:00');

        // Formulir lain tidak boleh ikut terhitung.
        $this->createSubmission($pelaporan, 'Menunggu Review', '2026-10-01 08:00:00');
        $this->createSubmission($pelaporan, 'Disetujui', '2026-10-01 08:00:00');

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));
        $pencairanStats = $response->viewData('stats')['pencairan'];

        $this->assertSame('Gasal 2026/2027', $pencairanStats['periode']);
        $this->assertSame(2, $pencairanStats['menunggu']);
        $this->assertSame(1, $pencairanStats['disetujui']);
        $this->assertSame(1, $pencairanStats['menunggu_sebelumnya']);

        $response->assertSee('2 Menunggu Review');
        $response->assertSee('1 Disetujui');
        $response->assertSee('+1 menunggu dari periode sebelumnya');
    }

    public function test_semester_period_follows_academic_calendar_and_config(): void
    {
        $cases = [
            ['2026-10-14 10:00:00', 'Gasal 2026/2027', '2026-08-01 00:00:00', '2027-01-31 23:59:59'],
            ['2027-01-31 23:59:59', 'Gasal 2026/2027', '2026-08-01 00:00:00', '2027-01-31 23:59:59'],
            ['2027-02-01 00:00:00', 'Genap 2026/2027', '2027-02-01 00:00:00', '2027-07-31 23:59:59'],
            ['2026-07-31 23:59:59', 'Genap 2025/2026', '2026-02-01 00:00:00', '2026-07-31 23:59:59'],
        ];

        foreach ($cases as [$now, $label, $mulai, $selesai]) {
            $this->travelTo(Carbon::parse($now, 'Asia/Jakarta'));
            $periode = $this->stats()['pencairan'];

            $this->assertSame($label, $periode['periode'], "Label periode untuk {$now}");
            $this->assertSame($mulai, $periode['mulai']->format('Y-m-d H:i:s'), "Awal periode untuk {$now}");
            $this->assertSame($selesai, $periode['selesai']->format('Y-m-d H:i:s'), "Akhir periode untuk {$now}");
        }

        // Batas semester bisa diubah lewat config.
        config(['unusa.semester.gasal_start_month' => 9, 'unusa.semester.genap_start_month' => 3]);
        $this->travelTo(Carbon::parse('2026-08-15 10:00:00', 'Asia/Jakarta'));
        $periode = $this->stats()['pencairan'];

        $this->assertSame('Genap 2025/2026', $periode['periode']);
        $this->assertSame('2026-03-01 00:00:00', $periode['mulai']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-31 23:59:59', $periode['selesai']->format('Y-m-d H:i:s'));
    }

    public function test_legacy_tables_are_shown_separately_and_not_summed(): void
    {
        $student = User::factory()->role(UserRole::MahasiswaKipk)->create();
        $activity = Activity::create([
            'title' => 'Kegiatan Lama',
            'category' => 'Seminar',
            'type' => 'Offline',
            'date_text' => '1 Oktober 2026',
            'location' => 'Aula',
            'price' => 'Gratis',
            'quota' => 50,
            'xp_points' => 10,
            'is_closed' => false,
        ]);
        Certificate::create([
            'user_id' => $student->id,
            'activity_id' => $activity->id,
            'user_name' => $student->name,
            'activity_title' => $activity->title,
            'code' => 'SMD-LAMA-0001',
            'status' => 'Berlaku',
            'issued_date' => now()->toDateString(),
        ]);
        Attendance::create([
            'user_id' => $student->id,
            'activity_id' => $activity->id,
            'activity_title' => $activity->title,
            'activity_category' => 'Seminar',
            'activity_date' => '1 Oktober 2026',
            'student_name' => 'Peserta Presensi Lama',
            'student_nim' => '2240000001',
            'university' => 'UNUSA',
            'proof_image_url' => 'https://example.com/bukti.jpg',
            'proof_file_name' => 'bukti.jpg',
            'status' => 'Hadir',
            'submitted_at' => now(),
        ]);

        $kegiatan = $this->createKegiatan();
        $this->createSertifikat($kegiatan, true);
        $this->createPresensi($kegiatan, 'Peserta Presensi Baru');

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));
        $stats = $response->viewData('stats');

        $this->assertSame(1, $stats['total_kegiatan']);
        $this->assertSame(1, $stats['sertifikat_terbit']);
        $this->assertSame(1, $stats['presensi_akhir']);
        $this->assertSame(['kegiatan' => 1, 'sertifikat' => 1, 'presensi' => 1], $stats['legacy']);

        $response->assertSee('Data lama: 1 kegiatan');
        $response->assertSee('Data lama: 1 sertifikat');
        $response->assertSee('Data lama: 1 presensi');

        // "Presensi Terkini" hanya membaca kegiatan_presensi.
        $response->assertSee('Peserta Presensi Baru');
        $response->assertDontSee('Peserta Presensi Lama');
    }

    public function test_recent_presensi_lists_latest_final_attendance(): void
    {
        $kegiatan = $this->createKegiatan(['nama_kegiatan' => 'Workshop Presensi Akhir']);
        $this->createPresensi($kegiatan, 'Peserta Paling Awal', now()->subDays(10));
        foreach (range(1, 5) as $i) {
            $this->createPresensi($kegiatan, "Peserta Baru {$i}", now()->subHours(6 - $i));
        }

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));
        $recent = $response->viewData('recentPresensi');

        $this->assertCount(5, $recent);
        $this->assertSame('Peserta Baru 5', $recent->first()->nama_lengkap);
        $response->assertSee('Workshop Presensi Akhir');
        $response->assertSee('09:00 WIB');
        $response->assertDontSee('Peserta Paling Awal');
    }
}
