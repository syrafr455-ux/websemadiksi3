<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\KegiatanStatus;
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

class PresensiAkhirQrLockTest extends TestCase
{
    use RefreshDatabase;

    protected User $participant;

    protected Kegiatan $kegiatan;

    protected KegiatanRegistration $registration;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Carbon::setTestNow(Carbon::parse('2026-11-10 12:45:00', 'Asia/Jakarta'));

        $this->participant = User::factory()->create([
            'role' => UserRole::MahasiswaKipk,
            'name' => 'Fulan bin Fulan',
            'nim' => '2240021099',
            'email' => 'fulan@unusa.ac.id',
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
            'lokasi_nama' => 'Auditorium Tower Unusa',
            'file_panduan_pdf' => 'kegiatan/panduan.pdf',
            'biaya' => 0,
            'kuota' => 100,
            'status' => KegiatanStatus::Published,
            'presensi_akhir_dibuka' => true,
            'presensi_akhir_mulai' => now()->subMinutes(15),
            'presensi_akhir_selesai' => now()->addMinutes(45),
        ]);

        $this->registration = KegiatanRegistration::create([
            'kegiatan_id' => $this->kegiatan->id,
            'booker_user_id' => $this->participant->id,
            'participant_user_id' => $this->participant->id,
            'nomor_tiket' => 'TKT-TEST-001',
            'ticket_code' => 'TKT-TEST-001',
            'participant_name' => $this->participant->name,
            'participant_identity' => $this->participant->nim,
            'participant_phone' => '081234567890',
            'status_pembayaran' => 'free',
            'status_kehadiran' => AttendanceStatus::CheckedIn,
            'checked_in_at' => now()->subHours(2),
        ]);
    }

    /**
     * 1. Tanpa izin QR ditolak:
     * Peserta dengan tiket sah dan check-in fase 1, saat sesi presensi akhir terbuka,
     * membuka lalu mengirim presensi lewat rute lama tanpa melewati pendaratan QR harus ditolak
     * dan dialihkan ke Kegiatan Saya dengan pesan "Pindai QR di layar acara untuk presensi akhir."
     */
    public function test_tanpa_izin_qr_ditolak(): void
    {
        // A. Mencoba membuka form presensi akhir lewat rute lama tanpa izin pendaratan QR
        $showResponse = $this->actingAs($this->participant)
            ->get(route('kegiatan.presensi.show', $this->kegiatan->id));

        $showResponse->assertRedirect(route('dashboard.kegiatan.saya'));
        $showResponse->assertSessionHas('error', 'Pindai QR di layar acara untuk presensi akhir.');

        // B. Mencoba mengirim presensi lewat rute lama tanpa izin pendaratan QR
        $dummySig = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        $submitResponse = $this->actingAs($this->participant)
            ->post(route('kegiatan.presensi.submit', $this->kegiatan->id), [
                'nama_lengkap' => $this->participant->name,
                'nim' => $this->participant->nim,
                'prodi' => 'S1 Sistem Informasi',
                'email' => $this->participant->email,
                'nomor_telepon' => '081234567890',
                'signature' => $dummySig,
            ]);

        $submitResponse->assertRedirect(route('dashboard.kegiatan.saya'));
        $submitResponse->assertSessionHas('error', 'Pindai QR di layar acara untuk presensi akhir.');

        $this->assertDatabaseMissing('kegiatan_presensi', [
            'kegiatan_id' => $this->kegiatan->id,
            'nim' => $this->participant->nim,
        ]);
    }

    /**
     * 2. Izin dari token valid berhasil:
     * Melewati pendaratan QR layar dengan token valid -> membuka form 200 OK -> submit sukses.
     */
    public function test_izin_dari_token_valid_berhasil(): void
    {
        $tokenService = app(PresensiAkhirTokenService::class);
        $token = $tokenService->generateToken($this->kegiatan->id);

        // Pendaratan QR
        $scanResponse = $this->actingAs($this->participant)
            ->get(route('kegiatan.presensi-akhir.scan', [
                'kegiatan' => $this->kegiatan->id,
                'token' => $token,
            ]));

        $scanResponse->assertRedirect(route('kegiatan.presensi.show', $this->kegiatan->id));
        $scanResponse->assertSessionHas('presensi_akhir_unlocked_'.$this->kegiatan->id);

        // Buka form presensi
        $showResponse = $this->actingAs($this->participant)
            ->get(route('kegiatan.presensi.show', $this->kegiatan->id));
        $showResponse->assertOk()
            ->assertSee('Presensi Mandiri & Tanda Tangan');

        // Kirim presensi
        $dummySig = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        $submitResponse = $this->actingAs($this->participant)
            ->post(route('kegiatan.presensi.submit', $this->kegiatan->id), [
                'nama_lengkap' => $this->participant->name,
                'nim' => $this->participant->nim,
                'prodi' => 'S1 Sistem Informasi',
                'email' => $this->participant->email,
                'nomor_telepon' => '081234567890',
                'signature' => $dummySig,
            ]);

        $submitResponse->assertRedirect(route('dashboard.kegiatan.saya'));
        $submitResponse->assertSessionHas('success');

        $this->assertDatabaseHas('kegiatan_presensi', [
            'kegiatan_id' => $this->kegiatan->id,
            'nim' => $this->participant->nim,
        ]);

        // Sertifikat tidak terbit otomatis pada presensi mandiri (Tugas T2a Bagian 4)
        $this->assertDatabaseMissing('sertifikat', [
            'kegiatan_id' => $this->kegiatan->id,
            'user_id' => $this->participant->id,
        ]);
    }

    /**
     * 3. Izin kedaluwarsa atau milik kegiatan lain ditolak.
     */
    public function test_izin_kedaluwarsa_atau_milik_kegiatan_lain_ditolak(): void
    {
        // Kasus A: Izin kedaluwarsa (expires_at sudah lewat)
        session()->put("presensi_akhir_unlocked_{$this->kegiatan->id}", [
            'kegiatan_id' => $this->kegiatan->id,
            'expires_at' => now()->subMinute()->timestamp,
        ]);

        $responseExpired = $this->actingAs($this->participant)
            ->get(route('kegiatan.presensi.show', $this->kegiatan->id));

        $responseExpired->assertRedirect(route('dashboard.kegiatan.saya'));
        $responseExpired->assertSessionHas('error', 'Pindai QR di layar acara untuk presensi akhir.');

        // Kasus B: Izin milik kegiatan lain
        $kegiatanLain = Kegiatan::create([
            'nama_kegiatan' => 'Kegiatan Lain',
            'deskripsi' => 'Deskripsi.',
            'kategori' => 'seminar',
            'metode_pelaksanaan' => 'offline',
            'sasaran_peserta' => 'umum',
            'tanggal_mulai' => Wib::keUtc('2026-11-10 09:00'),
            'tanggal_selesai' => Wib::keUtc('2026-11-10 13:00'),
            'lokasi_type' => 'unusa_campus',
            'lokasi_nama' => 'Auditorium',
            'file_panduan_pdf' => 'kegiatan/panduan.pdf',
            'biaya' => 0,
            'kuota' => 100,
            'status' => KegiatanStatus::Published,
            'presensi_akhir_dibuka' => true,
            'presensi_akhir_mulai' => now()->subMinutes(15),
            'presensi_akhir_selesai' => now()->addMinutes(45),
        ]);

        // Berikan izin untuk kegiatanLain di session
        session()->forget("presensi_akhir_unlocked_{$this->kegiatan->id}");
        session()->put("presensi_akhir_unlocked_{$kegiatanLain->id}", [
            'kegiatan_id' => $kegiatanLain->id,
            'expires_at' => now()->addMinutes(15)->timestamp,
        ]);

        $responseDifferent = $this->actingAs($this->participant)
            ->get(route('kegiatan.presensi.show', $this->kegiatan->id));

        $responseDifferent->assertRedirect(route('dashboard.kegiatan.saya'));
        $responseDifferent->assertSessionHas('error', 'Pindai QR di layar acara untuk presensi akhir.');
    }

    /**
     * 4. Izin tidak bisa dipakai dua kali:
     * Setelah presensi tersimpan, izin dihapus dari sesi sehingga pengiriman kedua ditolak.
     */
    public function test_izin_tidak_bisa_dipakai_dua_kali(): void
    {
        // Set izin valid di sesi
        session()->put("presensi_akhir_unlocked_{$this->kegiatan->id}", [
            'kegiatan_id' => $this->kegiatan->id,
            'expires_at' => now()->addMinutes(15)->timestamp,
        ]);

        $dummySig = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        // Pengiriman pertama: berhasil
        $submit1 = $this->actingAs($this->participant)
            ->post(route('kegiatan.presensi.submit', $this->kegiatan->id), [
                'nama_lengkap' => $this->participant->name,
                'nim' => $this->participant->nim,
                'prodi' => 'S1 Sistem Informasi',
                'email' => $this->participant->email,
                'nomor_telepon' => '081234567890',
                'signature' => $dummySig,
            ]);

        $submit1->assertRedirect(route('dashboard.kegiatan.saya'));
        $submit1->assertSessionMissing("presensi_akhir_unlocked_{$this->kegiatan->id}");

        // Pengiriman kedua dengan form resubmit: ditolak karena izin sudah dihapus
        $submit2 = $this->actingAs($this->participant)
            ->post(route('kegiatan.presensi.submit', $this->kegiatan->id), [
                'nama_lengkap' => $this->participant->name,
                'nim' => $this->participant->nim,
                'prodi' => 'S1 Sistem Informasi',
                'email' => $this->participant->email,
                'nomor_telepon' => '081234567890',
                'signature' => $dummySig,
            ]);

        $submit2->assertRedirect(route('dashboard.kegiatan.saya'));
        $submit2->assertSessionHas('error', 'Pindai QR di layar acara untuk presensi akhir.');
    }

    /**
     * 5. Sesi tertutup ditolak.
     */
    public function test_sesi_tertutup_ditolak(): void
    {
        $this->kegiatan->update([
            'presensi_akhir_dibuka' => false,
            'presensi_akhir_selesai' => now()->subMinute(),
        ]);

        session()->put("presensi_akhir_unlocked_{$this->kegiatan->id}", [
            'kegiatan_id' => $this->kegiatan->id,
            'expires_at' => now()->addMinutes(15)->timestamp,
        ]);

        $showResponse = $this->actingAs($this->participant)
            ->get(route('kegiatan.presensi.show', $this->kegiatan->id));

        $showResponse->assertRedirect(route('dashboard.kegiatan.saya'));
        $showResponse->assertSessionHas('error', 'Sesi presensi akhir belum dibuka atau sudah ditutup oleh panitia.');
    }

    /**
     * 6. Belum check-in fase 1 ditolak dengan pesan yang sama seperti jalur QR.
     */
    public function test_belum_checkin_fase_1_ditolak_dengan_pesan_yang_sama_seperti_jalur_qr(): void
    {
        $this->registration->update([
            'status_kehadiran' => AttendanceStatus::Registered,
            'checked_in_at' => null,
        ]);

        session()->put("presensi_akhir_unlocked_{$this->kegiatan->id}", [
            'kegiatan_id' => $this->kegiatan->id,
            'expires_at' => now()->addMinutes(15)->timestamp,
        ]);

        $showResponse = $this->actingAs($this->participant)
            ->get(route('kegiatan.presensi.show', $this->kegiatan->id));

        $showResponse->assertRedirect(route('dashboard.kegiatan.saya'));
        $showResponse->assertSessionHas('error', 'Check-in awal belum tercatat. Hubungi panitia.');

        $dummySig = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        $submitResponse = $this->actingAs($this->participant)
            ->post(route('kegiatan.presensi.submit', $this->kegiatan->id), [
                'nama_lengkap' => $this->participant->name,
                'nim' => $this->participant->nim,
                'prodi' => 'S1 Sistem Informasi',
                'email' => $this->participant->email,
                'nomor_telepon' => '081234567890',
                'signature' => $dummySig,
            ]);

        $submitResponse->assertRedirect(route('dashboard.kegiatan.saya'));
        $submitResponse->assertSessionHas('error', 'Check-in awal belum tercatat. Hubungi panitia.');
    }

    /**
     * 7. Presensi manual admin tetap jalan.
     */
    public function test_presensi_manual_admin_tetap_jalan(): void
    {
        $superAdmin = User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'email' => 'admin@unusa.ac.id',
        ]);

        $response = $this->actingAs($superAdmin)
            ->postJson(route('admin.kegiatan.presensi-akhir.manual', $this->kegiatan->id), [
                'registration_id' => $this->registration->id,
                'alasan' => 'Peserta lupa bawa HP, tanda tangan manual di kertas panitia',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('kegiatan_presensi', [
            'kegiatan_id' => $this->kegiatan->id,
            'registration_id' => $this->registration->id,
        ]);
    }
}
