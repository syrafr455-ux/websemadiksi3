<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\KegiatanStatus;
use App\Enums\PeranSertifikat;
use App\Enums\RegistrationPaymentStatus;
use App\Enums\UserRole;
use App\Events\KegiatanUpdatedEvent;
use App\Mail\KegiatanUpdatedMail;
use App\Mail\TicketIssuedMail;
use App\Models\CustomForm;
use App\Models\CustomFormSubmission;
use App\Models\Kegiatan;
use App\Models\KegiatanPresensi;
use App\Models\KegiatanRegistration;
use App\Models\User;
use App\Services\CertificateGeneratorService;
use App\Services\Kegiatan\PresensiAkhirTokenService;
use App\Support\Wib;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class TimezoneConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->travelToWib('2026-10-18 09:00:00');
    }

    protected function travelToWib(string $waktuWib): void
    {
        $this->travelTo(Carbon::parse($waktuWib, 'Asia/Jakarta'));
    }

    protected function createKegiatan(array $attributes = []): Kegiatan
    {
        return Kegiatan::create(array_merge([
            'nama_kegiatan' => 'Lomba Inovasi '.Str::random(5),
            'deskripsi' => 'Kegiatan uji zona waktu.',
            'kategori' => 'lomba',
            'metode_pelaksanaan' => 'offline',
            'sasaran_peserta' => 'umum',
            // 1 November 2026 08:00 WIB = 01:00 UTC
            'tanggal_mulai' => Wib::keUtc('2026-11-01 08:00'),
            'tanggal_selesai' => Wib::keUtc('2026-11-01 12:00'),
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

    protected function createMahasiswa(): User
    {
        return User::factory()->role(UserRole::MahasiswaKipk)->create([
            'nim' => (string) fake()->unique()->numerify('2240######'),
            'verification_status' => 'Verified',
        ]);
    }

    protected function createRegistration(Kegiatan $kegiatan, User $user): KegiatanRegistration
    {
        return KegiatanRegistration::create([
            'kegiatan_id' => $kegiatan->id,
            'booker_user_id' => $user->id,
            'participant_user_id' => $user->id,
            'nomor_tiket' => 'TKT-'.strtoupper(Str::random(8)),
            'participant_name' => $user->name,
            'status_pembayaran' => RegistrationPaymentStatus::Free,
            'status_kehadiran' => AttendanceStatus::Registered,
        ]);
    }

    protected function checkout(Kegiatan $kegiatan, User $user)
    {
        return $this->actingAs($user)->post(route('kegiatan.checkout', $kegiatan->id), [
            'delegates' => [[
                'name' => $user->name,
                'email' => $user->email,
                'identity' => $user->nim,
                'phone' => '081234567890',
            ]],
        ]);
    }

    protected function rawUtc(Kegiatan $kegiatan, string $kolom): ?string
    {
        $nilai = DB::table('kegiatan')->where('id', $kegiatan->id)->value($kolom);

        return $nilai === null ? null : Carbon::parse($nilai)->format('Y-m-d H:i:s');
    }

    protected function adminPayload(array $overrides = []): array
    {
        return array_merge([
            'nama_kegiatan' => 'Seminar Zona Waktu',
            'kategori' => 'Seminar',
            'sasaran_peserta' => 'umum',
            'tanggal_mulai' => '2026-11-01T08:00',
            'tanggal_selesai' => '2026-11-01T12:00',
            'registration_deadline' => '2026-10-25T23:59',
            'submission_deadline' => '2026-10-30T17:00',
            'lokasi_type' => 'external',
            'lokasi_nama' => 'Gedung Serbaguna',
            'biaya' => 0,
            'kuota' => 50,
            'is_seat_booking_active' => false,
        ], $overrides);
    }

    public function test_admin_store_converts_wib_input_to_utc(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->post(route('admin.kegiatan.store'), $this->adminPayload())
            ->assertRedirect(route('admin.kegiatan'));

        $kegiatan = Kegiatan::where('nama_kegiatan', 'Seminar Zona Waktu')->firstOrFail();

        $this->assertSame('2026-11-01 01:00:00', $this->rawUtc($kegiatan, 'tanggal_mulai'));
        $this->assertSame('2026-11-01 05:00:00', $this->rawUtc($kegiatan, 'tanggal_selesai'));
        $this->assertSame('2026-10-25 16:59:00', $this->rawUtc($kegiatan, 'registration_deadline'));
        $this->assertSame('2026-10-30 10:00:00', $this->rawUtc($kegiatan, 'submission_deadline'));
    }

    public function test_admin_update_converts_input_and_keeps_omitted_deadlines(): void
    {
        Event::fake([KegiatanUpdatedEvent::class]);
        $admin = User::factory()->superAdmin()->create();
        $kegiatan = $this->createKegiatan([
            'kategori' => 'seminar',
            'registration_deadline' => Wib::keUtc('2026-10-25 23:59'),
        ]);

        $payload = $this->adminPayload([
            'status' => KegiatanStatus::Published->value,
            'tanggal_mulai' => '2026-11-02T09:30',
            'tanggal_selesai' => '2026-11-02T12:00',
        ]);
        unset($payload['registration_deadline'], $payload['submission_deadline']);

        $this->actingAs($admin)->put(route('admin.kegiatan.update', $kegiatan->id), $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame('2026-11-02 02:30:00', $this->rawUtc($kegiatan, 'tanggal_mulai'));
        $this->assertSame('2026-11-02 05:00:00', $this->rawUtc($kegiatan, 'tanggal_selesai'));
        $this->assertSame('2026-10-25 16:59:00', $this->rawUtc($kegiatan, 'registration_deadline'));

        // Notifikasi perubahan jadwal membandingkan jam WIB lama dan baru.
        Event::assertDispatched(KegiatanUpdatedEvent::class, fn (KegiatanUpdatedEvent $event) => $event->changedAttributes['tanggal_mulai'] === ['old' => '2026-11-01 08:00', 'new' => '2026-11-02 09:30']);
    }

    public function test_admin_update_with_same_wib_schedule_does_not_report_schedule_change(): void
    {
        Event::fake([KegiatanUpdatedEvent::class]);
        $admin = User::factory()->superAdmin()->create();
        $kegiatan = $this->createKegiatan([
            'kategori' => 'seminar',
            'lokasi_type' => 'external',
            'lokasi_nama' => 'Gedung Serbaguna',
            'lokasi_detail' => null,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.kegiatan.update', $kegiatan->id), $this->adminPayload(['status' => KegiatanStatus::Published->value]))
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame('2026-11-01 01:00:00', $this->rawUtc($kegiatan, 'tanggal_mulai'));
        Event::assertNotDispatched(KegiatanUpdatedEvent::class);
    }

    public function test_inertia_pages_receive_tanggal_mulai_for_display_and_edit_form(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $kegiatan = $this->createKegiatan(['submission_deadline' => Wib::keUtc('2026-10-30 17:00')]);
        $mahasiswa = $this->createMahasiswa();
        $this->createRegistration($kegiatan, $mahasiswa);

        $adminProps = $this->actingAs($admin)->get(route('admin.kegiatan'))->viewData('page')['props'];
        $item = $adminProps['kegiatans']['data'][0];
        $this->assertSame('2026-11-01T01:00:00.000000Z', $item['tanggal_mulai']);
        $this->assertSame('2026-11-01T05:00:00.000000Z', $item['tanggal_selesai']);
        $this->assertSame('2026-10-30T10:00:00.000000Z', $item['submission_deadline']);

        $showProps = $this->actingAs($mahasiswa)->get(route('kegiatan.detail', $kegiatan->id))->viewData('page')['props'];
        $this->assertSame('2026-11-01T01:00:00.000000Z', $showProps['kegiatan']['tanggal_mulai']);

        $sayaProps = $this->actingAs($mahasiswa)->get(route('dashboard.kegiatan.saya'))->viewData('page')['props'];
        $this->assertSame('2026-11-01T01:00:00.000000Z', $sayaProps['enterpriseRegistrations'][0]['kegiatan']['tanggal_mulai']);
    }

    public function test_registration_closes_exactly_at_wib_deadline(): void
    {
        $kegiatan = $this->createKegiatan(['registration_deadline' => Wib::keUtc('2026-10-20 23:59:00')]);

        $this->travelToWib('2026-10-20 23:58:59');
        $this->assertTrue($kegiatan->fresh()->isRegistrationOpen());
        $pesertaTepatWaktu = $this->createMahasiswa();
        $this->checkout($kegiatan, $pesertaTepatWaktu)->assertSessionHas('success');
        $this->assertDatabaseHas('kegiatan_registrations', ['kegiatan_id' => $kegiatan->id, 'booker_user_id' => $pesertaTepatWaktu->id]);

        $this->travelToWib('2026-10-20 23:59:00');
        $this->assertFalse($kegiatan->fresh()->isRegistrationOpen());
        $pesertaTerlambat = $this->createMahasiswa();
        $this->checkout($kegiatan, $pesertaTerlambat)->assertSessionHas('error', 'Pendaftaran untuk kegiatan ini telah ditutup.');
        $this->assertDatabaseMissing('kegiatan_registrations', ['kegiatan_id' => $kegiatan->id, 'booker_user_id' => $pesertaTerlambat->id]);
    }

    public function test_submission_closes_exactly_at_wib_deadline(): void
    {
        $kegiatan = $this->createKegiatan([
            'is_submission_active' => true,
            'submission_deadline' => Wib::keUtc('2026-10-30 17:00:00'),
        ]);
        $pesertaTepatWaktu = $this->createMahasiswa();
        $pesertaTerlambat = $this->createMahasiswa();
        $registrasiTepatWaktu = $this->createRegistration($kegiatan, $pesertaTepatWaktu);
        $registrasiTerlambat = $this->createRegistration($kegiatan, $pesertaTerlambat);

        $unggah = fn (User $user, KegiatanRegistration $registration) => $this->actingAs($user)
            ->post(route('dashboard.kegiatan.upload-karya', $registration->id), [
                'title' => 'Karya Uji',
                'description' => 'Deskripsi karya',
                'submission_file' => UploadedFile::fake()->create('karya.pdf', 100, 'application/pdf'),
            ]);

        $this->travelToWib('2026-10-30 16:59:59');
        $this->assertTrue($kegiatan->fresh()->isSubmissionOpen());
        $unggah($pesertaTepatWaktu, $registrasiTepatWaktu)->assertSessionHas('success');
        $this->assertDatabaseHas('kegiatan_submissions', ['registration_id' => $registrasiTepatWaktu->id]);

        $this->travelToWib('2026-10-30 17:00:00');
        $this->assertFalse($kegiatan->fresh()->isSubmissionOpen());
        $unggah($pesertaTerlambat, $registrasiTerlambat)->assertSessionHas('error');
        $this->assertDatabaseMissing('kegiatan_submissions', ['registration_id' => $registrasiTerlambat->id]);
    }

    public function test_self_attendance_page_shows_wib_times(): void
    {
        $kegiatan = $this->createKegiatan([
            'presensi_akhir_dibuka' => true,
            'presensi_akhir_mulai' => now()->subMinutes(30),
            'presensi_akhir_selesai' => now()->addHours(2),
        ]);
        $mahasiswa = $this->createMahasiswa();
        $reg = $this->createRegistration($kegiatan, $mahasiswa);
        $reg->update([
            'status_kehadiran' => AttendanceStatus::CheckedIn,
            'checked_in_at' => now()->subHour(),
        ]);

        $token = app(PresensiAkhirTokenService::class)->generateToken($kegiatan->id);
        $this->actingAs($mahasiswa)->get(route('kegiatan.presensi-akhir.scan', [
            'kegiatan' => $kegiatan->id,
            'token' => $token,
        ]))->assertRedirect(route('kegiatan.presensi.show', $kegiatan->id));

        KegiatanPresensi::create([
            'kegiatan_id' => $kegiatan->id,
            'user_id' => $mahasiswa->id,
            'registration_id' => $reg->id,
            'nama_lengkap' => $mahasiswa->name,
            'nim' => $mahasiswa->nim,
            'prodi' => 'S1 Sistem Informasi',
            'email' => $mahasiswa->email,
            'nomor_telepon' => '081234567890',
            'signature_path' => 'presensi/ttd.png',
            // 1 November 2026 08:30 WIB = 01:30 UTC
            'check_in_at' => Carbon::parse('2026-11-01 01:30:00', 'UTC'),
        ]);

        $response = $this->actingAs($mahasiswa)->get(route('presensi.self', $kegiatan->id));

        $response->assertOk();
        $response->assertSee('2026, 08:00 WIB');
        $response->assertSee('2026, 08:30 WIB');
        $response->assertDontSee('2026, 01:00');
        $response->assertDontSee('2026, 01:30');
    }

    public function test_certificate_issued_after_midnight_wib_uses_new_year_and_wib_time(): void
    {
        $kegiatan = $this->createKegiatan();
        $penerima = $this->createMahasiswa();

        // 1 Januari 2027 00:30 WIB = 31 Desember 2026 17:30 UTC.
        $this->travelToWib('2027-01-01 00:30:00');
        $sertifikat = (new CertificateGeneratorService)->generate($kegiatan, $penerima, PeranSertifikat::Peserta);

        $this->assertStringContainsString('-2027-', $sertifikat->nomor_sertifikat);
        $this->assertSame('2026-12-31 17:30:00', $sertifikat->fresh()->issued_at->utc()->format('Y-m-d H:i:s'));

        $this->get(route('verifikasi.sertifikat', ['code' => $sertifikat->nomor_sertifikat]))
            ->assertOk()
            ->assertSee('2027, 00:30 WIB')
            ->assertDontSee('2026, 17:30');
    }

    public function test_emails_show_wib_start_time_instead_of_dash(): void
    {
        $kegiatan = $this->createKegiatan();
        $mahasiswa = $this->createMahasiswa();
        $registration = $this->createRegistration($kegiatan, $mahasiswa);

        $ticketMail = new TicketIssuedMail($registration->fresh());
        $ticketMail->assertSeeInHtml('2026, 08:00 WIB');
        $ticketMail->assertDontSeeInHtml('- WIB');

        $updatedMail = new KegiatanUpdatedMail($kegiatan->fresh(), $mahasiswa, []);
        $updatedMail->assertSeeInHtml('2026 08:00 WIB');
        $updatedMail->assertDontSeeInHtml('- WIB');
    }

    public function test_submission_history_and_admin_list_show_wib_submission_time(): void
    {
        Mail::fake();
        $form = CustomForm::create(['form_key' => 'pencairan', 'title' => 'Pengajuan Pencairan', 'is_active' => true]);
        $mahasiswa = $this->createMahasiswa();

        // Diajukan 18 Oktober 2026 09:00 WIB = 02:00 UTC (waktu "sekarang" di setUp).
        CustomFormSubmission::create([
            'custom_form_id' => $form->id,
            'user_id' => $mahasiswa->id,
            'applicant_name' => $mahasiswa->name,
            'answers' => ['rekening' => '123456'],
            'status' => 'Menunggu Review',
        ]);

        $this->actingAs($mahasiswa)->get(route('dashboard.pencairan'))
            ->assertOk()
            ->assertSee('2026, 09:00 WIB')
            ->assertDontSee('2026, 02:00');

        // Halaman admin sebelumnya membaca kolom yang tidak ada dan selalu menampilkan "sekarang".
        $this->travelToWib('2026-10-19 15:45:00');
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin)->get(route('admin.submissions'))
            ->assertOk()
            ->assertSee('2026, 09:00 WIB')
            ->assertDontSee('15:45 WIB');
    }
}
