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
use App\Support\Wib;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminPresensiAkhirTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    protected function buatKegiatanDenganPeserta(): array
    {
        $kegiatan = Kegiatan::create([
            'nama_kegiatan' => 'Seminar Teknologi Cloud 2026',
            'deskripsi' => 'Membahas arsitektur komputasi awan.',
            'kategori' => 'seminar',
            'metode_pelaksanaan' => 'offline',
            'sasaran_peserta' => 'umum',
            'tanggal_mulai' => Carbon::parse('2026-11-10 09:00:00', 'Asia/Jakarta'),
            'tanggal_selesai' => Carbon::parse('2026-11-10 13:00:00', 'Asia/Jakarta'),
            'lokasi_type' => 'unusa_campus',
            'lokasi_nama' => 'Auditorium Tower Lantai 9',
            'file_panduan_pdf' => 'kegiatan/panduan.pdf',
            'biaya' => 0,
            'kuota' => 100,
            'status' => KegiatanStatus::Published,
        ]);

        $user1 = User::factory()->create([
            'name' => 'Fauzan Adhim',
            'email' => 'fauzan@unusa.ac.id',
            'nim' => '2240021001',
            'role' => UserRole::MahasiswaKipk,
        ]);

        $user2 = User::factory()->create([
            'name' => 'Nadia Rahma',
            'email' => 'nadia@gmail.com',
            'nim' => '99887766',
            'role' => UserRole::UserUmum,
        ]);

        // Peserta 1: Registrasi + Check-in Fase 1 + Presensi Fase 2 + Sertifikat
        $checkInTime = Carbon::parse('2026-11-10 08:45:00', 'Asia/Jakarta');
        $reg1 = KegiatanRegistration::create([
            'kegiatan_id' => $kegiatan->id,
            'booker_user_id' => $user1->id,
            'participant_user_id' => $user1->id,
            'nomor_tiket' => 'TKT-CLOUD-001',
            'participant_name' => $user1->name,
            'participant_identity' => $user1->nim,
            'participant_phone' => '081234567890',
            'status_pembayaran' => 'free',
            'status_kehadiran' => AttendanceStatus::CheckedIn,
            'checked_in_at' => $checkInTime,
        ]);

        $fakeSig = UploadedFile::fake()->image('signature.png', 200, 100);
        $sigPath = $fakeSig->store('signatures', 'public');

        $presensiTime = Carbon::parse('2026-11-10 12:15:00', 'Asia/Jakarta');
        $presensi1 = KegiatanPresensi::create([
            'kegiatan_id' => $kegiatan->id,
            'user_id' => $user1->id,
            'registration_id' => $reg1->id,
            'nama_lengkap' => $user1->name,
            'nim' => $user1->nim,
            'prodi' => 'S1 Sistem Informasi',
            'email' => $user1->email,
            'nomor_telepon' => '081234567890',
            'signature_path' => $sigPath,
            'check_in_at' => $presensiTime,
        ]);

        $sertifikat1 = Sertifikat::create([
            'nomor_sertifikat' => 'CERT-CLOUD-2026-0001',
            'kegiatan_id' => $kegiatan->id,
            'user_id' => $user1->id,
            'presensi_id' => $presensi1->id,
            'peran_sertifikat' => PeranSertifikat::Peserta,
            'file_pdf_path' => 'certificates/sample1.pdf',
            'document_hash' => hash('sha256', 'hash_sample_1'),
            'is_valid' => true,
            'issued_at' => $presensiTime,
        ]);

        // Peserta 2: Registrasi saja (Belum Check-In, Belum Presensi)
        $reg2 = KegiatanRegistration::create([
            'kegiatan_id' => $kegiatan->id,
            'booker_user_id' => $user2->id,
            'participant_user_id' => $user2->id,
            'nomor_tiket' => 'TKT-CLOUD-002',
            'participant_name' => $user2->name,
            'participant_identity' => $user2->nim,
            'participant_phone' => '089876543210',
            'status_pembayaran' => 'free',
            'status_kehadiran' => AttendanceStatus::Registered,
            'checked_in_at' => null,
        ]);

        return compact('kegiatan', 'user1', 'user2', 'reg1', 'reg2', 'presensi1', 'sertifikat1', 'sigPath');
    }

    /**
     * 1. Super admin dapat melihat halaman presensi akhir lengkap dengan status fase 1, fase 2, dan waktu WIB.
     */
    public function test_super_admin_can_view_attendances_page_with_wib_time(): void
    {
        $data = $this->buatKegiatanDenganPeserta();
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $response = $this->actingAs($admin)->get(route('admin.attendances', ['kegiatan_id' => $data['kegiatan']->id]));

        $response->assertOk()
            ->assertSee('Presensi &amp; Kehadiran Peserta', false)
            ->assertSee('Seminar Teknologi Cloud 2026')
            ->assertSee('Fauzan Adhim')
            ->assertSee('TKT-CLOUD-001')
            ->assertSee('Sudah Check-In')
            ->assertSee('Sudah Presensi Akhir')
            ->assertSee('CERT-CLOUD-2026-0001')
            ->assertSee('WIB') // Memastikan zona waktu WIB tampil
            ->assertSee('Nadia Rahma')
            ->assertSee('Belum Check-In')
            ->assertSee('Belum Presensi');
    }

    /**
     * 2. Tanda tangan digital hanya bisa diakses oleh Super Admin via Policy; dilarang untuk publik & role lain.
     */
    public function test_signature_preview_is_restricted_to_super_admin_policy(): void
    {
        $data = $this->buatKegiatanDenganPeserta();
        $presensi = $data['presensi1'];
        $presensiId = $presensi->id;

        $mhs = User::factory()->create(['role' => UserRole::MahasiswaKipk]);
        $umum = User::factory()->create(['role' => UserRole::UserUmum]);
        $verifikator = User::factory()->create(['role' => UserRole::VerifikatorBerkas]);
        $juri = User::factory()->create(['role' => UserRole::PenilaiKarya]);
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        // A. Validasi Policy langsung: hanya super_admin yang diizinkan viewSignature
        $this->assertFalse(Gate::forUser($mhs)->allows('viewSignature', $presensi));
        $this->assertFalse(Gate::forUser($umum)->allows('viewSignature', $presensi));
        $this->assertFalse(Gate::forUser($verifikator)->allows('viewSignature', $presensi));
        $this->assertFalse(Gate::forUser($juri)->allows('viewSignature', $presensi));
        $this->assertTrue(Gate::forUser($admin)->allows('viewSignature', $presensi));

        // B. Tamu (Guest / Publik) tidak bisa akses -> redirect ke login
        $guestResp = $this->get(route('admin.attendances.signature', $presensiId));
        $guestResp->assertRedirect(route('login'));

        // C. Request JSON/API oleh role non-super admin -> 403 Forbidden
        $this->actingAs($mhs)->getJson(route('admin.attendances.signature', $presensiId))->assertForbidden();
        $this->actingAs($umum)->getJson(route('admin.attendances.signature', $presensiId))->assertForbidden();
        $this->actingAs($verifikator)->getJson(route('admin.attendances.signature', $presensiId))->assertForbidden();
        $this->actingAs($juri)->getJson(route('admin.attendances.signature', $presensiId))->assertForbidden();

        // D. Super Admin -> 200 OK (Melihat tanda tangan)
        $adminResp = $this->actingAs($admin)->get(route('admin.attendances.signature', $presensiId));
        $adminResp->assertOk();
    }

    /**
     * 3. Hak akses halaman admin presensi dibatasi hanya untuk Super Admin.
     */
    public function test_attendances_index_access_restricted_per_role(): void
    {
        $data = $this->buatKegiatanDenganPeserta();
        $url = route('admin.attendances', ['kegiatan_id' => $data['kegiatan']->id]);

        $mhs = User::factory()->create(['role' => UserRole::MahasiswaKipk]);
        $umum = User::factory()->create(['role' => UserRole::UserUmum]);
        $verifikator = User::factory()->create(['role' => UserRole::VerifikatorBerkas]);
        $juri = User::factory()->create(['role' => UserRole::PenilaiKarya]);
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        // Validasi Policy viewAny
        $this->assertFalse(Gate::forUser($mhs)->allows('viewAny', KegiatanPresensi::class));
        $this->assertFalse(Gate::forUser($umum)->allows('viewAny', KegiatanPresensi::class));
        $this->assertFalse(Gate::forUser($verifikator)->allows('viewAny', KegiatanPresensi::class));
        $this->assertFalse(Gate::forUser($juri)->allows('viewAny', KegiatanPresensi::class));
        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', KegiatanPresensi::class));

        // Tamu -> redirect login
        $this->get($url)->assertRedirect(route('login'));

        // Non-super-admin ditolak saat mencoba akses via JSON/API (403 Forbidden)
        $this->actingAs($mhs)->getJson($url)->assertForbidden();
        $this->actingAs($umum)->getJson($url)->assertForbidden();
        $this->actingAs($verifikator)->getJson($url)->assertForbidden();
        $this->actingAs($juri)->getJson($url)->assertForbidden();

        // Non-super-admin ditolak saat akses web (dialihkan dengan pesan error)
        $this->actingAs($mhs)->get($url)->assertRedirect(route('admin.login'));
        $this->actingAs($verifikator)->get($url)->assertRedirect(route('admin.berkas'));
        $this->actingAs($juri)->get($url)->assertRedirect(route('admin.penilaian'));

        // Super Admin -> 200 OK
        $this->actingAs($admin)->get($url)->assertOk();
    }

    /**
     * 4. Fitur pencarian dan filter pada halaman presensi.
     */
    public function test_filtering_and_search_on_attendances(): void
    {
        $data = $this->buatKegiatanDenganPeserta();
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        // A. Pencarian spesifik 'Fauzan'
        $respSearch = $this->actingAs($admin)->get(route('admin.attendances', [
            'kegiatan_id' => $data['kegiatan']->id,
            'q' => 'Fauzan',
        ]));
        $respSearch->assertOk()
            ->assertSee('Fauzan Adhim')
            ->assertDontSee('Nadia Rahma');

        // B. Filter Fase 1: Hanya yang sudah check-in
        $respFase1 = $this->actingAs($admin)->get(route('admin.attendances', [
            'kegiatan_id' => $data['kegiatan']->id,
            'fase1' => 'checked_in',
        ]));
        $respFase1->assertOk()
            ->assertSee('Fauzan Adhim')
            ->assertDontSee('Nadia Rahma');

        // C. Filter Fase 1: Belum check-in
        $respNotCheckIn = $this->actingAs($admin)->get(route('admin.attendances', [
            'kegiatan_id' => $data['kegiatan']->id,
            'fase1' => 'not_checked_in',
        ]));
        $respNotCheckIn->assertOk()
            ->assertSee('Nadia Rahma')
            ->assertDontSee('Fauzan Adhim');
    }

    /**
     * 5. Ekspor rekapitulasi presensi ke file CSV oleh Super Admin.
     */
    public function test_super_admin_can_export_attendances_csv(): void
    {
        $data = $this->buatKegiatanDenganPeserta();
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $exportResp = $this->actingAs($admin)->get(route('admin.attendances.export', [
            'kegiatan_id' => $data['kegiatan']->id,
        ]));

        $exportResp->assertOk();
        $exportResp->assertHeader('content-type', 'text/csv; charset=UTF-8');

        // Capture streamed response content
        $content = $exportResp->streamedContent();

        $this->assertStringContainsString('Nama Peserta', $content);
        $this->assertStringContainsString('Status Check-In Fase 1', $content);
        $this->assertStringContainsString('Status Presensi Akhir Fase 2', $content);
        $this->assertStringContainsString('Waktu Presensi Akhir (WIB)', $content);
        $this->assertStringContainsString('Fauzan Adhim', $content);
        $this->assertStringContainsString('TKT-CLOUD-001', $content);
        $this->assertStringContainsString('Sudah Check-In', $content);
        $this->assertStringContainsString('Sudah Presensi Akhir', $content);
        $this->assertStringContainsString('CERT-CLOUD-2026-0001', $content);
    }
}
