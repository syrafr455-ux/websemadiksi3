<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\KegiatanStatus;
use App\Enums\PeranSertifikat;
use App\Enums\UserRole;
use App\Models\Kegiatan;
use App\Models\KegiatanPresensi;
use App\Models\KegiatanRegistration;
use App\Models\Sertifikat;
use App\Models\User;
use App\Services\CertificateGeneratorService;
use App\Services\Kegiatan\PresensiAkhirTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class TwoPhaseAttendanceAndCertificateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    protected function createKegiatan(array $attributes = []): Kegiatan
    {
        return Kegiatan::create(array_merge([
            'nama_kegiatan' => 'Workshop Teknologi Modern '.Str::random(5),
            'deskripsi' => 'Deskripsi kegiatan pelatihan dan workshop teknologi.',
            'kategori' => 'workshop',
            'metode_pelaksanaan' => 'offline',
            'sasaran_peserta' => 'umum',
            'tanggal_mulai' => now()->addDays(2),
            'tanggal_selesai' => now()->addDays(2)->addHours(4),
            'lokasi_type' => 'unusa_campus',
            'lokasi_nama' => 'Auditorium Tower Unusa Lt. 9',
            'lokasi_detail' => 'Kampus B UNUSA, Jl. Jemursari',
            'file_panduan_pdf' => 'kegiatan/panduan/panduan.pdf',
            'biaya' => 0,
            'kuota' => 100,
            'status' => KegiatanStatus::Published,
            'is_seat_booking_active' => false,
            'is_submission_active' => false,
        ], $attributes));
    }

    protected function createRegistration(Kegiatan $kegiatan, User $user, array $attributes = []): KegiatanRegistration
    {
        $ticket = $attributes['ticket_code'] ?? $attributes['nomor_tiket'] ?? ('TKT-'.strtoupper(Str::random(8)));

        return KegiatanRegistration::create(array_merge([
            'kegiatan_id' => $kegiatan->id,
            'booker_user_id' => $user->id,
            'participant_user_id' => $user->id,
            'nomor_tiket' => $ticket,
            'ticket_code' => $ticket,
            'participant_name' => $user->name,
            'participant_identity' => $user->nim ?: '224001',
            'participant_phone' => '081234567890',
            'status_pembayaran' => 'free',
            'status_kehadiran' => 'registered',
            'amount_paid' => 0,
        ], $attributes, [
            'nomor_tiket' => $ticket,
            'ticket_code' => $ticket,
        ]));
    }

    /**
     * Test 1: Batas Waktu Pendaftaran (registration_deadline) mencegah checkout jika sudah kadaluarsa.
     */
    public function test_registration_deadline_blocks_checkout_when_expired(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::MahasiswaKipk,
            'verification_status' => 'verified',
        ]);

        $kegiatan = $this->createKegiatan([
            'status' => KegiatanStatus::Published,
            'kuota' => 50,
            'biaya' => 0,
            'registration_deadline' => now()->subHour(), // Sudah tutup
        ]);

        $response = $this->actingAs($user)->post(route('kegiatan.checkout', $kegiatan->id), [
            'delegates' => [
                [
                    'name' => $user->name,
                    'email' => $user->email,
                    'identity' => $user->nim ?: '224001',
                    'phone' => '081234567890',
                ],
            ],
        ]);

        $response->assertSessionHas('error', 'Pendaftaran untuk kegiatan ini telah ditutup.');
        $this->assertDatabaseMissing('kegiatan_registrations', [
            'kegiatan_id' => $kegiatan->id,
            'booker_user_id' => $user->id,
        ]);
    }

    /**
     * Test 2: Batas Waktu Pengumpulan Karya (submission_deadline) mencegah upload jika sudah kadaluarsa.
     */
    public function test_submission_deadline_blocks_upload_when_expired(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::MahasiswaKipk,
            'verification_status' => 'verified',
        ]);

        $kegiatan = $this->createKegiatan([
            'status' => KegiatanStatus::Published,
            'kategori' => 'lomba',
            'is_submission_active' => true,
            'submission_deadline' => now()->subDay(), // Sudah tutup
        ]);

        $registration = $this->createRegistration($kegiatan, $user);

        $dummyFile = UploadedFile::fake()->create('karya_poster.pdf', 1024, 'application/pdf');

        $response = $this->actingAs($user)->post(route('dashboard.kegiatan.upload-karya', $registration->id), [
            'title' => 'Karya Poster Inovatif',
            'description' => 'Deskripsi karya',
            'submission_file' => $dummyFile,
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('kegiatan_submissions', [
            'registration_id' => $registration->id,
        ]);
    }

    /**
     * Test 3: Phase 1 E-Ticket Check-In berhasil memvalidasi kehadiran awal.
     */
    public function test_phase_1_ticket_check_in(): void
    {
        $kegiatan = $this->createKegiatan();
        $user = User::factory()->create();

        $registration = $this->createRegistration($kegiatan, $user, [
            'ticket_code' => 'TKT-TEST-9988',
            'status_kehadiran' => 'registered',
        ]);

        $response = $this->postJson(route('presensi.check-in-ticket'), [
            'ticket_code' => 'TKT-TEST-9988',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'already_checked_in' => false,
                'ticket_code' => 'TKT-TEST-9988',
            ]);

        $registration->refresh();
        $this->assertEquals('checked_in', $registration->status_kehadiran instanceof AttendanceStatus ? $registration->status_kehadiran->value : (string) $registration->status_kehadiran);

        // Check-in kedua kali menandai already_checked_in = true
        $responseSecond = $this->postJson(route('presensi.check-in-ticket'), [
            'ticket_code' => 'TKT-TEST-9988',
        ]);

        $responseSecond->assertOk()
            ->assertJson([
                'already_checked_in' => true,
            ]);
    }

    /**
     * Test 4: Phase 2 Presensi Mandiri dengan Touchscreen Signature Canvas berhasil membuat record presensi dan auto-generate sertifikat.
     */
    public function test_phase_2_self_attendance_with_canvas_signature_and_auto_certificate(): void
    {
        $kegiatan = $this->createKegiatan([
            'nama_kegiatan' => 'Workshop Nasional Kecerdasan Buatan',
            'presensi_akhir_dibuka' => true,
            'presensi_akhir_mulai' => now()->subMinutes(10),
            'presensi_akhir_selesai' => now()->addHour(),
        ]);
        $user = User::factory()->create([
            'name' => 'Ahmad Dahlan',
            'nim' => '2240021055',
            'email' => 'ahmad@unusa.ac.id',
        ]);

        $this->createRegistration($kegiatan, $user, [
            'status_kehadiran' => AttendanceStatus::CheckedIn,
            'checked_in_at' => now()->subMinutes(30),
        ]);

        // Pindai QR layar proyektor untuk mendapatkan izin presensi akhir
        $token = app(PresensiAkhirTokenService::class)->generateToken($kegiatan->id);
        $scanResponse = $this->actingAs($user)->get(route('kegiatan.presensi-akhir.scan', [
            'kegiatan' => $kegiatan->id,
            'token' => $token,
        ]));
        $scanResponse->assertRedirect(route('kegiatan.presensi.show', $kegiatan->id));

        // Halaman form presensi terbuka dengan sukses
        $showResponse = $this->actingAs($user)->get(route('kegiatan.presensi.show', $kegiatan->id));
        $showResponse->assertOk()
            ->assertSee('Presensi Mandiri & Tanda Tangan');

        // Dummy base64 PNG 1x1 pixel
        $dummyBase64Png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $submitResponse = $this->actingAs($user)->post(route('kegiatan.presensi.submit', $kegiatan->id), [
            'nama_lengkap' => 'Ahmad Dahlan',
            'nim' => '2240021055',
            'prodi' => 'S1 Sistem Informasi',
            'email' => 'ahmad@unusa.ac.id',
            'nomor_telepon' => '081234567890',
            'signature' => $dummyBase64Png,
        ]);

        $submitResponse->assertRedirect(route('dashboard.kegiatan.saya'));

        // Verifikasi tabel kegiatan_presensi
        $presensi = KegiatanPresensi::where('kegiatan_id', $kegiatan->id)
            ->where('nim', '2240021055')
            ->first();

        $this->assertNotNull($presensi);
        $this->assertEquals('Ahmad Dahlan', $presensi->nama_lengkap);
        $this->assertNotEmpty($presensi->signature_path);
        Storage::disk('public')->assertExists($presensi->signature_path);

        // Verifikasi tabel sertifikat otomatis terbit
        $sertifikat = Sertifikat::where('kegiatan_id', $kegiatan->id)
            ->where('presensi_id', $presensi->id)
            ->first();

        $this->assertNotNull($sertifikat);
        $this->assertStringStartsWith('CERT-', $sertifikat->nomor_sertifikat);
        $this->assertNotEmpty($sertifikat->document_hash);
        $this->assertNotEmpty($sertifikat->file_pdf_path);
        Storage::disk('public')->assertExists($sertifikat->file_pdf_path);

        // Verifikasi kriptografi hash dokumen
        $this->assertTrue($sertifikat->verifyDocumentHash());
    }

    /**
     * Test 5: Portal Verifikasi Sertifikat Publik (`/verifikasi?code=...`) dan verifikasi file hash SHA-256.
     */
    public function test_public_certificate_verification_by_code_and_file_hash(): void
    {
        $kegiatan = $this->createKegiatan(['nama_kegiatan' => 'Seminar Transformasi Digital']);
        $user = User::factory()->create(['name' => 'Siti Fatimah']);

        $service = new CertificateGeneratorService;
        $sertifikat = $service->generate($kegiatan, $user, PeranSertifikat::Peserta);

        // Verifikasi via GET /verifikasi?code=...
        $verifyGet = $this->get(route('verifikasi.sertifikat', ['code' => $sertifikat->nomor_sertifikat]));
        $verifyGet->assertOk()
            ->assertSee($sertifikat->nomor_sertifikat)
            ->assertSee('Siti Fatimah')
            ->assertSee($sertifikat->document_hash);

        // Verifikasi via POST /verifikasi dengan upload berkas PDF asli
        $pdfContent = Storage::disk('public')->get($sertifikat->file_pdf_path);
        $tempPdf = UploadedFile::fake()->createWithContent('sertifikat_resmi.pdf', $pdfContent);

        $verifyPost = $this->post(route('verifikasi.sertifikat'), [
            'document_file' => $tempPdf,
        ]);

        $verifyPost->assertOk()
            ->assertSee('Integritas Berkas Valid (SHA-256 Cocok)')
            ->assertSee($sertifikat->nomor_sertifikat);

        // Download endpoint
        $downloadResponse = $this->get(route('sertifikat.download', $sertifikat->id));
        $downloadResponse->assertOk();
    }

    /**
     * Test 6: Super Admin mengunggah template sertifikat dan melakukan batch generation.
     */
    public function test_admin_upload_certificate_template_and_batch_generate(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $kegiatan = $this->createKegiatan(['nama_kegiatan' => 'Pelatihan Cloud Computing']);

        // 1. Upload Template
        $templateImage = UploadedFile::fake()->image('template_sertifikat.png', 1920, 1080);
        $uploadResp = $this->actingAs($admin)->postJson(route('admin.kegiatan.certificate-template', $kegiatan->id), [
            'template_file' => $templateImage,
        ]);

        $uploadResp->assertOk()->assertJson(['success' => true]);
        $kegiatan->refresh();
        $this->assertNotEmpty($kegiatan->certificate_template_path);
        Storage::disk('public')->assertExists($kegiatan->certificate_template_path);

        // 2. Simpan Schema Config
        $schemaResp = $this->actingAs($admin)->postJson(route('admin.kegiatan.certificate-schema', $kegiatan->id), [
            'schema_config' => [
                'name_pos_y' => 120,
                'qr_pos_x' => 240,
                'qr_pos_y' => 160,
            ],
        ]);
        $schemaResp->assertOk()->assertJson(['success' => true]);
        $kegiatan->refresh();
        $this->assertEquals(120, $kegiatan->certificate_schema_config['name_pos_y']);

        // 3. Batch Generate
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        KegiatanPresensi::create([
            'kegiatan_id' => $kegiatan->id,
            'user_id' => $user1->id,
            'nama_lengkap' => 'Peserta Satu',
            'nim' => '224001',
            'prodi' => 'TI',
            'email' => 'p1@unusa.ac.id',
            'nomor_telepon' => '0811',
            'signature_path' => 'signatures/dummy1.png',
            'check_in_at' => now(),
        ]);

        KegiatanPresensi::create([
            'kegiatan_id' => $kegiatan->id,
            'user_id' => $user2->id,
            'nama_lengkap' => 'Peserta Dua',
            'nim' => '224002',
            'prodi' => 'SI',
            'email' => 'p2@unusa.ac.id',
            'nomor_telepon' => '0812',
            'signature_path' => 'signatures/dummy2.png',
            'check_in_at' => now(),
        ]);

        $batchResp = $this->actingAs($admin)->postJson(route('admin.kegiatan.certificate-batch', $kegiatan->id));
        $batchResp->assertOk()
            ->assertJson([
                'success' => true,
                'count' => 2,
            ]);

        $this->assertEquals(2, Sertifikat::where('kegiatan_id', $kegiatan->id)->count());
    }
}
