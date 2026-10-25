<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\KegiatanStatus;
use App\Enums\RegistrationPaymentStatus;
use App\Enums\UserRole;
use App\Models\Kegiatan;
use App\Models\KegiatanPresensi;
use App\Models\KegiatanRegistration;
use App\Models\Sertifikat;
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

    protected Kegiatan $kegiatanTidakDiikuti;

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

        $this->kegiatanTidakDiikuti = Kegiatan::create([
            'nama_kegiatan' => 'Workshop UI/UX Terapan',
            'deskripsi' => 'Deskripsi.',
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
     * 1. Dropdown pada /dashboard/absensi HANYA memuat kegiatan yang diikuti peserta
     * dengan status pembayaran terkonfirmasi (Free atau Paid).
     * Kegiatan yang belum didaftar peserta TIDAK boleh muncul di dropdown.
     */
    public function test_dropdown_absensi_hanya_memuat_kegiatan_yang_diikuti_dengan_pembayaran_terkonfirmasi(): void
    {
        $response = $this->actingAs($this->peserta)->get(route('dashboard.absensi'));

        $response->assertOk();
        // Kegiatan yang diikuti harus ada di dalam dropdown
        $response->assertSee('Seminar AI Modern 2026');
        // Kegiatan yang TIDAK diikuti TIDAK boleh ada di dalam opsi dropdown
        $response->assertDontSee('Workshop UI/UX Terapan');
    }

    /**
     * 2. Bila tidak ada kegiatan yang memenuhi, halaman /dashboard/absensi menampilkan
     * keadaan kosong "Belum ada kegiatan yang sedang membuka presensi untuk Anda" dengan tautan ke Kegiatan Saya.
     */
    public function test_halaman_absensi_menampilkan_keadaan_kosong_bila_tidak_ada_kegiatan_yang_diikuti(): void
    {
        $response = $this->actingAs($this->userLain)->get(route('dashboard.absensi'));

        $response->assertOk();
        $response->assertSee('Belum ada kegiatan yang sedang membuka presensi untuk Anda');
        $response->assertSee(route('dashboard.kegiatan.saya'));
    }

    /**
     * 3. Pengiriman presensi untuk kegiatan yang TIDAK diikuti ditolak server (IDOR / manual ID).
     */
    public function test_kirim_presensi_kegiatan_yang_tidak_diikuti_ditolak_server_idor(): void
    {
        $fakeImage = UploadedFile::fake()->image('selfie.jpg', 600, 600);

        $response = $this->actingAs($this->peserta)->post(route('dashboard.absensi.store'), [
            'activity_id' => $this->kegiatanTidakDiikuti->id,
            'proof_image' => $fakeImage,
            'location_name' => 'Auditorium Tower',
        ]);

        $this->assertTrue($response->isRedirection() || $response->status() === 422 || $response->status() === 403);
        $this->assertDatabaseMissing('kegiatan_presensi', [
            'kegiatan_id' => $this->kegiatanTidakDiikuti->id,
            'user_id' => $this->peserta->id,
        ]);
    }

    /**
     * 4. Registrasi belum lunas (Pending) atau dibatalkan (Cancelled) ditolak server.
     */
    public function test_kirim_presensi_registrasi_pending_atau_dibatalkan_ditolak_server(): void
    {
        // Ubah pembayaran menjadi pending
        $this->registrasiLunas->update([
            'status_pembayaran' => RegistrationPaymentStatus::Pending,
        ]);

        $fakeImage = UploadedFile::fake()->image('selfie.jpg', 600, 600);

        $responsePending = $this->actingAs($this->peserta)->post(route('dashboard.absensi.store'), [
            'activity_id' => $this->kegiatanDiikuti->id,
            'proof_image' => $fakeImage,
            'location_name' => 'Auditorium Tower',
        ]);

        $this->assertTrue($responsePending->isRedirection() || $responsePending->status() === 422 || $responsePending->status() === 403);
        $this->assertDatabaseMissing('kegiatan_presensi', [
            'kegiatan_id' => $this->kegiatanDiikuti->id,
            'user_id' => $this->peserta->id,
        ]);

        // Ubah pembayaran menjadi kedaluwarsa/batal
        $this->registrasiLunas->update([
            'status_pembayaran' => RegistrationPaymentStatus::Expired,
        ]);

        $responseCancelled = $this->actingAs($this->peserta)->post(route('dashboard.absensi.store'), [
            'activity_id' => $this->kegiatanDiikuti->id,
            'proof_image' => $fakeImage,
            'location_name' => 'Auditorium Tower',
        ]);

        $this->assertTrue($responseCancelled->isRedirection() || $responseCancelled->status() === 422 || $responseCancelled->status() === 403);
        $this->assertDatabaseMissing('kegiatan_presensi', [
            'kegiatan_id' => $this->kegiatanDiikuti->id,
            'user_id' => $this->peserta->id,
        ]);
    }

    /**
     * 5. Kegiatan draft ditolak server.
     */
    public function test_kirim_presensi_kegiatan_draft_ditolak_server(): void
    {
        $this->kegiatanDiikuti->update(['status' => KegiatanStatus::Draft]);

        $fakeImage = UploadedFile::fake()->image('selfie.jpg', 600, 600);

        $response = $this->actingAs($this->peserta)->post(route('dashboard.absensi.store'), [
            'activity_id' => $this->kegiatanDiikuti->id,
            'proof_image' => $fakeImage,
            'location_name' => 'Auditorium Tower',
        ]);

        $this->assertTrue($response->isRedirection() || $response->status() === 422 || $response->status() === 403);
        $this->assertDatabaseMissing('kegiatan_presensi', [
            'kegiatan_id' => $this->kegiatanDiikuti->id,
            'user_id' => $this->peserta->id,
        ]);
    }

    /**
     * 6. Presensi ganda untuk kegiatan yang sama ditolak server.
     */
    public function test_kirim_presensi_ganda_untuk_kegiatan_yang_sama_ditolak_server(): void
    {
        $fakeImage1 = UploadedFile::fake()->image('selfie1.jpg', 600, 600);
        $fakeImage2 = UploadedFile::fake()->image('selfie2.jpg', 600, 600);

        // Presensi pertama: sukses
        $res1 = $this->actingAs($this->peserta)->post(route('dashboard.absensi.store'), [
            'activity_id' => $this->kegiatanDiikuti->id,
            'proof_image' => $fakeImage1,
            'location_name' => 'Auditorium Tower',
        ]);
        $res1->assertSessionHas('success');

        $this->assertDatabaseHas('kegiatan_presensi', [
            'kegiatan_id' => $this->kegiatanDiikuti->id,
            'user_id' => $this->peserta->id,
        ]);

        // Presensi kedua untuk kegiatan yang sama: ditolak
        $res2 = $this->actingAs($this->peserta)->post(route('dashboard.absensi.store'), [
            'activity_id' => $this->kegiatanDiikuti->id,
            'proof_image' => $fakeImage2,
            'location_name' => 'Auditorium Tower',
        ]);

        $this->assertTrue($res2->isRedirection() || $res2->status() === 422);
        // Presensi di DB tetap hanya ada 1 baris
        $this->assertEquals(1, KegiatanPresensi::where('kegiatan_id', $this->kegiatanDiikuti->id)->where('user_id', $this->peserta->id)->count());
    }

    /**
     * 7. Formulir /dashboard/absensi TIDAK memicu pembuatan sertifikat otomatis.
     */
    public function test_kirim_presensi_dashboard_tidak_memicu_pembuatan_sertifikat(): void
    {
        $fakeImage = UploadedFile::fake()->image('selfie.jpg', 600, 600);

        $res = $this->actingAs($this->peserta)->post(route('dashboard.absensi.store'), [
            'activity_id' => $this->kegiatanDiikuti->id,
            'proof_image' => $fakeImage,
            'location_name' => 'Auditorium Tower',
        ]);

        $res->assertSessionHas('success');

        // Pastikan tidak ada row sertifikat yang dibuat
        $this->assertDatabaseMissing('sertifikat', [
            'kegiatan_id' => $this->kegiatanDiikuti->id,
            'user_id' => $this->peserta->id,
        ]);
    }
}
