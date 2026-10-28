<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\CaraHadir;
use App\Enums\KegiatanStatus;
use App\Enums\RegistrationPaymentStatus;
use App\Enums\UserRole;
use App\Models\Kegiatan;
use App\Models\KegiatanGabung;
use App\Models\KegiatanPresensi;
use App\Models\KegiatanRegistration;
use App\Models\PeriodeAkademik;
use App\Models\User;
use App\Services\BatasKegiatanService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Batas 3 kegiatan yang DIHADIRI per periode akademik, penegakan di pendaftaran, dan halaman periode. */
class BatasKegiatanPerPeriodeTest extends TestCase
{
    use RefreshDatabase;

    private const PESAN_PENUH = 'Kamu sudah menghadiri 3 kegiatan pada Ganjil 2026/2027. Batas dibuka lagi pada periode berikutnya.';

    private User $admin;

    private User $peserta;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        Carbon::setTestNow('2026-10-28 10:00:00');
        $this->admin = User::factory()->superAdmin()->create();
        $this->peserta = User::factory()->mahasiswaKipk()->create();
        PeriodeAkademik::create(['nama' => 'Ganjil 2026/2027', 'tanggal_mulai' => '2026-08-01', 'tanggal_selesai' => '2027-01-31']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function kegiatan(string $mulai, array $tambahan = []): Kegiatan
    {
        static $urut = 0;
        $urut++;

        return Kegiatan::create(array_merge([
            'created_by' => $this->admin->id, 'nama_kegiatan' => "Kegiatan {$urut}", 'kategori' => 'seminar', 'metode_pelaksanaan' => 'offline',
            'sasaran_peserta' => 'umum', 'tanggal_mulai' => $mulai, 'tanggal_selesai' => Carbon::parse($mulai)->addHours(3), 'lokasi_type' => 'external',
            'lokasi_nama' => 'Aula', 'kuota' => 100, 'biaya' => 0, 'file_panduan_pdf' => 'x.pdf', 'status' => KegiatanStatus::Published,
        ], $tambahan));
    }

    /** Pendaftaran gratis yang benar-benar dihadiri (check-in tiket dan presensi akhir bertanda tangan). */
    private function hadir(User $user, Kegiatan $k, bool $presensiAkhir = true): KegiatanRegistration
    {
        $reg = KegiatanRegistration::create([
            'kegiatan_id' => $k->id, 'booker_user_id' => $user->id, 'participant_user_id' => $user->id, 'nomor_tiket' => 'TKT-'.$k->id.'-'.$user->id,
            'status_pembayaran' => RegistrationPaymentStatus::Free, 'status_kehadiran' => AttendanceStatus::CheckedIn, 'checked_in_at' => $k->tanggal_mulai,
        ]);
        if ($presensiAkhir) {
            KegiatanPresensi::create([
                'kegiatan_id' => $k->id, 'registration_id' => $reg->id, 'user_id' => $user->id, 'nama_lengkap' => $user->name, 'nim' => '1', 'prodi' => 'X',
                'email' => $user->email, 'nomor_telepon' => '08', 'signature_path' => 'signatures/kegiatan_presensi/sig.png', 'check_in_at' => $k->tanggal_mulai,
            ]);
        }

        return $reg;
    }

    private function hadirDiTigaKegiatan(?User $user = null): void
    {
        $user ??= $this->peserta;
        foreach (['2026-09-01 09:00', '2026-09-15 09:00', '2026-10-01 09:00'] as $mulai) {
            $this->hadir($user, $this->kegiatan($mulai, ['status' => KegiatanStatus::Completed]));
        }
    }

    private function daftar(Kegiatan $k, ?User $user = null, array $badan = []): TestResponse
    {
        $user ??= $this->peserta;

        return $this->actingAs($user)->post(route('kegiatan.checkout', $k->id), array_merge([
            'delegates' => [['name' => $user->name, 'email' => $user->email]],
        ], $badan));
    }

    private function service(): BatasKegiatanService
    {
        return app(BatasKegiatanService::class);
    }

    // ---------- Aturan hitung ----------

    public function test_dua_hadir_lalu_daftar_lolos_dan_tiga_hadir_lalu_ditolak_dengan_nama_periode(): void
    {
        $this->hadir($this->peserta, $this->kegiatan('2026-09-01 09:00', ['status' => KegiatanStatus::Completed]));
        $this->hadir($this->peserta, $this->kegiatan('2026-09-15 09:00', ['status' => KegiatanStatus::Completed]));
        $baru = $this->kegiatan('2026-11-20 09:00');

        $this->daftar($baru)->assertSessionHasNoErrors();
        $this->assertSame(1, KegiatanRegistration::where('kegiatan_id', $baru->id)->count());
        $this->assertSame(1, $this->service()->sisaJatah($this->peserta));

        $this->hadir($this->peserta, $this->kegiatan('2026-10-01 09:00', ['status' => KegiatanStatus::Completed]));
        $lain = $this->kegiatan('2026-11-25 09:00');
        $this->daftar($lain)->assertSessionHas('error', self::PESAN_PENUH);
        $this->assertSame(0, KegiatanRegistration::where('kegiatan_id', $lain->id)->count(), 'Tidak ada pendaftaran atau tagihan dibuat.');
        $this->assertSame(0, $this->service()->sisaJatah($this->peserta));
    }

    public function test_hanya_terdaftar_tanpa_hadir_kegiatan_non_terbit_dan_periode_lain_tidak_dihitung(): void
    {
        // Terdaftar tetapi tidak hadir sama sekali.
        $this->hadir($this->peserta, $this->kegiatan('2026-09-01 09:00', ['status' => KegiatanStatus::Completed]), false);
        // Check-in awal ada, presensi akhir belum: belum hadir menurut mesin kelayakan.
        $reg = $this->hadir($this->peserta, $this->kegiatan('2026-09-02 09:00', ['status' => KegiatanStatus::Completed]), false);
        // Hadir tetapi kegiatan bukan berstatus terbit.
        $this->hadir($this->peserta, $this->kegiatan('2026-09-03 09:00', ['status' => KegiatanStatus::Cancelled]));
        $this->hadir($this->peserta, $this->kegiatan('2026-09-04 09:00', ['status' => KegiatanStatus::Draft]));
        // Hadir di luar periode mana pun.
        $this->hadir($this->peserta, $this->kegiatan('2026-05-04 09:00', ['status' => KegiatanStatus::Completed]));
        // Registrasi expired.
        $kadaluarsa = $this->hadir($this->peserta, $this->kegiatan('2026-09-05 09:00', ['status' => KegiatanStatus::Completed]));
        $kadaluarsa->update(['status_pembayaran' => RegistrationPaymentStatus::Expired]);

        $this->assertSame(0, $this->service()->hitung($this->peserta));
        $this->daftar($this->kegiatan('2026-11-20 09:00'))->assertSessionHasNoErrors();
        $this->assertNotNull($reg);
    }

    public function test_satu_kegiatan_dengan_dua_tahap_presensi_dihitung_sekali(): void
    {
        $k = $this->kegiatan('2026-09-01 09:00', ['status' => KegiatanStatus::Completed, 'metode_pelaksanaan' => 'hybrid']);
        $reg = $this->hadir($this->peserta, $k);
        KegiatanGabung::create(['kegiatan_id' => $k->id, 'registration_id' => $reg->id, 'user_id' => $this->peserta->id, 'pertama_pada' => now(), 'terakhir_pada' => now(), 'jumlah' => 2]);
        KegiatanPresensi::create([
            'kegiatan_id' => $k->id, 'registration_id' => $reg->id, 'user_id' => $this->peserta->id, 'nama_lengkap' => 'X', 'nim' => '1', 'prodi' => 'X',
            'email' => 'x@example.com', 'nomor_telepon' => '08', 'signature_path' => 'signatures/kegiatan_presensi/sig2.png', 'check_in_at' => now(),
        ]);

        $this->assertSame(1, $this->service()->hitung($this->peserta));
        $this->assertCount(1, $this->service()->kegiatanDihadiri($this->peserta));
    }

    public function test_ganti_periode_mereset_jatah_dan_riwayat_semua_periode_tetap_terlihat(): void
    {
        PeriodeAkademik::create(['nama' => 'Genap 2026/2027', 'tanggal_mulai' => '2027-02-01', 'tanggal_selesai' => '2027-07-31']);
        $this->hadirDiTigaKegiatan();
        $this->assertSame(0, $this->service()->sisaJatah($this->peserta));

        Carbon::setTestNow('2027-03-01 10:00:00');
        $this->assertSame(3, $this->service()->sisaJatah($this->peserta));
        $this->daftar($this->kegiatan('2027-04-10 09:00'))->assertSessionHasNoErrors();

        $riwayat = $this->service()->kegiatanDihadiri($this->peserta);
        $this->assertCount(3, $riwayat);
        $this->assertSame(['Ganjil 2026/2027'], $riwayat->pluck('periode.nama')->unique()->all());
        $this->assertTrue($riwayat->first()['kegiatan']->tanggal_mulai->gte($riwayat->last()['kegiatan']->tanggal_mulai), 'Terbaru di atas.');
    }

    public function test_tanpa_periode_berjalan_batas_tidak_berlaku_dan_super_admin_melihat_peringatan(): void
    {
        PeriodeAkademik::query()->delete();
        $this->hadirDiTigaKegiatan();

        $this->assertNull($this->service()->periodeBerjalan());
        $this->assertNull($this->service()->sisaJatah($this->peserta));
        $this->daftar($this->kegiatan('2026-11-20 09:00'))->assertSessionHasNoErrors();

        $pesan = 'Belum ada periode akademik aktif; batas kegiatan belum berlaku.';
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->assertSee($pesan);
        $this->actingAs($this->admin)->get(route('admin.periode-akademik'))->assertOk()->assertSee($pesan);

        PeriodeAkademik::create(['nama' => 'Ganjil 2026/2027', 'tanggal_mulai' => '2026-08-01', 'tanggal_selesai' => '2027-01-31']);
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->assertDontSee($pesan);
    }

    // ---------- Penegakan di jalur pendaftaran ----------

    public function test_satu_satunya_jalur_pembuat_pendaftaran_adalah_checkout_dan_ditolak_saat_penuh(): void
    {
        // Jalur 0b: KegiatanBookingController::checkout (KegiatanRegistration::create). Tidak ada impor, seeder, atau perintah pembuat pendaftaran.
        $pembuat = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path())) as $berkas) {
            if ($berkas->getExtension() === 'php' && preg_match('/KegiatanRegistration::(create|insert|firstOrCreate|updateOrCreate)\(|registrations\(\)->(create|insert)\(/', file_get_contents($berkas->getPathname()))) {
                $pembuat[] = str_replace('\\', '/', substr($berkas->getPathname(), strlen(app_path()) + 1));
            }
        }
        $this->assertSame(['Http/Controllers/KegiatanBookingController.php'], $pembuat);

        $this->hadirDiTigaKegiatan();
        $baru = $this->kegiatan('2026-11-20 09:00');
        $this->daftar($baru)->assertSessionHas('error', self::PESAN_PENUH);
        // Rute lama yang tampak seperti pendaftaran hanya mengalihkan ke halaman detail.
        $this->actingAs($this->peserta)->post(route('dashboard.kegiatan.daftar', $baru->id))->assertRedirect(route('kegiatan.detail', $baru->id));
        $this->assertSame(0, KegiatanRegistration::where('kegiatan_id', $baru->id)->count());
    }

    public function test_batas_juga_berlaku_untuk_hybrid_onsite_dan_daring_serta_delegasi_dengan_akun(): void
    {
        $this->hadirDiTigaKegiatan();
        $hybrid = $this->kegiatan('2026-11-20 09:00', ['metode_pelaksanaan' => 'hybrid', 'kuota' => 5, 'kuota_onsite' => 2, 'tautan_rapat' => 'https://zoom.us/j/1']);

        $this->daftar($hybrid, null, ['cara_hadir' => 'onsite'])->assertSessionHas('error', self::PESAN_PENUH);
        $this->daftar($hybrid, null, ['cara_hadir' => 'daring'])->assertSessionHas('error', self::PESAN_PENUH);

        // Urutan: batas 3 kegiatan lebih dulu daripada kuota hybrid (daring belum dibuka, tetapi pesan batas yang tampil).
        $pemesan = User::factory()->mahasiswaKipk()->create();
        $res = $this->daftar($hybrid, $pemesan, ['cara_hadir' => 'onsite', 'delegates' => [['name' => 'Pemesan', 'email' => $pemesan->email], ['name' => $this->peserta->name, 'email' => $this->peserta->email]]]);
        $res->assertSessionHas('error', "{$this->peserta->name} sudah menghadiri 3 kegiatan pada Ganjil 2026/2027. Batas dibuka lagi pada periode berikutnya.");
        $this->assertSame(0, KegiatanRegistration::where('kegiatan_id', $hybrid->id)->count(), 'Tidak ada pendaftaran sebagian.');
    }

    public function test_pendaftaran_yang_sudah_ada_tidak_dibatalkan_dan_presensi_tidak_pernah_ditolak_karena_batas(): void
    {
        $terdaftar = $this->kegiatan('2026-11-20 09:00');
        $this->daftar($terdaftar)->assertSessionHasNoErrors();
        $this->hadirDiTigaKegiatan();

        $this->assertSame(RegistrationPaymentStatus::Free, KegiatanRegistration::where('kegiatan_id', $terdaftar->id)->first()->status_pembayaran);

        // Presensi dan pemindaian tiket tidak memanggil layanan batas (kehadiran nyata selalu tercatat).
        foreach (['Http/Controllers/PresensiController.php', 'Http/Controllers/Admin/PresensiScannerController.php', 'Services/Kegiatan/PresensiTicketScannerService.php'] as $berkas) {
            $this->assertStringNotContainsString('BatasKegiatanService', file_get_contents(app_path($berkas)), $berkas);
        }
    }

    // ---------- Tampilan peserta dan admin ----------

    public function test_dashboard_peserta_menampilkan_jatah_dan_riwayat_semua_periode(): void
    {
        PeriodeAkademik::create(['nama' => 'Genap 2025/2026', 'tanggal_mulai' => '2026-02-01', 'tanggal_selesai' => '2026-07-31']);
        $lama = $this->kegiatan('2026-03-10 09:00', ['status' => KegiatanStatus::Completed, 'nama_kegiatan' => 'Seminar Semester Lalu']);
        $this->hadir($this->peserta, $lama);
        $daring = $this->kegiatan('2026-09-01 09:00', ['status' => KegiatanStatus::Completed, 'nama_kegiatan' => 'Webinar Semester Ini', 'metode_pelaksanaan' => 'hybrid']);
        $reg = $this->hadir($this->peserta, $daring);
        $reg->update(['cara_hadir' => CaraHadir::Daring]);

        $res = $this->actingAs($this->peserta)->get(route('dashboard.index'))->assertOk();

        $res->assertSee('Jatah kegiatan Ganjil 2026/2027: 1 dari 3 terpakai');
        $res->assertSee('Kegiatan yang sudah saya ikuti');
        $res->assertSeeInOrder(['Webinar Semester Ini', 'Seminar Semester Lalu']);
        $res->assertSee('Via Zoom');
        $res->assertSee('Genap 2025/2026');
    }

    public function test_detail_kegiatan_membawa_jatah_dan_peserta_lain_tidak_melihat_data_orang_lain(): void
    {
        $this->hadirDiTigaKegiatan();
        $lain = User::factory()->mahasiswaKipk()->create();
        $k = $this->kegiatan('2026-11-20 09:00');

        $props = fn (User $u) => $this->actingAs($u)->get(route('kegiatan.detail', $k->id))->assertOk()->original->getData()['page']['props'];

        $this->assertSame(['berlaku' => true, 'periode' => 'Ganjil 2026/2027', 'batas' => 3, 'terpakai' => 3, 'penuh' => true], $props($this->peserta)['jatah']);
        $this->assertSame(0, $props($lain)['jatah']['terpakai']);
        $this->assertFalse($props($lain)['jatah']['penuh']);
    }

    public function test_panel_kegiatan_diikuti_di_data_pengguna_hanya_untuk_super_admin_dan_read_only(): void
    {
        $this->hadirDiTigaKegiatan();
        $url = route('admin.users.kegiatan-diikuti', $this->peserta->id);

        $res = $this->actingAs($this->admin)->getJson($url)->assertOk();
        $res->assertJsonPath('jatah.terpakai', 3)->assertJsonPath('jatah.periode', 'Ganjil 2026/2027')->assertJsonCount(3, 'kegiatan');

        $this->actingAs(User::factory()->role(UserRole::VerifikatorBerkas)->create())->getJson($url)->assertForbidden();
        $this->actingAs($this->peserta)->getJson($url)->assertForbidden();
        foreach (['post', 'put', 'delete'] as $metode) {
            $this->actingAs($this->admin)->{$metode.'Json'}($url)->assertStatus(405);
        }
    }

    public function test_kueri_agregat_tetap_untuk_satu_pengguna_tanpa_n_plus_satu(): void
    {
        $periode = $this->service()->periodeBerjalan();
        $hitungKueri = function () use ($periode): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->service()->kegiatanDihadiri($this->peserta, $periode);
            $jumlah = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $jumlah;
        };

        $this->hadir($this->peserta, $this->kegiatan('2026-09-01 09:00', ['status' => KegiatanStatus::Completed]));
        $satu = $hitungKueri();
        $this->hadir($this->peserta, $this->kegiatan('2026-09-10 09:00', ['status' => KegiatanStatus::Completed]));
        $this->hadir($this->peserta, $this->kegiatan('2026-09-20 09:00', ['status' => KegiatanStatus::Completed]));
        $this->hadir($this->peserta, $this->kegiatan('2026-10-02 09:00', ['status' => KegiatanStatus::Completed]));

        $this->assertSame($satu, $hitungKueri());
        $this->assertLessThanOrEqual(4, $satu);
    }

    // ---------- Halaman periode akademik ----------

    public function test_validasi_periode_tumpang_tindih_selesai_sebelum_mulai_dan_nama_terlalu_panjang(): void
    {
        $kirim = fn (array $t) => $this->actingAs($this->admin)->post(route('admin.periode-akademik.store'), array_merge(['nama' => 'Genap 2026/2027', 'tanggal_mulai' => '2027-02-01', 'tanggal_selesai' => '2027-07-31'], $t));

        $kirim(['nama' => str_repeat('N', 100)])->assertSessionHasNoErrors();
        $kirim(['nama' => str_repeat('N', 101), 'tanggal_mulai' => '2028-02-01', 'tanggal_selesai' => '2028-07-31'])->assertSessionHasErrors('nama');
        $kirim(['tanggal_mulai' => '2028-02-01', 'tanggal_selesai' => '2028-01-31'])->assertSessionHasErrors('tanggal_selesai');
        $kirim(['nama' => 'Bentrok', 'tanggal_mulai' => '2027-01-15', 'tanggal_selesai' => '2027-02-15'])->assertSessionHasErrors('tanggal_selesai');
        $this->assertSame('Periode tumpang tindih dengan Ganjil 2026/2027.', session('errors')->first('tanggal_selesai'));
        $kirim(['nama' => 'Tanggal Salah', 'tanggal_mulai' => 'bukan-tanggal', 'tanggal_selesai' => '2028-01-31'])->assertSessionHasErrors('tanggal_mulai');
        $kirim(['nama' => '', 'tanggal_mulai' => '2029-01-01', 'tanggal_selesai' => '2029-02-01'])->assertSessionHasErrors('nama');
        $this->assertSame(2, PeriodeAkademik::count());
    }

    public function test_ubah_dan_hapus_periode_serta_hanya_super_admin_yang_boleh(): void
    {
        $periode = PeriodeAkademik::first();

        $this->actingAs($this->admin)->put(route('admin.periode-akademik.update', $periode->id), ['nama' => 'Ganjil Revisi', 'tanggal_mulai' => '2026-08-01', 'tanggal_selesai' => '2027-01-31'])->assertSessionHasNoErrors();
        $this->assertSame('Ganjil Revisi', $periode->fresh()->nama);
        $this->actingAs($this->admin)->put(route('admin.periode-akademik.update', $periode->id), ['nama' => 'X', 'tanggal_mulai' => '2027-01-31', 'tanggal_selesai' => '2026-08-01'])->assertSessionHasErrors('tanggal_selesai');

        foreach ([UserRole::VerifikatorBerkas, UserRole::PenilaiKarya] as $role) {
            $staf = User::factory()->role($role)->create();
            $this->actingAs($staf)->getJson(route('admin.periode-akademik'))->assertForbidden();
            $this->actingAs($staf)->deleteJson(route('admin.periode-akademik.destroy', $periode->id))->assertForbidden();
        }
        $this->actingAs($this->peserta)->get(route('admin.periode-akademik'))->assertRedirect();
        $this->assertSame(1, PeriodeAkademik::count());

        $this->actingAs($this->admin)->delete(route('admin.periode-akademik.destroy', $periode->id))->assertSessionHasNoErrors();
        $this->assertSame(0, PeriodeAkademik::count());
    }

    public function test_tanggal_periode_tidak_diisi_oleh_sistem(): void
    {
        PeriodeAkademik::query()->delete();

        $this->assertSame(0, PeriodeAkademik::count(), 'Tidak ada seeder atau migrasi yang mengisi tanggal semester.');
        $this->assertSame(3, config('unusa.batas_kegiatan_per_periode'));
    }
}
