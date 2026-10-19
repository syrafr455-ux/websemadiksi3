<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\PenerimaKipk;
use App\Models\User;
use App\Notifications\StatusKipkDiperbarui;
use App\Services\Kip\KlaimKipService;
use App\Support\KemiripanNama;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KlaimKipTest extends TestCase
{
    use RefreshDatabase;

    protected User $mahasiswa;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Storage::fake('local');
        $this->mahasiswa = $this->umumUnusa('2241010001', 'Fauzan Hidayat');
    }

    protected function umumUnusa(string $nim, string $nama): User
    {
        return User::factory()->role(UserRole::UserUmum)->create([
            'name' => $nama, 'nim' => $nim, 'jalur_pendaftaran' => User::JALUR_UNUSA,
            'asal_kampus' => 'Universitas Nahdlatul Ulama Surabaya',
        ]);
    }

    protected function penerima(string $nomor, string $nama, ?string $nim = null, array $atribut = []): PenerimaKipk
    {
        $penerima = (new PenerimaKipk(['nama_lengkap' => $nama, 'nim' => $nim] + $atribut))->aturNomor($nomor);
        $penerima->save();

        return $penerima;
    }

    protected function klaim(User $user, string $nomor, string $ip = '10.1.1.1')
    {
        return $this->actingAs($user)->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post(route('dashboard.klaim-kip'), ['nomor_pendaftaran' => $nomor]);
    }

    public function test_matching_number_and_name_upgrades_account(): void
    {
        $penerima = $this->penerima('1234.567.89012.1766.761', 'FAUZAN HIDAYAT');

        $this->klaim($this->mahasiswa, '1234 567 89012 1766 761')
            ->assertRedirect(route('dashboard.index'))
            ->assertSessionHas('klaim_status', KlaimKipService::BERHASIL)
            ->assertSessionMissing('_old_input');

        $this->mahasiswa->refresh();
        $penerima->refresh();
        $this->assertSame(UserRole::MahasiswaKipk, $this->mahasiswa->role);
        $this->assertSame($this->mahasiswa->id, $penerima->user_id);
        $this->assertSame('klaim_nomor', $penerima->cara_terhubung);
        $this->assertSame('2241010001', $penerima->nim, 'NIM di baris diisi dari akun bila kosong.');
        Notification::assertSentTo($this->mahasiswa, StatusKipkDiperbarui::class);
        $this->assertTrue(AuditLog::where('action', 'status_kip_berubah')->exists());

        // Pemilik hanya melihat nomor tersamar.
        $this->actingAs($this->mahasiswa)->get(route('dashboard.index'))->assertOk()
            ->assertSee('****.***.*****.1766.761')
            ->assertDontSee('1234567890121766761')->assertDontSee('1234.567.89012.1766.761');

        $this->assertStringNotContainsString('1234567890121766761', json_encode(AuditLog::all()->toArray()));
    }

    public function test_failures_use_the_same_neutral_message(): void
    {
        $lain = User::factory()->role(UserRole::MahasiswaKipk)->create();
        $this->penerima('1111.222.33333.4444.555', 'Fauzan Hidayat', null, ['user_id' => $lain->id]);

        $milikOrangLain = $this->klaim($this->mahasiswa, '1111.222.33333.4444.555');
        $tidakAda = $this->klaim($this->mahasiswa, '9999.999.99999.9999.999');

        $milikOrangLain->assertSessionHas('klaim_pesan', KlaimKipService::PESAN_GAGAL);
        $tidakAda->assertSessionHas('klaim_pesan', KlaimKipService::PESAN_GAGAL);
        $this->assertSame(UserRole::UserUmum, $this->mahasiswa->fresh()->role);

        // Alasan sebenarnya hanya tercatat untuk admin.
        $this->assertEqualsCanonicalizing(
            ['terhubung_akun_lain', 'tidak_ditemukan'],
            AuditLog::where('action', 'klaim_kip_gagal')->get()->pluck('metadata.alasan')->all()
        );
    }

    public function test_dissimilar_name_is_sent_for_review_without_role_upgrade(): void
    {
        $penerima = $this->penerima('2222.333.44444.5555.666', 'Siti Rahmawati');

        $this->klaim($this->mahasiswa, '2222.333.44444.5555.666')->assertSessionHas('klaim_status', KlaimKipService::DITINJAU);

        $penerima->refresh();
        $this->assertSame(PenerimaKipk::TINJAU_PERLU, $penerima->status_tinjau);
        $this->assertSame(PenerimaKipk::ALASAN_KLAIM_NAMA, $penerima->alasan_tinjau);
        $this->assertSame($this->mahasiswa->id, $penerima->klaim_user_id);
        $this->assertNull($penerima->user_id);
        $this->assertSame(UserRole::UserUmum, $this->mahasiswa->fresh()->role);

        $this->actingAs($this->mahasiswa)->get(route('dashboard.index'))->assertSee('Klaim Anda sedang ditinjau admin');

        // Akun lain tidak bisa menyerobot baris yang sedang diklaim.
        $lain = $this->umumUnusa('2241010099', 'Siti Rahmawati');
        $this->klaim($lain, '2222.333.44444.5555.666', '10.2.2.2')->assertSessionHas('klaim_pesan', KlaimKipService::PESAN_GAGAL);
    }

    public function test_different_nim_on_row_is_rejected(): void
    {
        $this->penerima('3333.444.55555.6666.777', 'Fauzan Hidayat', '2241019999');

        $this->klaim($this->mahasiswa, '3333.444.55555.6666.777')->assertSessionHas('klaim_pesan', KlaimKipService::PESAN_GAGAL);
        $this->assertSame(UserRole::UserUmum, $this->mahasiswa->fresh()->role);
    }

    public function test_attempts_are_limited_per_account_and_per_ip(): void
    {
        $this->penerima('1234.567.89012.1766.761', 'Fauzan Hidayat');

        foreach (range(1, 5) as $i) {
            $this->klaim($this->mahasiswa, "0000.000.00000.0000.00{$i}", "10.3.3.{$i}");
        }
        $this->klaim($this->mahasiswa, '1234.567.89012.1766.761', '10.3.3.9')->assertSessionHas('klaim_status', KlaimKipService::TERKUNCI);
        $this->assertSame(UserRole::UserUmum, $this->mahasiswa->fresh()->role);

        // Per IP: akun lain dari IP yang sudah dipakai 5 kali juga terkunci.
        $lain = $this->umumUnusa('2241010002', 'Budi Santoso');
        foreach (range(1, 5) as $i) {
            $this->klaim($this->umumUnusa("22410100{$i}0", "Akun {$i}"), '0000.000.00000.0000.000', '10.4.4.4');
        }
        $this->klaim($lain, '0000.000.00000.0000.000', '10.4.4.4')->assertSessionHas('klaim_status', KlaimKipService::TERKUNCI);
    }

    public function test_only_unusa_user_umum_can_claim(): void
    {
        $this->penerima('1234.567.89012.1766.761', 'Fauzan Hidayat');
        $umumLuar = User::factory()->role(UserRole::UserUmum)->create(['jalur_pendaftaran' => User::JALUR_UMUM]);
        $kipk = User::factory()->role(UserRole::MahasiswaKipk)->create();

        $this->klaim($umumLuar, '1234.567.89012.1766.761')->assertForbidden();
        $this->klaim($kipk, '1234.567.89012.1766.761')->assertForbidden();
        $this->actingAs($kipk)->get(route('dashboard.index'))->assertOk()->assertDontSee('Saya penerima KIP Kuliah');
        $this->actingAs($this->mahasiswa)->get(route('dashboard.index'))->assertSee('Saya penerima KIP Kuliah');
    }

    protected function imporUlang(string $csv, bool $daftarLengkap = true): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin)->post(route('admin.penerima-kipk.unggah'), ['file' => UploadedFile::fake()->createWithContent('daftar.csv', $csv)]);
        $this->actingAs($admin)->post(route('admin.penerima-kipk.impor'), $daftarLengkap ? ['daftar_lengkap' => '1'] : [])
            ->assertSessionHas('success')->assertSessionHas('info_sinkron');
    }

    public function test_reimport_upgrades_matching_unusa_account_by_nim_and_flags_missing_without_downgrade(): void
    {
        $kipkLama = User::factory()->role(UserRole::MahasiswaKipk)->create(['nim' => '2241010050']);
        $lama = $this->penerima('5555.666.77777.8888.999', 'Penerima Lama', '2241010050', ['user_id' => $kipkLama->id]);

        $this->imporUlang("nomor_pendaftaran,nama_lengkap,nim\n1234.567.89012.1766.761,Fauzan Hidayat,2241010001\n");

        $this->assertSame(UserRole::MahasiswaKipk, $this->mahasiswa->fresh()->role);
        $this->assertSame('impor_nim', $this->mahasiswa->fresh()->penerimaKipk->cara_terhubung);
        Notification::assertSentTo($this->mahasiswa, StatusKipkDiperbarui::class);

        // Penerima yang hilang dari daftar: ditandai, role akun tetap.
        $lama->refresh();
        $this->assertSame(PenerimaKipk::TINJAU_PERLU, $lama->status_tinjau);
        $this->assertSame(PenerimaKipk::ALASAN_HILANG, $lama->alasan_tinjau);
        $this->assertSame($kipkLama->id, $lama->user_id);
        $this->assertSame(UserRole::MahasiswaKipk, $kipkLama->fresh()->role);
        $this->assertTrue(AuditLog::where('action', 'penerima_kipk_hilang_dari_impor')->exists());

        // Muncul lagi di impor berikutnya -> penanda dicabut.
        $this->imporUlang("nomor_pendaftaran,nama_lengkap,nim\n5555.666.77777.8888.999,Penerima Lama,2241010050\n1234.567.89012.1766.761,Fauzan Hidayat,2241010001\n");
        $this->assertSame(PenerimaKipk::TINJAU_NORMAL, $lama->fresh()->status_tinjau);
    }

    public function test_partial_import_does_not_flag_missing_recipients(): void
    {
        $lama = $this->penerima('5555.666.77777.8888.999', 'Penerima Lama');

        $this->imporUlang("nomor_pendaftaran,nama_lengkap\n1234.567.89012.1766.761,Orang Baru\n", daftarLengkap: false);

        $this->assertSame(PenerimaKipk::TINJAU_NORMAL, $lama->fresh()->status_tinjau);
    }

    public function test_name_similarity_threshold(): void
    {
        $this->assertSame(0.85, KemiripanNama::AMBANG);
        $this->assertTrue(KemiripanNama::cocok('Dr. Siti Aminah, S.Kom., M.Kom.', 'siti aminah'));
        $this->assertTrue(KemiripanNama::cocok('Muhamad Rizki Pratama', 'Muhammad Rizki Pratama'));
        $this->assertTrue(KemiripanNama::cocok('Siti Aminah', 'Siti Aminah Putri'));
        $this->assertTrue(KemiripanNama::cocok('Ahmad Fauzan', 'Fauzan Ahmad'));
        $this->assertFalse(KemiripanNama::cocok('Siti Aminah', 'Siti Rahmah'));
        $this->assertFalse(KemiripanNama::cocok('Budi Santoso', 'Bayu Santosa'));
        $this->assertFalse(KemiripanNama::cocok('Rina', 'Rina Kartika'), 'Satu kata saja tidak cukup; diteruskan ke tinjauan.');
    }
}
