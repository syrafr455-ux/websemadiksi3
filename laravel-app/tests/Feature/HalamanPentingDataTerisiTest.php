<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\JenisSertifikat;
use App\Enums\KegiatanStatus;
use App\Enums\PeranSertifikat;
use App\Enums\RegistrationPaymentStatus;
use App\Enums\StatusSertifikat;
use App\Models\Activity;
use App\Models\Certificate;
use App\Models\Kegiatan;
use App\Models\KegiatanPenandatangan;
use App\Models\KegiatanPresensi;
use App\Models\KegiatanRegistration;
use App\Models\Penandatangan;
use App\Models\Sertifikat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Halaman penting merespons tanpa 500 saat query terisi data: setiap status kegiatan, sertifikat dengan dan tanpa
 * akun, sertifikat dicabut, dan data sertifikat lama. Dijalankan di suite SQLite dan PostgreSQL.
 */
class HalamanPentingDataTerisiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $mahasiswa;

    /** @var array<string, Kegiatan> status => kegiatan */
    private array $kegiatanPerStatus = [];

    private Kegiatan $selesai;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');

        $this->admin = User::factory()->superAdmin()->create();
        $this->mahasiswa = User::factory()->mahasiswaKipk()->create(['name' => 'Mahasiswa Contoh', 'nim' => '2241000001']);

        foreach (KegiatanStatus::cases() as $status) {
            $this->kegiatanPerStatus[$status->value] = $this->buatKegiatan($status);
        }
        $this->selesai = $this->kegiatanPerStatus[KegiatanStatus::Completed->value];

        $registrasi = KegiatanRegistration::create([
            'kegiatan_id' => $this->selesai->id, 'booker_user_id' => $this->mahasiswa->id, 'participant_user_id' => $this->mahasiswa->id,
            'participant_name' => $this->mahasiswa->name, 'participant_identity' => '2241000001', 'nomor_tiket' => 'TKT-HALAMAN-01',
            'status_pembayaran' => RegistrationPaymentStatus::Free, 'status_kehadiran' => AttendanceStatus::CheckedIn, 'checked_in_at' => now()->subDay(),
        ]);
        KegiatanPresensi::create([
            'kegiatan_id' => $this->selesai->id, 'registration_id' => $registrasi->id, 'user_id' => $this->mahasiswa->id,
            'nama_lengkap' => $this->mahasiswa->name, 'nim' => '2241000001', 'prodi' => 'Sistem Informasi', 'email' => 'mhs@unusa.ac.id',
            'nomor_telepon' => '08123456789', 'signature_path' => 'signatures/kegiatan_presensi/sig.png', 'check_in_at' => now()->subDay(),
        ]);

        $this->buatSertifikat('CERT-HALAMAN-0001', ['user_id' => $this->mahasiswa->id, 'registration_id' => $registrasi->id, 'penerima_key' => 'user:'.$this->mahasiswa->id, 'nama_penerima' => $this->mahasiswa->name]);
        $this->buatSertifikat('CERT-HALAMAN-0002', [
            'penerima_key' => Sertifikat::hitungPenerimaKey(null, 'Tamu Undangan', 'ITB'), 'nama_penerima' => 'Tamu Undangan', 'asal_institusi' => 'ITB',
            'jenis' => JenisSertifikat::Pembicara, 'predikat' => 'Keynote',
        ]);
        $this->buatSertifikat('CERT-HALAMAN-0003', [
            'penerima_key' => Sertifikat::hitungPenerimaKey(null, 'Peserta Dicabut', 'UNUSA'), 'nama_penerima' => 'Peserta Dicabut',
            'status' => StatusSertifikat::Dicabut, 'alasan_pencabutan' => 'Data keliru', 'dicabut_pada' => now(), 'dicabut_oleh' => $this->admin->id,
        ]);

        $penandatangan = Penandatangan::create(['nama' => 'Dr. Rektor', 'jabatan' => 'Rektor', 'institusi' => 'UNUSA', 'is_active' => true]);
        foreach ($this->kegiatanPerStatus as $kegiatan) {
            KegiatanPenandatangan::create(['kegiatan_id' => $kegiatan->id, 'penandatangan_id' => $penandatangan->id, 'jenis_sertifikat' => 'semua', 'urutan' => 1]);
        }

        $activity = Activity::create([
            'title' => 'Seminar Lama', 'category' => 'Seminar', 'type' => 'Online', 'date_text' => '20 Oktober 2025', 'location' => 'Zoom',
            'price' => 'Gratis', 'quota' => 100, 'xp_points' => 50, 'is_closed' => false,
        ]);
        Certificate::create([
            'user_id' => $this->mahasiswa->id, 'activity_id' => $activity->id, 'user_name' => 'Mahasiswa Contoh', 'activity_title' => 'Seminar Lama',
            'code' => 'CERT-LAMA-0001', 'status' => 'Berlaku', 'issued_date' => now()->toDateString(),
        ]);
    }

    private function buatKegiatan(KegiatanStatus $status): Kegiatan
    {
        return Kegiatan::create([
            'created_by' => $this->admin->id, 'nama_kegiatan' => 'Kegiatan '.$status->value, 'deskripsi' => 'Deskripsi', 'kategori' => 'seminar',
            'metode_pelaksanaan' => 'offline', 'sasaran_peserta' => 'umum', 'tanggal_mulai' => now()->subDays(3), 'tanggal_selesai' => now()->subDays(3)->addHours(2),
            'lokasi_type' => 'unusa_campus', 'lokasi_nama' => 'Auditorium', 'kuota' => 100, 'biaya' => 0, 'file_panduan_pdf' => 'kegiatan/panduan.pdf',
            'status' => $status,
        ]);
    }

    private function buatSertifikat(string $nomor, array $atribut = []): Sertifikat
    {
        return Sertifikat::create($atribut + [
            'kegiatan_id' => $this->selesai->id, 'nomor_sertifikat' => $nomor, 'nama_penerima' => 'Penerima', 'asal_institusi' => 'UNUSA',
            'jenis' => JenisSertifikat::Peserta, 'peran_sertifikat' => PeranSertifikat::Peserta, 'status' => StatusSertifikat::Aktif, 'issued_at' => now(),
            'penandatangan_snapshot' => [['nama_lengkap' => 'Dr. Rektor', 'jabatan' => 'Rektor', 'identitas_terformat' => 'NIP: 1975****01']],
        ]);
    }

    public function test_dashboard_dan_kelola_kegiatan_super_admin_memuat_kegiatan_dari_semua_status(): void
    {
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();

        $props = $this->actingAs($this->admin)->get(route('admin.kegiatan'))->assertOk()->original->getData()['page']['props'];
        $this->assertCount(count(KegiatanStatus::cases()), $props['kegiatans']['data']);
    }

    public function test_halaman_per_kegiatan_dan_halaman_sertifikat_terbuka_untuk_setiap_status(): void
    {
        foreach ($this->kegiatanPerStatus as $status => $kegiatan) {
            $this->actingAs($this->admin)->get(route('admin.kegiatan.show', $kegiatan->id))->assertOk();
            $this->actingAs($this->admin)->get(route('admin.kegiatan.sertifikat', $kegiatan->id))->assertOk();
            $this->actingAs($this->admin)->get(route('admin.attendances', ['kegiatan_id' => $kegiatan->id]))->assertOk();
        }
    }

    public function test_semua_tab_sertifikat_terbuka_dengan_sertifikat_berakun_tanpa_akun_dan_dicabut(): void
    {
        $this->actingAs($this->admin);

        foreach (['peserta', 'juri', 'pembicara', 'juara'] as $jenis) {
            $this->get(route('admin.certificates', ['tab' => 'terbitkan', 'kegiatan_id' => $this->selesai->id, 'jenis' => $jenis]))->assertOk();
        }
        $this->get(route('admin.certificates', ['tab' => 'arsip']))->assertOk()->assertSee('CERT-HALAMAN-0002');
        $this->get(route('admin.certificates', ['tab' => 'arsip', 'q' => 'Tamu']))->assertOk();
        $this->get(route('admin.certificates', ['tab' => 'penandatangan', 'kegiatan_id' => $this->selesai->id]))->assertOk();
        $this->get(route('admin.certificates', ['tab' => 'pengaturan']))->assertOk();
    }

    public function test_verifikasi_publik_sertifikat_baru_lama_dicabut_dan_tidak_dikenal(): void
    {
        $this->get(route('verifikasi.sertifikat', ['code' => 'CERT-HALAMAN-0001']))->assertOk()->assertSee('Mahasiswa Contoh');
        $this->get(route('verifikasi.sertifikat', ['code' => 'CERT-HALAMAN-0002']))->assertOk()->assertSee('Valid');
        $this->get(route('verifikasi.sertifikat', ['code' => 'CERT-HALAMAN-0003']))->assertOk();
        $this->get(route('verifikasi.sertifikat', ['code' => 'CERT-LAMA-0001']))->assertOk()->assertSee('CERT-LAMA-0001');
        $this->get(route('verifikasi.sertifikat', ['code' => 'CERT-TIDAK-ADA']))->assertOk();
        $this->get('/verifikasi')->assertOk();
    }

    public function test_halaman_mahasiswa_terbuka_dengan_registrasi_presensi_dan_sertifikat(): void
    {
        $this->actingAs($this->mahasiswa);

        $this->get(route('dashboard.index'))->assertOk();
        $this->get(route('dashboard.kegiatan'))->assertOk();
        $this->get(route('dashboard.kegiatan.saya'))->assertOk();
        $this->get(route('dashboard.sertifikat'))->assertOk()->assertSee('CERT-HALAMAN-0001');
        $this->get(route('dashboard.kegiatan.show', $this->selesai->id))->assertRedirect(route('kegiatan.detail', $this->selesai->id));
        $this->get(route('kegiatan.detail', $this->selesai->id))->assertOk();
    }
}
