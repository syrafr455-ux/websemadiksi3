<?php

namespace Tests\Feature;

use App\Enums\JenisSertifikat;
use App\Enums\KegiatanStatus;
use App\Enums\PeranSertifikat;
use App\Enums\RegistrationPaymentStatus;
use App\Enums\StatusSertifikat;
use App\Enums\UserRole;
use App\Models\Kegiatan;
use App\Models\KegiatanPenandatangan;
use App\Models\KegiatanPresensi;
use App\Models\KegiatanRegistration;
use App\Models\Penandatangan;
use App\Models\PengaturanVerifikasi;
use App\Models\Sertifikat;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Penjaga regresi untuk error 500 /admin/sertifikat (SQLSTATE[42703] kolom "status" tidak ada di
 * kegiatan_registrations). SQLite menelan nama kolom yang salah, jadi selain memeriksa status 200 tes ini
 * mencocokkan setiap kolom pada query yang dijalankan dengan skema (lihat MengauditKolomSql).
 */
class HalamanSertifikatDanKehadiranPenjagaKolomTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Kegiatan $kegiatan;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        Carbon::setTestNow('2026-10-27 10:00:00');

        $this->admin = User::factory()->superAdmin()->create();
        $this->kegiatan = Kegiatan::create([
            'created_by' => $this->admin->id,
            'nama_kegiatan' => 'Seminar Teknologi Nasional',
            'deskripsi' => 'Deskripsi',
            'kategori' => 'seminar',
            'metode_pelaksanaan' => 'offline',
            'sasaran_peserta' => 'umum',
            'tanggal_mulai' => '2026-10-20 01:00:00',
            'tanggal_selesai' => '2026-10-20 09:00:00',
            'lokasi_type' => 'unusa_campus',
            'lokasi_nama' => 'Auditorium',
            'kuota' => 100,
            'biaya' => 0,
            'file_panduan_pdf' => 'kegiatan/panduan.pdf',
            'status' => KegiatanStatus::Published,
        ]);

        foreach ([RegistrationPaymentStatus::Free, RegistrationPaymentStatus::Pending, RegistrationPaymentStatus::Paid] as $i => $status) {
            $user = User::factory()->create(['role' => UserRole::MahasiswaKipk, 'nim' => '3130010'.$i]);
            $reg = KegiatanRegistration::create([
                'kegiatan_id' => $this->kegiatan->id,
                'booker_user_id' => $user->id,
                'participant_user_id' => $user->id,
                'participant_name' => $user->name,
                'nomor_tiket' => "TIK-GUARD-{$i}",
                'status_pembayaran' => $status,
                'checked_in_at' => '2026-10-20 01:30:00',
            ]);
            KegiatanPresensi::create([
                'kegiatan_id' => $this->kegiatan->id,
                'registration_id' => $reg->id,
                'user_id' => $user->id,
                'nama_lengkap' => $user->name,
                'email' => $user->email,
                'nim' => $user->nim,
                'prodi' => 'S1 Sistem Informasi',
                'nomor_telepon' => '08123456789',
                'signature_path' => 'signatures/x.png',
            ]);
        }

        $ttd = Penandatangan::create([
            'nama' => 'Dr. Penandatangan', 'jabatan' => 'Rektor', 'jenis_identitas' => 'NIP',
            'nomor_identitas' => '1234567890', 'institusi' => 'UNUSA', 'mode_tampil' => 'samarkan', 'is_active' => true,
        ]);
        KegiatanPenandatangan::create([
            'kegiatan_id' => $this->kegiatan->id, 'penandatangan_id' => $ttd->id, 'jenis_sertifikat' => null, 'urutan' => 1,
        ]);
        PengaturanVerifikasi::ambil();

        Sertifikat::create([
            'kegiatan_id' => $this->kegiatan->id,
            'nomor_sertifikat' => 'CERT-GUARD-2026-0001',
            'nama_penerima' => 'Peserta Contoh',
            'jenis' => JenisSertifikat::Peserta,
            'peran' => PeranSertifikat::Peserta,
            'status' => StatusSertifikat::Aktif,
            'file_pdf_path' => 'certificates/dummy.pdf',
            'document_hash' => hash('sha256', 'CERT-GUARD-2026-0001'),
            'issued_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return iterable<string, array{0: string}> */
    public static function halaman(): iterable
    {
        yield 'sertifikat terbitkan' => ['/admin/penerbitan-sertifikat?tab=terbitkan'];
        yield 'sertifikat terbitkan juri' => ['/admin/penerbitan-sertifikat?tab=terbitkan&jenis=juri'];
        yield 'sertifikat terbitkan juara' => ['/admin/penerbitan-sertifikat?tab=terbitkan&jenis=juara'];
        yield 'sertifikat terbitkan pembicara' => ['/admin/penerbitan-sertifikat?tab=terbitkan&jenis=pembicara'];
        yield 'sertifikat arsip' => ['/admin/penerbitan-sertifikat?tab=arsip'];
        yield 'sertifikat arsip terfilter' => ['/admin/penerbitan-sertifikat?tab=arsip&jenis=peserta&status=aktif&q=Contoh'];
        yield 'sertifikat penandatangan' => ['/admin/penerbitan-sertifikat?tab=penandatangan'];
        yield 'sertifikat pengaturan' => ['/admin/penerbitan-sertifikat?tab=pengaturan'];
        yield 'tracking kehadiran' => ['/admin/absensi'];
        yield 'verifikasi publik' => ['/verifikasi?code=CERT-GUARD-2026-0001'];
    }

    #[DataProvider('halaman')]
    public function test_halaman_merespons_200_dan_semua_kolom_ada_di_skema(string $url): void
    {
        $this->mulaiAuditKolom();

        $response = $this->actingAs($this->admin)->get(str_replace('{id}', (string) $this->kegiatan->id, $url));
        $response->assertOk();

        $this->assertTidakAdaKolomTidakDikenal($url);
    }

    public function test_tracking_kehadiran_per_kegiatan_dan_unduh_csv(): void
    {
        $this->mulaiAuditKolom();

        $this->actingAs($this->admin)->get('/admin/absensi?kegiatan_id='.$this->kegiatan->id)->assertOk();
        $this->actingAs($this->admin)->get('/admin/absensi/export?kegiatan_id='.$this->kegiatan->id)->assertOk();

        $this->assertTidakAdaKolomTidakDikenal('absensi');
    }

    /**
     * Kolom yang dipakai kode kelayakan, penerbitan, dan halaman sertifikat (hasil audit pembacaan kode).
     *
     * @return array<string, list<string>>
     */
    public static function kolomYangDipakaiKode(): array
    {
        return [
            'kegiatan_registrations' => ['id', 'kegiatan_id', 'booker_user_id', 'participant_user_id', 'participant_name', 'participant_identity', 'status_pembayaran', 'status_kehadiran', 'checked_in_at'],
            'kegiatan_presensi' => ['id', 'kegiatan_id', 'registration_id', 'user_id', 'nama_lengkap', 'email', 'nim'],
            'kegiatan_gabung' => ['registration_id', 'kegiatan_id', 'user_id', 'pertama_pada', 'jumlah'],
            'sertifikat' => ['id', 'kegiatan_id', 'registration_id', 'user_id', 'penerima_key', 'nomor_sertifikat', 'nomor_urut', 'nama_penerima', 'email_penerima', 'asal_institusi', 'nim_penerima', 'jenis', 'predikat', 'status', 'issued_at', 'document_hash', 'file_pdf_path', 'penandatangan_snapshot', 'dicabut_pada', 'dicabut_oleh', 'alasan_pencabutan', 'keterangan_publik'],
            'sertifikat_riwayat_perubahan' => ['sertifikat_id', 'user_id', 'kolom_diubah', 'nilai_lama', 'nilai_baru', 'alasan'],
            'penandatangan' => ['id', 'nama', 'gelar', 'jabatan', 'jenis_identitas', 'nomor_identitas', 'institusi', 'tanda_tangan_path', 'mode_tampil', 'is_active'],
            'kegiatan_penandatangan' => ['kegiatan_id', 'penandatangan_id', 'jenis_sertifikat', 'urutan'],
            'kegiatan_penilai' => ['kegiatan_id'],
            'kegiatan_submissions' => ['kegiatan_id', 'nilai'],
        ];
    }

    public function test_kolom_yang_dipakai_kode_sertifikat_ada_di_skema(): void
    {
        foreach (self::kolomYangDipakaiKode() as $tabel => $kolom) {
            $this->assertTrue(Schema::hasTable($tabel), "Tabel {$tabel} tidak ada.");
            foreach ($kolom as $nama) {
                $this->assertTrue(Schema::hasColumn($tabel, $nama), "Kolom {$tabel}.{$nama} tidak ada di skema.");
            }
        }

        $this->assertFalse(Schema::hasColumn('kegiatan_registrations', 'status'), 'Kolom status bukan milik kegiatan_registrations; pakai status_pembayaran atau status_kehadiran.');
    }
}
