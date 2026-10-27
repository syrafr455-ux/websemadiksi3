<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\KegiatanStatus;
use App\Enums\RegistrationPaymentStatus;
use App\Enums\UserRole;
use App\Models\Kegiatan;
use App\Models\KegiatanPresensi;
use App\Models\KegiatanRegistration;
use App\Models\KegiatanSubmission;
use App\Models\User;
use App\Support\CsvSanitizer;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTrackingKehadiranTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-27 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_csv_sanitizer_netralkan_formula_berbahaya(): void
    {
        // Formula prefixes: '=', '+', '-', '@', "\t", "\r"
        $this->assertSame("'=cmd|' /C calc'!A0", CsvSanitizer::netralkan("=cmd|' /C calc'!A0"));
        $this->assertSame("'+62812345678", CsvSanitizer::netralkan('+62812345678'));
        $this->assertSame("'-5+2", CsvSanitizer::netralkan('-5+2'));
        $this->assertSame("'@SUM(1,2)", CsvSanitizer::netralkan('@SUM(1,2)'));
        $this->assertSame("'\ttab_value", CsvSanitizer::netralkan("\ttab_value"));
        $this->assertSame("'\rcr_value", CsvSanitizer::netralkan("\rcr_value"));

        // Safe values remain unchanged
        $this->assertSame('Fauzan Adhim', CsvSanitizer::netralkan('Fauzan Adhim'));
        $this->assertSame('12345678', CsvSanitizer::netralkan('12345678'));
        $this->assertSame('', CsvSanitizer::netralkan(''));
        $this->assertSame('', CsvSanitizer::netralkan(null));

        // Row sanitization
        $row = ['=1+1', 'Budi', '-500'];
        $this->assertSame(["'=1+1", 'Budi', "'-500"], CsvSanitizer::netralkanBaris($row));
    }

    private function buatKegiatan(array $overrides = []): Kegiatan
    {
        return Kegiatan::create(array_merge([
            'nama_kegiatan' => 'Seminar Cloud 2026',
            'deskripsi' => 'Deskripsi kegiatan',
            'kategori' => 'seminar',
            'metode_pelaksanaan' => 'offline',
            'sasaran_peserta' => 'umum',
            'lokasi_type' => 'unusa_campus',
            'lokasi_nama' => 'Auditorium Tower Lantai 9',
            'tanggal_mulai' => now()->subHours(3),
            'tanggal_selesai' => now()->subHour(),
            'file_panduan_pdf' => 'kegiatan/panduan.pdf',
            'biaya' => 0,
            'kuota' => 100,
            'status' => KegiatanStatus::Published,
        ], $overrides));
    }

    private function buatRegistration(Kegiatan $kegiatan, User $user, array $overrides = []): KegiatanRegistration
    {
        return KegiatanRegistration::create(array_merge([
            'kegiatan_id' => $kegiatan->id,
            'booker_user_id' => $user->id,
            'participant_user_id' => $user->id,
            'participant_name' => $user->name,
            'participant_identity' => $user->nim ?? '12345',
            'participant_email' => $user->email,
            'nomor_tiket' => 'TKT-'.strtoupper(bin2hex(random_bytes(3))),
            'status' => 'confirmed',
            'status_pembayaran' => RegistrationPaymentStatus::Free->value,
            'status_kehadiran' => AttendanceStatus::CheckedIn,
            'checked_in_at' => now()->subHours(2),
        ], $overrides));
    }

    public function test_corong_kehadiran_dan_kelayakan_peserta_offline(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $kegiatan = $this->buatKegiatan();

        // Peserta 1: Lengkap (Check-in tiket + Presensi akhir bertanda tangan) -> Layak
        $user1 = User::factory()->create(['name' => 'Peserta Layak', 'nim' => '2024001', 'email' => 'layak@unusa.ac.id', 'role' => UserRole::MahasiswaKipk]);
        $reg1 = $this->buatRegistration($kegiatan, $user1, [
            'nomor_tiket' => 'TKT-LAYAK-01',
        ]);
        KegiatanPresensi::create([
            'kegiatan_id' => $kegiatan->id,
            'registration_id' => $reg1->id,
            'user_id' => $user1->id,
            'nama_lengkap' => $user1->name,
            'nim' => '2024001',
            'email' => 'layak@unusa.ac.id',
            'nomor_telepon' => '08123456789',
            'prodi' => 'Sistem Informasi',
            'check_in_at' => now()->subMinutes(30),
            'signature_path' => 'signatures/kegiatan_presensi/sig_layak.png',
        ]);

        // Peserta 2: Belum presensi akhir (Hanya check-in fase 1) -> Belum Layak
        $user2 = User::factory()->create(['name' => 'Peserta Tanpa Fase2', 'nim' => '2024002', 'email' => 'fase1only@unusa.ac.id', 'role' => UserRole::MahasiswaKipk]);
        $this->buatRegistration($kegiatan, $user2, [
            'nomor_tiket' => 'TKT-FASE1-02',
        ]);

        // Peserta 3: Belum check-in dan belum presensi -> Belum Layak
        $user3 = User::factory()->create(['name' => 'Peserta Belum Hadir', 'nim' => '2024003', 'email' => 'nohadir@unusa.ac.id', 'role' => UserRole::MahasiswaKipk]);
        $this->buatRegistration($kegiatan, $user3, [
            'nomor_tiket' => 'TKT-NOHADIR-03',
            'status_kehadiran' => AttendanceStatus::Registered,
            'checked_in_at' => null,
        ]);

        // Request ke halaman attendances
        $response = $this->actingAs($admin)->get(route('admin.attendances', ['kegiatan_id' => $kegiatan->id]));

        $response->assertOk();
        $response->assertSee('Corong Kehadiran &amp; Kelayakan Sertifikat', false);
        $response->assertSee('Peserta Layak');
        $response->assertSee('Peserta Tanpa Fase2');
        $response->assertSee('Peserta Belum Hadir');

        // Status badge di tabel
        $response->assertSee('Layak');
        $response->assertSee('Belum Layak');
        $response->assertSee('Belum presensi akhir');
        $response->assertSee('Belum check-in awal');

        // Filter: Hanya yang Layak
        $responseLayak = $this->actingAs($admin)->get(route('admin.attendances', [
            'kegiatan_id' => $kegiatan->id,
            'kelayakan' => 'layak',
        ]));
        $responseLayak->assertOk()
            ->assertSee('Peserta Layak')
            ->assertDontSee('Peserta Tanpa Fase2')
            ->assertDontSee('Peserta Belum Hadir');

        // Filter: Hanya yang Belum Layak
        $responseBelumLayak = $this->actingAs($admin)->get(route('admin.attendances', [
            'kegiatan_id' => $kegiatan->id,
            'kelayakan' => 'belum_layak',
        ]));
        $responseBelumLayak->assertOk()
            ->assertSee('Peserta Tanpa Fase2')
            ->assertSee('Peserta Belum Hadir')
            ->assertDontSee('Peserta Layak');
    }

    public function test_corong_kehadiran_kegiatan_lomba_memeriksa_pengumpulan_karya(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $lomba = $this->buatKegiatan([
            'nama_kegiatan' => 'Lomba UI/UX Nasional',
            'deskripsi' => 'Deskripsi lomba',
            'kategori' => 'lomba',
            'is_submission_active' => true,
            'tanggal_mulai' => now()->subHours(4),
            'tanggal_selesai' => now()->subHour(),
        ]);

        // Peserta 1: Hadir fase 1 + fase 2 + sudah mengumpulkan karya -> Layak
        $user1 = User::factory()->create(['name' => 'Juara Desain', 'nim' => '2024101', 'email' => 'juara@unusa.ac.id', 'role' => UserRole::MahasiswaKipk]);
        $reg1 = $this->buatRegistration($lomba, $user1, [
            'nomor_tiket' => 'TKT-LOMBA-01',
        ]);
        KegiatanPresensi::create([
            'kegiatan_id' => $lomba->id,
            'registration_id' => $reg1->id,
            'user_id' => $user1->id,
            'nama_lengkap' => $user1->name,
            'nim' => '2024101',
            'email' => 'juara@unusa.ac.id',
            'nomor_telepon' => '08123456789',
            'prodi' => 'Desain',
            'check_in_at' => now()->subHour(),
            'signature_path' => 'signatures/sig_juara.png',
        ]);
        KegiatanSubmission::create([
            'kegiatan_id' => $lomba->id,
            'registration_id' => $reg1->id,
            'nama_kelompok_peserta' => 'Tim Juara Desain',
            'file_karya_url' => 'submissions/karya1.pdf',
        ]);

        // Peserta 2: Hadir fase 1 + fase 2 TAPI belum kumpul karya -> Belum Layak ('Karya belum dikumpulkan')
        $user2 = User::factory()->create(['name' => 'Peserta Tanpa Karya', 'nim' => '2024102', 'email' => 'nokarya@unusa.ac.id', 'role' => UserRole::MahasiswaKipk]);
        $reg2 = $this->buatRegistration($lomba, $user2, [
            'nomor_tiket' => 'TKT-LOMBA-02',
        ]);
        KegiatanPresensi::create([
            'kegiatan_id' => $lomba->id,
            'registration_id' => $reg2->id,
            'user_id' => $user2->id,
            'nama_lengkap' => $user2->name,
            'nim' => '2024102',
            'email' => 'nokarya@unusa.ac.id',
            'nomor_telepon' => '08123456789',
            'prodi' => 'Informatika',
            'check_in_at' => now()->subHour(),
            'signature_path' => 'signatures/sig_nokarya.png',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.attendances', ['kegiatan_id' => $lomba->id]));
        $response->assertOk();
        $response->assertSee('Karya Lomba');
        $response->assertSee('Sudah Kumpul');
        $response->assertSee('Belum Kumpul');
        $response->assertSee('Karya belum dikumpulkan');

        // Filter karya terkumpul
        $respKarya = $this->actingAs($admin)->get(route('admin.attendances', [
            'kegiatan_id' => $lomba->id,
            'kelayakan' => 'karya_terkumpul',
        ]));
        $respKarya->assertOk()
            ->assertSee('Juara Desain')
            ->assertDontSee('Peserta Tanpa Karya');

        // Filter karya belum
        $respBelum = $this->actingAs($admin)->get(route('admin.attendances', [
            'kegiatan_id' => $lomba->id,
            'kelayakan' => 'karya_belum',
        ]));
        $respBelum->assertOk()
            ->assertSee('Peserta Tanpa Karya')
            ->assertDontSee('Juara Desain');
    }

    public function test_ekspor_csv_memuat_status_kelayakan_wib_dan_netralisasi_formula(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $kegiatan = $this->buatKegiatan([
            'nama_kegiatan' => 'Workshop IT',
            'deskripsi' => 'Deskripsi workshop',
            'kategori' => 'workshop',
            'tanggal_mulai' => now()->subHours(2),
            'tanggal_selesai' => now()->subHour(),
        ]);

        // Peserta dengan nama dan tiket yang diawali karakter berbahaya untuk formula injection
        $userDangerous = User::factory()->create([
            'name' => '=HYPERLINK("http://evil.com")',
            'email' => 'attacker@unusa.ac.id',
            'role' => UserRole::MahasiswaKipk,
        ]);

        $this->buatRegistration($kegiatan, $userDangerous, [
            'participant_name' => $userDangerous->name,
            'participant_identity' => '+62812345678',
            'participant_email' => 'attacker@unusa.ac.id',
            'nomor_tiket' => '@MALICIOUS-CODE',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.attendances.export', [
            'kegiatan_id' => $kegiatan->id,
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();

        // Verifikasi header CSV baru
        $this->assertStringContainsString('Status Kelayakan Sertifikat', $csv);
        $this->assertStringContainsString('Alasan Kelayakan', $csv);

        // Verifikasi netralisasi formula injection: diawali tanda petik tunggal (')
        $this->assertStringContainsString('\'=HYPERLINK', $csv);
        $this->assertStringContainsString("'+62812345678", $csv);
        $this->assertStringContainsString("'@MALICIOUS-CODE", $csv);

        // Verifikasi waktu tercatat dalam format WIB
        $this->assertStringContainsString('WIB', $csv);
    }
}
