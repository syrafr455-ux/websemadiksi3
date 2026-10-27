<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\JenisSertifikat;
use App\Enums\KegiatanStatus;
use App\Enums\RegistrationPaymentStatus;
use App\Enums\StatusSertifikat;
use App\Enums\UserRole;
use App\Models\Kegiatan;
use App\Models\KegiatanPresensi;
use App\Models\KegiatanRegistration;
use App\Models\Sertifikat;
use App\Models\User;
use App\Support\Wib;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PensiunPresensiDanSertifikatSiswaTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected User $admin;

    protected Kegiatan $kegiatan;

    protected KegiatanRegistration $registration;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Carbon::setTestNow(Carbon::parse('2026-11-15 10:00:00', 'Asia/Jakarta'));

        $this->user = User::factory()->create([
            'role' => UserRole::MahasiswaKipk,
            'name' => 'Fulan bin Fulan',
            'nim' => '2240021088',
            'email' => 'fulan@unusa.ac.id',
        ]);

        $this->admin = User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'name' => 'Super Admin',
            'email' => 'admin@unusa.ac.id',
        ]);

        $this->kegiatan = Kegiatan::create([
            'nama_kegiatan' => 'Workshop Literasi Digital 2026',
            'deskripsi' => 'Workshop literasi digital komprehensif.',
            'kategori' => 'workshop',
            'metode_pelaksanaan' => 'offline',
            'sasaran_peserta' => 'umum',
            'tanggal_mulai' => Wib::keUtc('2026-11-15 08:00'),
            'tanggal_selesai' => Wib::keUtc('2026-11-15 12:00'),
            'lokasi_type' => 'unusa_campus',
            'lokasi_nama' => 'Auditorium Tower',
            'file_panduan_pdf' => 'kegiatan/panduan.pdf',
            'biaya' => 0,
            'kuota' => 100,
            'status' => KegiatanStatus::Published,
            'presensi_akhir_dibuka' => true,
        ]);

        $this->registration = KegiatanRegistration::create([
            'kegiatan_id' => $this->kegiatan->id,
            'booker_user_id' => $this->user->id,
            'participant_user_id' => $this->user->id,
            'nomor_tiket' => 'TKT-WLD-001',
            'participant_name' => $this->user->name,
            'participant_identity' => $this->user->nim,
            'participant_phone' => '081234567891',
            'status_pembayaran' => RegistrationPaymentStatus::Free,
            'status_kehadiran' => AttendanceStatus::Registered,
        ]);
    }

    /**
     * 1. Presensi mandiri (Phase 2) mencatat presensi tanpa menerbitkan sertifikat otomatis.
     */
    public function test_presensi_mandiri_tidak_menerbitkan_sertifikat_otomatis(): void
    {
        $this->registration->update(['status_kehadiran' => AttendanceStatus::CheckedIn]);
        $dummySignature = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        // Unlock presensi akhir di session
        session(["presensi_akhir_unlocked_{$this->kegiatan->id}" => true]);

        $response = $this->actingAs($this->user)->post(route('kegiatan.presensi.submit', $this->kegiatan->id), [
            'nama_lengkap' => $this->user->name,
            'nim' => $this->user->nim,
            'prodi' => 'S1 Sistem Informasi',
            'email' => $this->user->email,
            'nomor_telepon' => '081234567891',
            'signature' => $dummySignature,
        ]);

        $response->assertRedirect(route('dashboard.kegiatan.saya'));

        // Presensi tersimpan
        $presensi = KegiatanPresensi::where('kegiatan_id', $this->kegiatan->id)
            ->where('nim', $this->user->nim)
            ->first();
        $this->assertNotNull($presensi);

        // Sertifikat TIDAK terbit otomatis
        $sertifikat = Sertifikat::where('kegiatan_id', $this->kegiatan->id)
            ->where('presensi_id', $presensi->id)
            ->first();
        $this->assertNull($sertifikat);
    }

    /**
     * 2. Presensi manual oleh admin mencatat presensi tanpa menerbitkan sertifikat otomatis.
     */
    public function test_presensi_akhir_manual_admin_tidak_menerbitkan_sertifikat_otomatis(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.kegiatan.presensi-akhir.manual', $this->kegiatan->id), [
            'registration_id' => $this->registration->id,
            'alasan' => 'Peserta lupa bawa gawai namun hadir penuh',
        ]);

        $response->assertRedirect();

        $presensi = KegiatanPresensi::where('kegiatan_id', $this->kegiatan->id)
            ->where('registration_id', $this->registration->id)
            ->first();
        $this->assertNotNull($presensi);

        $sertifikat = Sertifikat::where('kegiatan_id', $this->kegiatan->id)
            ->where('registration_id', $this->registration->id)
            ->first();
        $this->assertNull($sertifikat);
    }

    /**
     * 3. Di Kegiatan Saya, status kegiatan yang sudah presensi tetapi belum terbit sertifikat
     * menampilkan status "Menunggu verifikasi panitia".
     */
    public function test_kegiatan_saya_menampilkan_status_menunggu_verifikasi_panitia(): void
    {
        // Catat presensi
        KegiatanPresensi::create([
            'kegiatan_id' => $this->kegiatan->id,
            'user_id' => $this->user->id,
            'registration_id' => $this->registration->id,
            'nama_lengkap' => $this->user->name,
            'nim' => $this->user->nim,
            'prodi' => 'Sistem Informasi',
            'email' => $this->user->email,
            'nomor_telepon' => '081234567891',
            'signature_path' => 'sig.png',
            'check_in_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->get(route('dashboard.kegiatan.saya'));
        $response->assertOk();

        // Cek bahwa view / props memuat status menunggu verifikasi
        if (isset($response->original->getData()['page'])) {
            $props = $response->original->getData()['page']['props'];
            $regs = collect($props['enterpriseRegistrations']);
            $myReg = $regs->firstWhere('id', $this->registration->id);
            $this->assertEquals('menunggu_verifikasi', $myReg['sertifikat_status']);
        } else {
            $response->assertSee('Menunggu verifikasi panitia');
        }
    }

    /**
     * 4. Di Kegiatan Saya, jika sertifikat sudah terbit, tautan/status terbit muncul.
     */
    public function test_kegiatan_saya_menampilkan_status_sertifikat_terbit(): void
    {
        // Terbitkan sertifikat
        $sertifikat = Sertifikat::create([
            'kegiatan_id' => $this->kegiatan->id,
            'user_id' => $this->user->id,
            'registration_id' => $this->registration->id,
            'nomor_sertifikat' => 'CERT-WLD-2026-0001',
            'recipient_name' => $this->user->name,
            'nama_penerima' => $this->user->name,
            'jenis' => JenisSertifikat::Peserta,
            'status' => StatusSertifikat::Aktif,
            'document_hash' => hash('sha256', 'CERT-WLD-2026-0001'),
            'file_pdf_path' => 'kegiatan/sertifikat/wld.pdf',
            'issued_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->get(route('dashboard.kegiatan.saya'));
        $response->assertOk();

        if (isset($response->original->getData()['page'])) {
            $props = $response->original->getData()['page']['props'];
            $regs = collect($props['enterpriseRegistrations']);
            $myReg = $regs->firstWhere('id', $this->registration->id);
            $this->assertEquals('terbit', $myReg['sertifikat_status']);
            $this->assertEquals('CERT-WLD-2026-0001', $myReg['sertifikat_nomor']);
        } else {
            $response->assertSee('CERT-WLD-2026-0001');
        }
    }

    /**
     * 5. Halaman Sertifikat & Portofolio mengelompokkan sertifikat per jenis (peserta dan juri sekaligus).
     */
    public function test_halaman_sertifikat_mengelompokkan_per_jenis(): void
    {
        // Sertifikat 1: Peserta
        Sertifikat::create([
            'kegiatan_id' => $this->kegiatan->id,
            'user_id' => $this->user->id,
            'nomor_sertifikat' => 'CERT-WLD-2026-0001',
            'nama_penerima' => $this->user->name,
            'jenis' => JenisSertifikat::Peserta,
            'status' => StatusSertifikat::Aktif,
            'document_hash' => hash('sha256', 'CERT-WLD-2026-0001'),
            'file_pdf_path' => 'kegiatan/sertifikat/wld.pdf',
            'issued_at' => now(),
        ]);

        // Sertifikat 2: Juri (kegiatan lain)
        $kegiatanLomba = Kegiatan::create([
            'nama_kegiatan' => 'Lomba Inovasi Mahasiswa 2026',
            'deskripsi' => 'Lomba tahunan.',
            'kategori' => 'lomba',
            'metode_pelaksanaan' => 'offline',
            'sasaran_peserta' => 'umum',
            'tanggal_mulai' => Wib::keUtc('2026-11-20 08:00'),
            'tanggal_selesai' => Wib::keUtc('2026-11-20 16:00'),
            'lokasi_type' => 'unusa_campus',
            'lokasi_nama' => 'Lab IT',
            'file_panduan_pdf' => 'kegiatan/panduan.pdf',
            'biaya' => 0,
            'kuota' => 50,
            'status' => KegiatanStatus::Published,
        ]);

        Sertifikat::create([
            'kegiatan_id' => $kegiatanLomba->id,
            'user_id' => $this->user->id,
            'nomor_sertifikat' => 'CERT-LIM-2026-0002',
            'nama_penerima' => $this->user->name,
            'jenis' => JenisSertifikat::Juri,
            'status' => StatusSertifikat::Aktif,
            'document_hash' => hash('sha256', 'CERT-LIM-2026-0002'),
            'file_pdf_path' => 'kegiatan/sertifikat/lim.pdf',
            'issued_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->get(route('dashboard.sertifikat'));
        $response->assertOk();

        // Tampilan mengelompokkan kedua jenis
        $response->assertSee('Sertifikat Peserta');
        $response->assertSee('Sertifikat Juri');
        $response->assertSee('CERT-WLD-2026-0001');
        $response->assertSee('CERT-LIM-2026-0002');
    }
}
