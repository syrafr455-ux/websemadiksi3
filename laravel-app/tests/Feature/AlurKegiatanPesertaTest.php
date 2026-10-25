<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\KegiatanStatus;
use App\Enums\SeatStatus;
use App\Enums\UserRole;
use App\Models\Kegiatan;
use App\Models\KegiatanPresensi;
use App\Models\KegiatanRegistration;
use App\Models\KegiatanSeat;
use App\Models\Sertifikat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AlurKegiatanPesertaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /**
     * Reproduksi Bug (b):
     * Kegiatan yang dibuat super admin muncul di landing page, tetapi sebelumnya TIDAK muncul
     * di daftar kegiatan pengguna yang sudah login (/dashboard/kegiatan & /dashboard).
     */
    public function test_bug_b_kegiatan_dibuat_super_admin_muncul_di_landing_dan_di_daftar_kegiatan_pengguna_login(): void
    {
        $superAdmin = User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'is_blocked' => false,
        ]);

        $mahasiswaKipk = User::factory()->create([
            'role' => UserRole::MahasiswaKipk,
            'verification_status' => 'Verified',
            'is_blocked' => false,
            'is_unusa' => true,
        ]);

        $userUmumLuar = User::factory()->create([
            'role' => UserRole::UserUmum,
            'email' => 'peserta.luar@gmail.com',
            'is_unusa' => false,
            'is_blocked' => false,
        ]);

        // 1. Kegiatan umum diterbitkan super admin
        $kegiatanUmum = Kegiatan::create([
            'nama_kegiatan' => 'Seminar Teknologi Masa Depan 2026',
            'deskripsi' => 'Pengenalan teknologi terkini AI dan Cloud Computing.',
            'kategori' => 'seminar',
            'metode_pelaksanaan' => 'offline',
            'sasaran_peserta' => 'umum',
            'tanggal_mulai' => now()->addDays(3),
            'tanggal_selesai' => now()->addDays(3)->addHours(3),
            'lokasi_type' => 'unusa_campus',
            'lokasi_nama' => 'Auditorium Tower Unusa',
            'file_panduan_pdf' => 'kegiatan/panduan.pdf',
            'biaya' => 0,
            'kuota' => 100,
            'status' => KegiatanStatus::Published,
            'is_seat_booking_active' => false,
        ]);

        // 2. Kegiatan internal UNUSA
        $kegiatanInternal = Kegiatan::create([
            'nama_kegiatan' => 'Pelatihan Internal Kampus UNUSA',
            'deskripsi' => 'Khusus civitas akademika UNUSA.',
            'kategori' => 'pelatihan',
            'metode_pelaksanaan' => 'online',
            'sasaran_peserta' => 'internal_unusa',
            'tanggal_mulai' => now()->addDays(4),
            'lokasi_type' => 'online',
            'lokasi_nama' => 'Zoom UNUSA',
            'file_panduan_pdf' => 'kegiatan/panduan.pdf',
            'biaya' => 0,
            'kuota' => 50,
            'status' => KegiatanStatus::Published,
            'is_seat_booking_active' => false,
        ]);

        // 3. Kegiatan draft
        $kegiatanDraft = Kegiatan::create([
            'nama_kegiatan' => 'Rencana Lomba Inovasi Mahasiswa',
            'deskripsi' => 'Masih dalam penyusunan proposal.',
            'kategori' => 'lomba',
            'metode_pelaksanaan' => 'hybrid',
            'sasaran_peserta' => 'umum',
            'tanggal_mulai' => now()->addDays(10),
            'lokasi_type' => 'unusa_campus',
            'lokasi_nama' => 'Ruang Rapat',
            'file_panduan_pdf' => 'kegiatan/panduan.pdf',
            'biaya' => 0,
            'kuota' => 20,
            'status' => KegiatanStatus::Draft,
            'is_seat_booking_active' => false,
        ]);

        // Verifikasi Landing Page
        $resLanding = $this->get(route('home'));
        $resLanding->assertOk();
        $resLanding->assertSee('Seminar Teknologi Masa Depan 2026');
        $resLanding->assertDontSee('Rencana Lomba Inovasi Mahasiswa'); // Draft tidak boleh muncul di landing

        // Verifikasi Mahasiswa KIP-K login melihat daftar kegiatan
        $resMhsKegiatan = $this->actingAs($mahasiswaKipk)->get(route('dashboard.kegiatan'));
        $resMhsKegiatan->assertOk();
        $resMhsKegiatan->assertSee('Seminar Teknologi Masa Depan 2026');
        $resMhsKegiatan->assertSee('Pelatihan Internal Kampus UNUSA');
        $resMhsKegiatan->assertDontSee('Rencana Lomba Inovasi Mahasiswa'); // Draft tidak boleh muncul

        // Verifikasi Mahasiswa KIP-K login melihat ringkasan dashboard
        $resMhsDashboard = $this->actingAs($mahasiswaKipk)->get(route('dashboard.index'));
        $resMhsDashboard->assertOk();
        $resMhsDashboard->assertSee('Seminar Teknologi Masa Depan 2026');

        // Verifikasi Peserta umum luar kampus tidak melihat kegiatan khusus internal UNUSA
        $resUmumKegiatan = $this->actingAs($userUmumLuar)->get(route('dashboard.kegiatan'));
        $resUmumKegiatan->assertOk();
        $resUmumKegiatan->assertSee('Seminar Teknologi Masa Depan 2026');
        $resUmumKegiatan->assertDontSee('Pelatihan Internal Kampus UNUSA');
        $resUmumKegiatan->assertDontSee('Rencana Lomba Inovasi Mahasiswa');
    }

    /**
     * Reproduksi Bug (a):
     * Setelah mahasiswa mendaftar kegiatan, kegiatan harus muncul di Kegiatan Saya
     * dan menampilkan status tiket dan kursi.
     */
    public function test_bug_a_pendaftaran_kegiatan_muncul_di_kegiatan_saya_dan_diarahkan_dengan_benar(): void
    {
        $mahasiswa = User::factory()->create([
            'role' => UserRole::MahasiswaKipk,
            'verification_status' => 'Verified',
            'is_blocked' => false,
            'is_unusa' => true,
        ]);

        $kegiatan = Kegiatan::create([
            'nama_kegiatan' => 'Workshop AI & Data Science 2026',
            'deskripsi' => 'Belajar machine learning terapan.',
            'kategori' => 'workshop',
            'metode_pelaksanaan' => 'offline',
            'sasaran_peserta' => 'umum',
            'tanggal_mulai' => now()->addDays(5),
            'lokasi_type' => 'unusa_campus',
            'lokasi_nama' => 'Lab Komputer Lt. 3',
            'file_panduan_pdf' => 'kegiatan/panduan.pdf',
            'biaya' => 0,
            'kuota' => 50,
            'status' => KegiatanStatus::Published,
            'is_seat_booking_active' => false,
        ]);

        // Mahasiswa mendaftar via checkout
        $resCheckout = $this->actingAs($mahasiswa)->post(route('kegiatan.checkout', $kegiatan->id), [
            'delegates' => [
                [
                    'name' => $mahasiswa->name,
                    'email' => $mahasiswa->email,
                    'identity' => $mahasiswa->nim ?: '224001',
                    'phone' => '081234567890',
                ],
            ],
        ]);

        $resCheckout->assertRedirect(route('dashboard.kegiatan.saya'));

        // Cek halaman Kegiatan Saya
        $resSaya = $this->actingAs($mahasiswa)->get(route('dashboard.kegiatan.saya'));
        $resSaya->assertOk();
        $resSaya->assertSee('Workshop AI & Data Science 2026', false);
        $resSaya->assertDontSee('Belum ada riwayat pendaftaran kegiatan');

        $this->assertDatabaseHas('kegiatan_registrations', [
            'kegiatan_id' => $kegiatan->id,
            'participant_user_id' => $mahasiswa->id,
            'participant_name' => $mahasiswa->name,
        ]);
    }

    /**
     * Tes Ujung ke Ujung Alur Lengkap:
     * 1. Super admin membuat dan menerbitkan kegiatan berkursi.
     * 2. Muncul di landing page dan daftar pengguna login.
     * 3. Peserta mendaftar dengan memilih kursi (gratis & berkursi).
     * 4. Diarahkan ke Kegiatan Saya yang menampilkan kegiatan beserta tiket/kursi.
     * 5. Check-in fase 1 (validasi tiket).
     * 6. Presensi akhir fase 2 (self-attendance + tanda tangan digital).
     * 7. Sertifikat otomatis terbit & terverifikasi di portal validasi publik.
     * 8. Aturan draft tetap berlaku (draft ditolak pendaftaran dan tidak muncul).
     */
    public function test_alur_ujung_ke_ujung_kegiatan_peserta_lengkap(): void
    {
        $superAdmin = User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'is_blocked' => false,
        ]);

        $peserta = User::factory()->create([
            'role' => UserRole::MahasiswaKipk,
            'name' => 'Budi Santoso',
            'nim' => '2240021001',
            'email' => 'budi.santoso@student.unusa.ac.id',
            'verification_status' => 'Verified',
            'is_blocked' => false,
            'is_unusa' => true,
        ]);

        // 1. Super admin membuat dan menerbitkan kegiatan berkursi
        $kegiatan = Kegiatan::create([
            'nama_kegiatan' => 'Grand Summit Inovasi UNUSA 2026',
            'deskripsi' => 'Konferensi tahunan inovasi teknologi dan pengabdian.',
            'kategori' => 'seminar',
            'metode_pelaksanaan' => 'offline',
            'sasaran_peserta' => 'umum',
            'tanggal_mulai' => now()->addDays(2),
            'tanggal_selesai' => now()->addDays(2)->addHours(5),
            'lokasi_type' => 'unusa_campus',
            'lokasi_nama' => 'Auditorium Utama UNUSA',
            'lokasi_detail' => 'Tower Lt. 9, Kampus B',
            'file_panduan_pdf' => 'kegiatan/panduan.pdf',
            'biaya' => 0,
            'kuota' => 10,
            'status' => KegiatanStatus::Published,
            'is_seat_booking_active' => true,
            'seat_layout_config' => [
                'rows' => ['A'],
                'cols_per_row' => 5,
            ],
        ]);

        $seatA1 = KegiatanSeat::create([
            'kegiatan_id' => $kegiatan->id,
            'seat_code' => 'A01',
            'row_label' => 'A',
            'seat_number' => 1,
            'col_number' => 1,
            'status' => SeatStatus::Available,
        ]);

        // 2. Muncul di landing dan di daftar pengguna login
        $this->get(route('home'))->assertSee('Grand Summit Inovasi UNUSA 2026');
        $this->actingAs($peserta)->get(route('dashboard.kegiatan'))->assertSee('Grand Summit Inovasi UNUSA 2026');
        $this->actingAs($peserta)->get(route('dashboard.index'))->assertSee('Grand Summit Inovasi UNUSA 2026');

        // 3. Peserta mendaftar dengan kursi A01
        $resCheckout = $this->actingAs($peserta)->post(route('kegiatan.checkout', $kegiatan->id), [
            'delegates' => [
                [
                    'name' => $peserta->name,
                    'email' => $peserta->email,
                    'identity' => $peserta->nim,
                    'phone' => '081234567899',
                    'seat_id' => $seatA1->id,
                ],
            ],
            'payment_method' => 'QRIS',
        ]);

        // 4. Diarahkan ke Kegiatan Saya yang menampilkan kegiatan beserta tiket & kursi
        $resCheckout->assertRedirect(route('dashboard.kegiatan.saya'));

        $registration = KegiatanRegistration::where('kegiatan_id', $kegiatan->id)
            ->where('participant_user_id', $peserta->id)
            ->firstOrFail();

        $this->assertSame('A01', $registration->seat->seat_code);
        $this->assertStringStartsWith('TKT-', $registration->nomor_tiket);

        $resKegiatanSaya = $this->actingAs($peserta)->get(route('dashboard.kegiatan.saya'));
        $resKegiatanSaya->assertOk();
        $resKegiatanSaya->assertSee('Grand Summit Inovasi UNUSA 2026');
        $resKegiatanSaya->assertSee($registration->nomor_tiket);
        $resKegiatanSaya->assertSee('A01');

        // 5. Check-in fase 1 (validasi tiket fisik / barcode scan)
        $resCheckIn = $this->postJson(route('presensi.check-in-ticket'), [
            'ticket_code' => $registration->nomor_tiket,
        ]);
        $resCheckIn->assertOk()->assertJson([
            'success' => true,
            'already_checked_in' => false,
            'ticket_code' => $registration->nomor_tiket,
        ]);

        $registration->refresh();
        $this->assertEquals(AttendanceStatus::CheckedIn, $registration->status_kehadiran);

        // 6. Presensi akhir fase 2 (self attendance dengan tanda tangan touchscreen)
        $dummySignature = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        $resPresensi = $this->actingAs($peserta)->post(route('kegiatan.presensi.submit', $kegiatan->id), [
            'nama_lengkap' => $peserta->name,
            'nim' => $peserta->nim,
            'prodi' => 'S1 Sistem Informasi',
            'email' => $peserta->email,
            'nomor_telepon' => '081234567899',
            'signature' => $dummySignature,
        ]);
        $resPresensi->assertRedirect();

        $presensi = KegiatanPresensi::where('kegiatan_id', $kegiatan->id)
            ->where('nim', $peserta->nim)
            ->firstOrFail();
        $this->assertNotEmpty($presensi->signature_path);
        Storage::disk('public')->assertExists($presensi->signature_path);

        // 7. Sertifikat otomatis terbit & terverifikasi di portal publik
        $sertifikat = Sertifikat::where('kegiatan_id', $kegiatan->id)
            ->where('presensi_id', $presensi->id)
            ->firstOrFail();

        $this->assertStringStartsWith('CERT-', $sertifikat->nomor_sertifikat);

        $resVerif = $this->get('/verifikasi?code='.$sertifikat->nomor_sertifikat);
        $resVerif->assertOk();
        $resVerif->assertSee($sertifikat->nomor_sertifikat);
        $resVerif->assertSee($peserta->name);

        // 8. Aturan draft tetap berlaku
        $kegiatanDraft = Kegiatan::create([
            'nama_kegiatan' => 'Kegiatan Rahasia Draft',
            'kategori' => 'workshop',
            'metode_pelaksanaan' => 'online',
            'sasaran_peserta' => 'umum',
            'tanggal_mulai' => now()->addDays(20),
            'lokasi_type' => 'online',
            'lokasi_nama' => 'Zoom',
            'file_panduan_pdf' => 'kegiatan/panduan.pdf',
            'biaya' => 0,
            'kuota' => 10,
            'status' => KegiatanStatus::Draft,
        ]);

        // Peserta tidak bisa mendaftar kegiatan draft
        $resDraftCheckout = $this->actingAs($peserta)->post(route('kegiatan.checkout', $kegiatanDraft->id), [
            'delegates' => [
                [
                    'name' => $peserta->name,
                    'email' => $peserta->email,
                ],
            ],
        ]);
        $resDraftCheckout->assertSessionHas('error');

        // Peserta tidak bisa mengakses detail kegiatan draft
        $this->actingAs($peserta)->get(route('kegiatan.detail', $kegiatanDraft->id))->assertNotFound();

        // Super admin bisa melihat pratinjau draft
        $this->actingAs($superAdmin)->get(route('kegiatan.detail', $kegiatanDraft->id))->assertOk();
    }
}
