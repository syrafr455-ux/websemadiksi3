<?php

namespace Tests\Feature;

use App\Enums\KegiatanStatus;
use App\Enums\RegistrationPaymentStatus;
use App\Enums\UserRole;
use App\Jobs\SendProtectedEmailJob;
use App\Mail\SertifikatTerbitMail;
use App\Models\Kegiatan;
use App\Models\KegiatanPresensi;
use App\Models\KegiatanRegistration;
use App\Models\Sertifikat;
use App\Models\User;
use App\Services\EmailProtectionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Keputusan pemilik: penerbitan sertifikat tidak mengirim email (hemat kuota SMTP).
 * Peserta melihat sertifikat di menu mereka; penerima tanpa akun dibagikan admin lewat tautan verifikasi.
 */
class SertifikatTanpaEmailTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function assertTanpaEmail(): void
    {
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Queue::assertNotPushed(SendProtectedEmailJob::class);
        $this->assertSame(0, app(EmailProtectionService::class)->getDailyCount(), 'Kuota harian email tidak boleh bertambah.');
    }

    public function test_terbit_manual_tidak_mengirim_email_walau_email_diisi(): void
    {
        Mail::fake();
        Queue::fake();

        $this->actingAs($this->admin)->post(route('admin.certificates.manual'), [
            'kegiatan_id' => $this->kegiatan->id,
            'nama_penerima' => 'Dr. Pembicara Tamu',
            'asal_institusi' => 'Institut Teknologi Bandung',
            'jenis' => 'pembicara',
            'email_penerima' => 'tamu@itb.ac.id',
        ])->assertRedirect()->assertSessionHas('success');

        $sertifikat = Sertifikat::where('nama_penerima', 'Dr. Pembicara Tamu')->firstOrFail();
        $this->assertSame('tamu@itb.ac.id', $sertifikat->email_penerima, 'Email tetap disimpan untuk arsip.');
        $this->assertTanpaEmail();
    }

    public function test_impor_tidak_mengirim_email(): void
    {
        Mail::fake();
        Queue::fake();

        $csv = "nama,asal_institusi,jenis,predikat,email,nim\nDewi Sartika,UNUSA,pembicara,,dewi@example.com,\n";
        $this->actingAs($this->admin)->post(route('admin.certificates.import'), [
            'kegiatan_id' => $this->kegiatan->id,
            'file' => UploadedFile::fake()->createWithContent('penerima.csv', $csv),
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(1, Sertifikat::where('email_penerima', 'dewi@example.com')->count());
        $this->assertTanpaEmail();
    }

    public function test_terbit_massal_tidak_mengirim_email_dan_sertifikat_tampil_di_menu_penerima_saja(): void
    {
        Mail::fake();
        Queue::fake();

        $penerima = User::factory()->create(['role' => UserRole::MahasiswaKipk, 'nim' => '3130099001']);
        $lain = User::factory()->create(['role' => UserRole::MahasiswaKipk, 'nim' => '3130099002']);

        $registrasi = KegiatanRegistration::create([
            'kegiatan_id' => $this->kegiatan->id,
            'booker_user_id' => $penerima->id,
            'participant_user_id' => $penerima->id,
            'participant_name' => $penerima->name,
            'nomor_tiket' => 'TIK-TANPA-EMAIL',
            'status_pembayaran' => RegistrationPaymentStatus::Paid,
            'checked_in_at' => '2026-10-20 01:30:00',
        ]);
        KegiatanPresensi::create([
            'kegiatan_id' => $this->kegiatan->id,
            'registration_id' => $registrasi->id,
            'user_id' => $penerima->id,
            'nama_lengkap' => $penerima->name,
            'email' => $penerima->email,
            'nim' => $penerima->nim,
            'prodi' => 'S1 Sistem Informasi',
            'nomor_telepon' => '08123456789',
            'signature_path' => 'signatures/x.png',
        ]);

        $this->actingAs($this->admin)->post(route('admin.certificates.batch-issue'), [
            'kegiatan_id' => $this->kegiatan->id,
            'jenis' => 'peserta',
            'recipients' => [[
                'user_id' => $penerima->id,
                'registration_id' => $registrasi->id,
                'nama' => $penerima->name,
                'asal_institusi' => 'Universitas Nahdlatul Ulama Surabaya',
                'email' => $penerima->email,
                'nim' => $penerima->nim,
            ]],
        ])->assertRedirect()->assertSessionHas('success');

        $sertifikat = Sertifikat::where('user_id', $penerima->id)->first();
        $this->assertNotNull($sertifikat, 'Sertifikat massal untuk peserta layak harus terbit.');

        $this->actingAs($penerima)->get(route('dashboard.sertifikat'))->assertOk()->assertSee($sertifikat->nomor_sertifikat);
        $this->actingAs($lain)->get(route('dashboard.sertifikat'))->assertOk()->assertDontSee($sertifikat->nomor_sertifikat);
        $this->assertTanpaEmail();
    }

    public function test_mailable_dan_view_sertifikat_terbit_sudah_dihapus(): void
    {
        $this->assertFalse(class_exists(SertifikatTerbitMail::class));
        $this->assertFileDoesNotExist(resource_path('views/emails/sertifikat_terbit.blade.php'));
        $this->assertTrue(Schema::hasColumn('sertifikat', 'email_penerima'));
    }

    public function test_arsip_menyediakan_tombol_salin_tautan_verifikasi_untuk_penerima_tanpa_akun(): void
    {
        $this->actingAs($this->admin)->post(route('admin.certificates.manual'), [
            'kegiatan_id' => $this->kegiatan->id,
            'nama_penerima' => 'Tamu Tanpa Akun',
            'asal_institusi' => 'ITB',
            'jenis' => 'pembicara',
        ])->assertRedirect();

        $sertifikat = Sertifikat::where('nama_penerima', 'Tamu Tanpa Akun')->firstOrFail();

        $this->actingAs($this->admin)->get(route('admin.certificates', ['tab' => 'arsip']))
            ->assertOk()
            ->assertSee('Salin tautan verifikasi', false)
            ->assertSee(url('/verifikasi?code='.$sertifikat->nomor_sertifikat), false);
    }
}
