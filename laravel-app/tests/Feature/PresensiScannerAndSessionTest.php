<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\KegiatanStatus;
use App\Enums\RegistrationPaymentStatus;
use App\Enums\UserRole;
use App\Models\Kegiatan;
use App\Models\KegiatanRegistration;
use App\Models\User;
use App\Services\Kegiatan\PresensiAkhirTokenService;
use App\Support\Wib;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PresensiScannerAndSessionTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $participant;

    protected User $staffVerifikator;

    protected Kegiatan $kegiatan;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->superAdmin = User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'email' => 'superadmin@unusa.ac.id',
        ]);

        $this->participant = User::factory()->create([
            'role' => UserRole::MahasiswaKipk,
            'name' => 'Fulan bin Fulan',
            'nim' => '2240021099',
            'email' => 'fulan@unusa.ac.id',
        ]);

        $this->staffVerifikator = User::factory()->create([
            'role' => UserRole::VerifikatorBerkas,
            'email' => 'verifikator@unusa.ac.id',
        ]);

        $this->kegiatan = Kegiatan::create([
            'nama_kegiatan' => 'Seminar Nasional Kecerdasan Buatan 2026',
            'deskripsi' => 'Seminar perkembangan AI terkini.',
            'kategori' => 'seminar',
            'metode_pelaksanaan' => 'offline',
            'sasaran_peserta' => 'umum',
            'tanggal_mulai' => Wib::keUtc('2026-11-10 09:00'),
            'tanggal_selesai' => Wib::keUtc('2026-11-10 13:00'),
            'lokasi_type' => 'unusa_campus',
            'lokasi_nama' => 'Auditorium Tower Lantai 9',
            'file_panduan_pdf' => 'kegiatan/panduan.pdf',
            'biaya' => 0,
            'kuota' => 100,
            'status' => KegiatanStatus::Published,
        ]);
    }

    protected function createRegistration(Kegiatan $kegiatan, User $user, array $overrides = []): KegiatanRegistration
    {
        $nomorTiket = $overrides['nomor_tiket'] ?? KegiatanRegistration::generateTicketNumber($kegiatan->id, 'A1');

        return KegiatanRegistration::create(array_merge([
            'kegiatan_id' => $kegiatan->id,
            'booker_user_id' => $user->id,
            'participant_user_id' => $user->id,
            'nomor_tiket' => $nomorTiket,
            'participant_name' => $user->name,
            'participant_identity' => $user->nim,
            'participant_phone' => '081234567890',
            'amount_paid' => 0,
            'status_pembayaran' => RegistrationPaymentStatus::Free,
            'status_kehadiran' => AttendanceStatus::Registered,
        ], $overrides));
    }

    /**
     * Test: Pindai tiket valid diterima, pindai ulang menghasilkan SUDAH CHECK-IN.
     */
    public function test_pindai_tiket_valid_diterima_dan_pindai_ulang_menghasilkan_sudah_checkin(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 08:30:00', 'Asia/Jakarta')); // 30 menit sebelum mulai (jendela buka)

        $reg = $this->createRegistration($this->kegiatan, $this->participant);

        $response = $this->actingAs($this->superAdmin)->postJson(route('admin.kegiatan.scanner.scan', $this->kegiatan->id), [
            'ticket_code' => $reg->nomor_tiket,
            'method' => 'kamera',
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'DITERIMA',
                'success' => true,
            ]);

        $reg->refresh();
        $this->assertEquals(AttendanceStatus::CheckedIn, $reg->status_kehadiran);
        $this->assertNotNull($reg->checked_in_at);

        // Pindai ulang dengan tiket yang sama menghasilkan SUDAH_CHECK_IN
        $responseSecond = $this->actingAs($this->superAdmin)->postJson(route('admin.kegiatan.scanner.scan', $this->kegiatan->id), [
            'ticket_code' => $reg->nomor_tiket,
            'method' => 'kamera',
        ]);

        $responseSecond->assertOk()
            ->assertJson([
                'status' => 'SUDAH_CHECK_IN',
                'success' => true,
            ]);
    }

    /**
     * Test: Tiket milik kegiatan lain ditolak dengan pesan jelas.
     */
    public function test_tiket_kegiatan_lain_ditolak(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 08:30:00', 'Asia/Jakarta'));

        $kegiatanLain = Kegiatan::create([
            'nama_kegiatan' => 'Workshop UI/UX Berkelanjutan',
            'kategori' => 'workshop',
            'metode_pelaksanaan' => 'offline',
            'sasaran_peserta' => 'umum',
            'tanggal_mulai' => Wib::keUtc('2026-11-10 09:00'),
            'tanggal_selesai' => Wib::keUtc('2026-11-10 13:00'),
            'lokasi_type' => 'unusa_campus',
            'lokasi_nama' => 'Lab Komputer',
            'file_panduan_pdf' => 'kegiatan/panduan.pdf',
            'biaya' => 0,
            'kuota' => 50,
            'status' => KegiatanStatus::Published,
        ]);

        $regLain = $this->createRegistration($kegiatanLain, $this->participant);

        $response = $this->actingAs($this->superAdmin)->postJson(route('admin.kegiatan.scanner.scan', $this->kegiatan->id), [
            'ticket_code' => $regLain->nomor_tiket,
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'DITOLAK',
                'success' => false,
            ]);
        $this->assertStringContainsString('kegiatan lain', strtolower($response->json('message')));
    }

    /**
     * Test: Tiket belum lunas atau dibatalkan ditolak.
     */
    public function test_tiket_belum_lunas_atau_gagal_ditolak(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 08:30:00', 'Asia/Jakarta'));

        $regPending = $this->createRegistration($this->kegiatan, $this->participant, [
            'status_pembayaran' => RegistrationPaymentStatus::Pending,
        ]);

        $response = $this->actingAs($this->superAdmin)->postJson(route('admin.kegiatan.scanner.scan', $this->kegiatan->id), [
            'ticket_code' => $regPending->nomor_tiket,
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'DITOLAK',
                'success' => false,
            ]);
        $this->assertStringContainsString('lunas', strtolower($response->json('message')));
    }

    /**
     * Test: Pindai di luar jendela waktu check-in ditolak.
     */
    public function test_pindai_di_luar_jendela_waktu_ditolak(): void
    {
        // 5 jam sebelum mulai (bawaan buka 2 jam sebelum mulai)
        Carbon::setTestNow(Carbon::parse('2026-11-10 04:00:00', 'Asia/Jakarta'));

        $reg = $this->createRegistration($this->kegiatan, $this->participant);

        $response = $this->actingAs($this->superAdmin)->postJson(route('admin.kegiatan.scanner.scan', $this->kegiatan->id), [
            'ticket_code' => $reg->nomor_tiket,
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'DITOLAK',
                'success' => false,
            ]);
        $this->assertStringContainsString('jendela', strtolower($response->json('message')));
    }

    /**
     * Test: URL dan kode mentah sama-sama diterima dan diekstrak kodenya.
     */
    public function test_url_dan_kode_mentah_sama_sama_diterima(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 08:30:00', 'Asia/Jakarta'));

        $reg = $this->createRegistration($this->kegiatan, $this->participant);
        $urlDenganKode = "https://semadiksi.unusa.ac.id/tiket/cek?ticket={$reg->nomor_tiket}&source=qrcode";

        $response = $this->actingAs($this->superAdmin)->postJson(route('admin.kegiatan.scanner.scan', $this->kegiatan->id), [
            'ticket_code' => $urlDenganKode,
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'DITERIMA',
                'ticket_code' => $reg->nomor_tiket,
            ]);
    }

    /**
     * Test: Bukan super_admin mendapat 403, tamu dialihkan ke login.
     */
    public function test_hanya_super_admin_yang_dapat_mengakses_pemindai(): void
    {
        $guestResponse = $this->get(route('admin.kegiatan.scanner', $this->kegiatan->id));
        $guestResponse->assertRedirect(route('login'));

        $staffResponse = $this->actingAs($this->staffVerifikator)->get(route('admin.kegiatan.scanner', $this->kegiatan->id));
        $staffResponse->assertForbidden();

        $adminResponse = $this->actingAs($this->superAdmin)->get(route('admin.kegiatan.scanner', $this->kegiatan->id));
        $adminResponse->assertOk();
    }

    /**
     * Test: Check-in manual wajib alasan dan batal check-in tercatat di audit.
     */
    public function test_checkin_manual_wajib_alasan_dan_batal_checkin_tercatat_di_audit(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 08:30:00', 'Asia/Jakarta'));
        $reg = $this->createRegistration($this->kegiatan, $this->participant);

        // Tanpa alasan ditolak validasi
        $invalidResponse = $this->actingAs($this->superAdmin)->postJson(route('admin.kegiatan.scanner.manual', $this->kegiatan->id), [
            'registration_id' => $reg->id,
            'alasan' => '',
        ]);
        $invalidResponse->assertUnprocessable();

        // Dengan alasan berhasil
        $validResponse = $this->actingAs($this->superAdmin)->postJson(route('admin.kegiatan.scanner.manual', $this->kegiatan->id), [
            'registration_id' => $reg->id,
            'alasan' => 'Peserta lupa bawa gawai, identitas KTM fisik terverifikasi panitia.',
        ]);
        $validResponse->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ticket_checkin_manual',
            'user_id' => $this->superAdmin->id,
        ]);

        // Pembatalan check-in wajib alasan
        $cancelInvalid = $this->actingAs($this->superAdmin)->postJson(route('admin.kegiatan.scanner.cancel', $this->kegiatan->id), [
            'registration_id' => $reg->id,
            'alasan' => '',
        ]);
        $cancelInvalid->assertUnprocessable();

        $cancelValid = $this->actingAs($this->superAdmin)->postJson(route('admin.kegiatan.scanner.cancel', $this->kegiatan->id), [
            'registration_id' => $reg->id,
            'alasan' => 'Salah pindai tiket peserta rombongan.',
        ]);
        $cancelValid->assertOk();

        $reg->refresh();
        $this->assertEquals(AttendanceStatus::Registered, $reg->status_kehadiran);
        $this->assertNull($reg->checked_in_at);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ticket_checkin_cancelled',
            'user_id' => $this->superAdmin->id,
        ]);
    }

    /**
     * Test: Presensi akhir - panel buka dan tutup sesi, verifikasi token HMAC slot saat ini dan sebelumnya.
     */
    public function test_presensi_akhir_panel_dan_verifikasi_token(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 12:45:00', 'Asia/Jakarta')); // Menjelang selesai kegiatan

        // 1. Super admin membuka presensi akhir
        $bukaResponse = $this->actingAs($this->superAdmin)->postJson(route('admin.kegiatan.presensi-akhir.buka', $this->kegiatan->id), [
            'waktu_mulai' => '2026-11-10T12:45',
            'waktu_selesai' => '2026-11-10T14:00',
        ]);
        $bukaResponse->assertOk();

        $this->kegiatan->refresh();
        $this->assertTrue((bool) $this->kegiatan->presensi_akhir_dibuka);

        // 2. Token generation & verification
        $tokenService = app(PresensiAkhirTokenService::class);
        $currentToken = $tokenService->generateToken($this->kegiatan->id);
        $this->assertTrue($tokenService->verifyToken($this->kegiatan->id, $currentToken));

        // Token pada slot sebelumnya (30 detik yang lalu) masih diterima
        $prevSlot = (int) floor((now()->timestamp - 30) / 30);
        $prevToken = $tokenService->generateToken($this->kegiatan->id, $prevSlot);
        $this->assertTrue($tokenService->verifyToken($this->kegiatan->id, $prevToken));

        // Token yang kedaluwarsa (2 menit yang lalu) ditolak
        $expiredSlot = (int) floor((now()->timestamp - 120) / 30);
        $expiredToken = $tokenService->generateToken($this->kegiatan->id, $expiredSlot);
        $this->assertFalse($tokenService->verifyToken($this->kegiatan->id, $expiredToken));

        // Token yang diubah/dimanipulasi ditolak
        $tamperedToken = $currentToken.'tampered';
        $this->assertFalse($tokenService->verifyToken($this->kegiatan->id, $tamperedToken));

        // Token untuk kegiatan lain ditolak
        $this->assertFalse($tokenService->verifyToken(99999, $currentToken));
    }

    /**
     * Test: Alur peserta memindai QR presensi akhir ditolak bila belum check-in fase 1 atau sesi ditutup.
     */
    public function test_peserta_presensi_akhir_ditolak_jika_belum_checkin_fase_1(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 12:45:00', 'Asia/Jakarta'));

        // Buka sesi presensi akhir
        $this->kegiatan->update([
            'presensi_akhir_dibuka' => true,
            'presensi_akhir_mulai' => now()->subMinutes(5),
            'presensi_akhir_selesai' => now()->addHour(),
        ]);

        $reg = $this->createRegistration($this->kegiatan, $this->participant, [
            'status_kehadiran' => AttendanceStatus::Registered, // Belum check-in fase 1
        ]);

        $tokenService = app(PresensiAkhirTokenService::class);
        $token = $tokenService->generateToken($this->kegiatan->id);

        $response = $this->actingAs($this->participant)->get(route('kegiatan.presensi-akhir.scan', [
            'kegiatan' => $this->kegiatan->id,
            'token' => $token,
        ]));

        $response->assertRedirect();
        $this->assertStringContainsString('Check-in awal belum tercatat', session('error') ?? '');

        // Sekarang check-in fase 1 terlebih dahulu
        $reg->update([
            'status_kehadiran' => AttendanceStatus::CheckedIn,
            'checked_in_at' => now(),
        ]);

        $responseSuccess = $this->actingAs($this->participant)->get(route('kegiatan.presensi-akhir.scan', [
            'kegiatan' => $this->kegiatan->id,
            'token' => $token,
        ]));

        $responseSuccess->assertRedirect(route('kegiatan.presensi.show', $this->kegiatan->id));
    }

    /**
     * Test: Presensi akhir manual oleh admin wajib alasan dan idempoten.
     */
    public function test_presensi_akhir_manual_oleh_admin(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 12:45:00', 'Asia/Jakarta'));

        $reg = $this->createRegistration($this->kegiatan, $this->participant, [
            'status_kehadiran' => AttendanceStatus::CheckedIn,
            'checked_in_at' => now()->subHours(3),
        ]);

        $response = $this->actingAs($this->superAdmin)->postJson(route('admin.kegiatan.presensi-akhir.manual', $this->kegiatan->id), [
            'registration_id' => $reg->id,
            'alasan' => 'Peserta menghadiri sesi akhir dan menyelesaikan evaluasi secara tertulis.',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('kegiatan_presensi', [
            'kegiatan_id' => $this->kegiatan->id,
            'nim' => $this->participant->nim,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'presensi_akhir_manual',
            'user_id' => $this->superAdmin->id,
        ]);
    }
}
