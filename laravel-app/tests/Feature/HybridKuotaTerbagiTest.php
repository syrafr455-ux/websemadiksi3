<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\CaraHadir;
use App\Enums\KegiatanStatus;
use App\Enums\RegistrationPaymentStatus;
use App\Models\Kegiatan;
use App\Models\KegiatanGabung;
use App\Models\KegiatanPresensi;
use App\Models\KegiatanRegistration;
use App\Models\User;
use App\Services\Kegiatan\DenahKursiService;
use App\Services\Kegiatan\KelayakanSertifikatService;
use App\Services\Kegiatan\KuotaHybridService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Hybrid: dua cara hadir (onsite atau Zoom) dengan kuota terbagi; daring terbuka otomatis setelah onsite penuh. */
class HybridKuotaTerbagiTest extends TestCase
{
    use RefreshDatabase;

    private const ZOOM = 'https://zoom.us/j/99887766554';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->admin = User::factory()->superAdmin()->create();
    }

    private function hybrid(array $tambahan = []): Kegiatan
    {
        return Kegiatan::create(array_merge([
            'created_by' => $this->admin->id, 'nama_kegiatan' => 'Seminar Hybrid', 'kategori' => 'seminar', 'metode_pelaksanaan' => 'hybrid',
            'sasaran_peserta' => 'umum', 'tanggal_mulai' => now()->addDays(10), 'tanggal_selesai' => now()->addDays(10)->addHours(3),
            'lokasi_type' => 'external', 'lokasi_nama' => 'Hotel Contoh', 'lokasi_detail' => 'Jl. Contoh No. 1, Surabaya',
            'kuota' => 5, 'kuota_onsite' => 3, 'biaya' => 0, 'file_panduan_pdf' => 'x.pdf', 'status' => KegiatanStatus::Published,
            'platform_daring' => 'zoom', 'tautan_rapat' => self::ZOOM, 'meeting_id' => '998 8776 6554', 'passcode_rapat' => 'rahasia123',
        ], $tambahan));
    }

    private function daftar(Kegiatan $k, ?User $user = null, array $badan = []): TestResponse
    {
        $user ??= User::factory()->mahasiswaKipk()->create();

        return $this->actingAs($user)->post(route('kegiatan.checkout', $k->id), array_merge([
            'delegates' => [['name' => $user->name, 'email' => $user->email]],
        ], $badan));
    }

    private function aktif(Kegiatan $k, ?CaraHadir $cara = null): int
    {
        return KegiatanRegistration::where('kegiatan_id', $k->id)->active()->when($cara, fn ($q) => $q->where('cara_hadir', $cara->value))->count();
    }

    // ---------- Pilihan cara hadir ----------

    public function test_hybrid_tanpa_cara_hadir_ditolak_dan_nilai_tak_sah_menjadi_galat_validasi_bukan_500(): void
    {
        $k = $this->hybrid();

        $this->daftar($k)->assertSessionHasErrors('cara_hadir');
        $this->daftar($k, null, ['cara_hadir' => 'hibrida'])->assertSessionHasErrors('cara_hadir');
        $pengguna = User::factory()->mahasiswaKipk()->create();
        $this->actingAs($pengguna)->postJson(route('kegiatan.checkout', $k->id), ['cara_hadir' => 'hibrida', 'delegates' => [['name' => 'A']]])->assertStatus(422)->assertJsonValidationErrors('cara_hadir');
        $this->assertSame(0, KegiatanRegistration::count());
    }

    public function test_kegiatan_non_hybrid_menentukan_cara_hadir_otomatis_dan_kuotanya_tidak_berubah(): void
    {
        $offline = $this->hybrid(['metode_pelaksanaan' => 'offline', 'kuota_onsite' => null, 'kuota' => 2]);
        $online = $this->hybrid(['metode_pelaksanaan' => 'online', 'lokasi_type' => 'online', 'kuota_onsite' => null, 'nama_kegiatan' => 'Webinar']);

        $this->daftar($offline)->assertSessionHasNoErrors();
        $this->daftar($online)->assertSessionHasNoErrors();

        $this->assertSame(CaraHadir::Onsite, KegiatanRegistration::where('kegiatan_id', $offline->id)->first()->cara_hadir);
        $this->assertSame(CaraHadir::Daring, KegiatanRegistration::where('kegiatan_id', $online->id)->first()->cara_hadir);

        $this->daftar($offline)->assertSessionHasNoErrors();
        $this->daftar($offline)->assertSessionHas('error'); // kuota total 2 tetap berlaku seperti sebelumnya
        $this->assertSame(2, $this->aktif($offline));
    }

    // ---------- Kuota terbagi ----------

    public function test_onsite_penuh_pada_kuota_onsite_lalu_daring_terbuka_dan_penuh_pada_kuota_daring(): void
    {
        $k = $this->hybrid();

        for ($i = 0; $i < 2; $i++) {
            $this->daftar($k, null, ['cara_hadir' => 'onsite'])->assertSessionHasNoErrors();
        }

        // Daring ditolak selama onsite belum penuh.
        $res = $this->daftar($k, null, ['cara_hadir' => 'daring']);
        $res->assertSessionHas('error', 'Pendaftaran via Zoom dibuka otomatis setelah kuota onsite penuh.');
        $this->assertSame(0, $this->aktif($k, CaraHadir::Daring));

        // Onsite ke-3 (batas tepat) lolos, ke-4 ditolak.
        $this->daftar($k, null, ['cara_hadir' => 'onsite'])->assertSessionHasNoErrors();
        $this->daftar($k, null, ['cara_hadir' => 'onsite'])->assertSessionHas('error', 'Kuota onsite sudah penuh. Pendaftaran via Zoom akan dibuka otomatis.');
        $this->assertSame(3, $this->aktif($k, CaraHadir::Onsite));

        // Daring terbuka: dua diterima, ketiga ditolak.
        $this->daftar($k, null, ['cara_hadir' => 'daring'])->assertSessionHasNoErrors();
        $this->daftar($k, null, ['cara_hadir' => 'daring'])->assertSessionHasNoErrors();
        $this->daftar($k, null, ['cara_hadir' => 'daring'])->assertSessionHas('error', 'Kuota via Zoom sudah penuh.');

        $this->assertSame(2, $this->aktif($k, CaraHadir::Daring));
        $this->assertSame(5, $this->aktif($k), 'Kuota total tidak pernah terlampaui.');
    }

    public function test_pembatalan_onsite_menutup_lagi_pendaftaran_daring_baru_tanpa_mengubah_pendaftar_daring_lama(): void
    {
        $k = $this->hybrid();
        $onsite = [];
        for ($i = 0; $i < 3; $i++) {
            $this->daftar($k, null, ['cara_hadir' => 'onsite']);
        }
        $this->daftar($k, null, ['cara_hadir' => 'daring'])->assertSessionHasNoErrors();
        $daringLama = KegiatanRegistration::where('cara_hadir', 'daring')->firstOrFail();

        KegiatanRegistration::where('cara_hadir', 'onsite')->first()->update(['status_pembayaran' => RegistrationPaymentStatus::Expired]);

        $this->daftar($k, null, ['cara_hadir' => 'daring'])->assertSessionHas('error', 'Pendaftaran via Zoom dibuka otomatis setelah kuota onsite penuh.');
        $this->assertSame(RegistrationPaymentStatus::Free, $daringLama->fresh()->status_pembayaran);
        $this->assertSame(1, $this->aktif($k, CaraHadir::Daring));

        // Kursi onsite yang kosong bisa diisi lagi, lalu daring terbuka kembali.
        $this->daftar($k, null, ['cara_hadir' => 'onsite'])->assertSessionHasNoErrors();
        $this->daftar($k, null, ['cara_hadir' => 'daring'])->assertSessionHasNoErrors();
        $this->assertSame(5, $this->aktif($k));
    }

    public function test_pemesanan_beberapa_delegasi_tidak_boleh_melewati_sisa_kuota(): void
    {
        $k = $this->hybrid();
        $this->daftar($k, null, ['cara_hadir' => 'onsite']);
        $this->daftar($k, null, ['cara_hadir' => 'onsite']);

        $pemesan = User::factory()->mahasiswaKipk()->create();
        $res = $this->daftar($k, $pemesan, ['cara_hadir' => 'onsite', 'delegates' => [['name' => 'A', 'email' => 'a1@example.com'], ['name' => 'B', 'email' => 'b1@example.com']]]);

        $res->assertSessionHas('error', 'Sisa kuota onsite hanya 1, kurang dari 2 peserta yang didaftarkan.');
        $this->assertSame(2, $this->aktif($k), 'Tidak ada pendaftaran sebagian yang tersimpan.');
    }

    public function test_hybrid_lama_tanpa_kuota_onsite_memakai_satu_kuota_total_dan_kedua_cara_hadir_terbuka(): void
    {
        $k = $this->hybrid(['kuota_onsite' => null, 'kuota' => 2]);

        $this->daftar($k, null, ['cara_hadir' => 'daring'])->assertSessionHasNoErrors();
        $this->daftar($k, null, ['cara_hadir' => 'onsite'])->assertSessionHasNoErrors();
        $this->daftar($k, null, ['cara_hadir' => 'onsite'])->assertSessionHas('error');
        $this->assertSame(2, $this->aktif($k));

        $this->assertFalse(app(KuotaHybridService::class)->berpembagian($k));
    }

    public function test_dua_pendaftaran_berurutan_tepat_di_batas_tidak_melewati_kuota(): void
    {
        $k = $this->hybrid(['kuota' => 2, 'kuota_onsite' => 1]);

        $this->daftar($k, null, ['cara_hadir' => 'onsite'])->assertSessionHasNoErrors();
        $this->daftar($k, null, ['cara_hadir' => 'onsite'])->assertSessionHas('error');
        $this->daftar($k, null, ['cara_hadir' => 'daring'])->assertSessionHasNoErrors();
        $this->daftar($k, null, ['cara_hadir' => 'daring'])->assertSessionHas('error');

        $this->assertSame(1, $this->aktif($k, CaraHadir::Onsite));
        $this->assertSame(1, $this->aktif($k, CaraHadir::Daring));
    }

    public function test_penghitungan_dan_pembuatan_pendaftaran_satu_transaksi_dengan_kunci_baris_kegiatan(): void
    {
        // PHPUnit tidak menjalankan dua permintaan paralel. Penjaga 1: urutan di sumber checkout.
        $sumber = file_get_contents(app_path('Http/Controllers/KegiatanBookingController.php'));
        $badan = substr($sumber, strpos($sumber, 'public function checkout('));
        $badan = substr($badan, 0, strpos($badan, 'public function reselectSeat('));
        $urutan = [
            strpos($badan, 'DB::beginTransaction()'),
            strpos($badan, 'lockForUpdate()'),
            strpos($badan, '$kuotaHybrid->periksa('),
            strpos($badan, 'KegiatanRegistration::create('),
            strpos($badan, 'DB::commit()'),
        ];
        $this->assertNotContains(false, $urutan);
        $this->assertSame($urutan, collect($urutan)->sort()->values()->all(), 'beginTransaction < lockForUpdate < periksa kuota < create < commit');

        // Penjaga 2 (PostgreSQL): query kegiatan "for update" dijalankan di dalam transaksi sebelum INSERT pendaftaran.
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->addToAssertionCount(1);

            return;
        }
        $k = $this->hybrid();
        $log = [];
        DB::listen(function ($q) use (&$log) {
            $log[] = ['sql' => strtolower($q->sql), 'level' => DB::transactionLevel()];
        });
        $this->daftar($k, null, ['cara_hadir' => 'onsite'])->assertSessionHasNoErrors();

        $kunci = collect($log)->search(fn ($l) => str_contains($l['sql'], 'from "kegiatan"') && str_contains($l['sql'], 'for update'));
        $sisip = collect($log)->search(fn ($l) => str_contains($l['sql'], 'insert into "kegiatan_registrations"'));
        $this->assertNotFalse($kunci, 'baris kegiatan dikunci');
        $this->assertNotFalse($sisip);
        $this->assertLessThan($sisip, $kunci);
        $this->assertGreaterThan(1, $log[$kunci]['level'], 'kunci diambil di dalam transaksi aplikasi (di atas transaksi tes)');
    }

    // ---------- Kursi, tiket, Zoom, kelayakan ----------

    public function test_daring_tidak_memakai_kursi_denah_dan_onsite_memakainya(): void
    {
        $k = $this->hybrid(['is_seat_booking_active' => true, 'lokasi_type' => 'unusa_campus', 'seat_layout_config' => ['rows' => ['A'], 'cols_per_row' => 3, 'vip_rows' => []], 'kuota' => 4, 'kuota_onsite' => 1]);
        app(DenahKursiService::class)->buat($k, ['rows' => ['A'], 'cols_per_row' => 3, 'vip_rows' => []]);
        $kursi = $k->seats()->where('status', 'available')->where('tier', 'reguler')->orderBy('id')->firstOrFail();

        $this->daftar($k, null, ['cara_hadir' => 'onsite', 'delegates' => [['name' => 'Onsite', 'email' => 'o1@example.com']]])->assertSessionHasErrors('delegates.0.seat_id');
        $this->daftar($k, null, ['cara_hadir' => 'onsite', 'delegates' => [['name' => 'Onsite', 'email' => 'o1@example.com', 'seat_id' => $kursi->id]]])->assertSessionHasNoErrors();
        $this->assertSame($kursi->id, KegiatanRegistration::where('cara_hadir', 'onsite')->value('seat_id'));

        $res = $this->daftar($k, null, ['cara_hadir' => 'daring', 'delegates' => [['name' => 'Daring', 'email' => 'd1@example.com', 'seat_id' => $k->seats()->orderByDesc('id')->value('id')]]]);
        $res->assertSessionHasNoErrors();
        $this->assertNull(KegiatanRegistration::where('cara_hadir', 'daring')->value('seat_id'));
    }

    public function test_kredensial_zoom_tidak_bocor_ke_halaman_peserta_dan_onsite_tidak_bisa_masuk_ruang_zoom(): void
    {
        $k = $this->hybrid(['tanggal_mulai' => now()->addMinutes(5), 'tanggal_selesai' => now()->addHours(3), 'kuota' => 2, 'kuota_onsite' => 1]);
        $onsite = User::factory()->mahasiswaKipk()->create();
        $daring = User::factory()->mahasiswaKipk()->create();
        $this->daftar($k, $onsite, ['cara_hadir' => 'onsite']);
        $this->daftar($k, $daring, ['cara_hadir' => 'daring']);

        $html = $this->actingAs($daring)->get(route('kegiatan.detail', $k->id))->assertOk()->getContent();
        foreach ([self::ZOOM, '998 8776 6554', 'rahasia123'] as $rahasia) {
            $this->assertStringNotContainsString($rahasia, $html);
        }

        $this->actingAs($onsite)->postJson(route('kegiatan.gabung', $k->id))->assertForbidden()
            ->assertJsonPath('message', 'Pendaftaran Anda adalah onsite. Ruang Zoom hanya untuk peserta yang mendaftar via Zoom.');
        $this->assertSame(0, KegiatanGabung::count());

        $res = $this->actingAs($daring)->postJson(route('kegiatan.gabung', $k->id));
        $res->assertOk()->assertJsonPath('join_url', self::ZOOM);
        $this->assertSame(1, KegiatanGabung::count());
    }

    public function test_kelayakan_sertifikat_peserta_daring_pada_hybrid_mengikuti_gabung_dan_presensi_akhir(): void
    {
        $k = $this->hybrid(['tanggal_mulai' => now()->subDays(2), 'tanggal_selesai' => now()->subDay()]);
        $user = User::factory()->mahasiswaKipk()->create();
        $reg = KegiatanRegistration::create([
            'kegiatan_id' => $k->id, 'booker_user_id' => $user->id, 'participant_user_id' => $user->id, 'nomor_tiket' => 'TKT-DARING-1',
            'cara_hadir' => CaraHadir::Daring, 'status_pembayaran' => RegistrationPaymentStatus::Free, 'status_kehadiran' => AttendanceStatus::Registered,
        ]);
        $mesin = app(KelayakanSertifikatService::class);

        $this->assertSame('Belum check-in awal atau gabung', $mesin->evaluasiPeserta($k, $reg)['alasan']);

        KegiatanGabung::create(['kegiatan_id' => $k->id, 'registration_id' => $reg->id, 'user_id' => $user->id, 'pertama_pada' => now(), 'terakhir_pada' => now(), 'jumlah' => 1]);
        $reg = $reg->fresh();
        $this->assertSame('Belum presensi akhir', $mesin->evaluasiPeserta($k, $reg)['alasan']);

        KegiatanPresensi::create([
            'kegiatan_id' => $k->id, 'registration_id' => $reg->id, 'user_id' => $user->id, 'nama_lengkap' => $user->name, 'nim' => '123', 'prodi' => 'X',
            'email' => $user->email, 'nomor_telepon' => '081', 'signature_path' => 'signatures/kegiatan_presensi/sig.png', 'check_in_at' => now(),
        ]);
        $hasil = $mesin->evaluasiPeserta($k, $reg->fresh());
        $this->assertTrue($hasil['layak']);
        $this->assertSame('gabung', $hasil['detail']['sumber_check_in']);
        $this->assertTrue($mesin->kehadiranTercatat($k, $reg->fresh())['hadir']);
    }

    // ---------- Formulir admin ----------

    private function payloadAdmin(array $tambahan = []): array
    {
        return array_merge([
            'nama_kegiatan' => 'Hybrid Admin', 'kategori' => 'seminar', 'metode_pelaksanaan' => 'hybrid', 'sasaran_peserta' => 'umum',
            'tanggal_mulai' => '2026-12-25 09:00', 'tanggal_selesai' => '2026-12-25 11:00', 'lokasi_type' => 'external', 'lokasi_nama' => 'Hotel Contoh',
            'lokasi_detail' => 'Jl. Contoh 1', 'biaya' => 0, 'kuota' => 5, 'kuota_onsite' => 3, 'tautan_rapat' => self::ZOOM, 'aksi_submit' => 'draft',
        ], $tambahan);
    }

    public function test_kuota_onsite_batas_tepat_dan_batas_plus_satu_pada_formulir(): void
    {
        $kirim = fn (array $t) => $this->actingAs($this->admin)->post(route('admin.kegiatan.store'), $this->payloadAdmin($t));

        foreach ([1, 4] as $ok) {
            $kirim(['nama_kegiatan' => "Onsite {$ok}", 'kuota_onsite' => $ok])->assertSessionHasNoErrors();
            $this->assertSame($ok, Kegiatan::where('nama_kegiatan', "Onsite {$ok}")->value('kuota_onsite'));
        }

        foreach ([0, 5] as $salah) {
            $kirim(['nama_kegiatan' => "Onsite {$salah}", 'kuota_onsite' => $salah])->assertSessionHasErrors('kuota_onsite');
            $this->assertSame('Kuota onsite harus antara 1 dan 4. Sisanya otomatis menjadi kuota daring.', session('errors')->first('kuota_onsite'));
            $this->assertSame(0, Kegiatan::where('nama_kegiatan', "Onsite {$salah}")->count());
        }

        $kirim(['nama_kegiatan' => 'Raksasa', 'kuota' => 100000, 'kuota_onsite' => 100001])->assertSessionHasErrors('kuota_onsite');
        $kirim(['nama_kegiatan' => 'Tanpa Onsite', 'kuota_onsite' => null])->assertSessionHasErrors('kuota_onsite');
        $kirim(['nama_kegiatan' => 'Non Hybrid', 'metode_pelaksanaan' => 'offline', 'kuota_onsite' => 2, 'tautan_rapat' => null])->assertSessionHasNoErrors();
        $this->assertNull(Kegiatan::where('nama_kegiatan', 'Non Hybrid')->value('kuota_onsite'), 'Non-hybrid mengosongkan kuota_onsite.');
    }

    public function test_kuota_onsite_tidak_boleh_melebihi_jumlah_kursi_denah(): void
    {
        $kirim = fn (int $onsite, string $nama) => $this->actingAs($this->admin)->post(route('admin.kegiatan.store'), $this->payloadAdmin([
            'nama_kegiatan' => $nama, 'lokasi_type' => 'unusa_campus', 'lokasi_kampus' => 'Kampus B (Jemursari)', 'lokasi_gedung' => 'Tower UNUSA', 'lokasi_ruangan' => 'Auditorium Lt. 9',
            'lokasi_nama' => 'Tower UNUSA', 'is_seat_booking_active' => true, 'seat_layout_config' => ['rows' => ['A', 'B'], 'cols_per_row' => 3],
            'kuota' => 20, 'kuota_onsite' => $onsite,
        ]));

        $kirim(6, 'Denah Pas')->assertSessionHasNoErrors();
        $kirim(7, 'Denah Lebih')->assertSessionHasErrors('kuota_onsite');
        $this->assertSame('Kuota onsite (7) melebihi jumlah kursi denah ruangan (6).', session('errors')->first('kuota_onsite'));
    }

    public function test_menurunkan_kuota_di_bawah_pendaftar_aktif_ditolak_pada_edit(): void
    {
        $k = $this->hybrid();
        foreach (range(1, 3) as $i) {
            $this->daftar($k, null, ['cara_hadir' => 'onsite'])->assertSessionHasNoErrors();
        }
        foreach (range(1, 2) as $i) {
            $this->daftar($k, null, ['cara_hadir' => 'daring'])->assertSessionHasNoErrors();
        }

        $ubah = fn (array $t) => $this->actingAs($this->admin)->put(route('admin.kegiatan.update', $k->id), $t);

        $ubah(['kuota_onsite' => 2])->assertSessionHasErrors('kuota_onsite');
        $this->assertSame('Kuota onsite tidak boleh lebih kecil dari pendaftar onsite aktif (3).', session('errors')->first('kuota_onsite'));

        $ubah(['kuota_onsite' => 4])->assertSessionHasErrors('kuota_onsite');
        $this->assertSame('Kuota daring (1) tidak boleh lebih kecil dari pendaftar daring aktif (2).', session('errors')->first('kuota_onsite'));

        $ubah(['kuota_onsite' => 3])->assertSessionHasNoErrors();
        $this->assertSame(3, $k->fresh()->kuota_onsite);
    }

    public function test_ajukan_hybrid_tanpa_bagian_lengkap_ditolak_dengan_pesan_per_bagian(): void
    {
        $k = $this->hybrid(['status' => KegiatanStatus::Draft, 'tautan_rapat' => null, 'kuota_onsite' => null, 'lokasi_type' => 'external', 'lokasi_detail' => null]);
        User::factory()->superAdmin()->create();

        $res = $this->actingAs($this->admin)->post(route('admin.kegiatan.ajukan', $k->id));

        $res->assertSessionHas('error');
        $pesan = session('error');
        $this->assertStringContainsString('Onsite: alamat atau tautan peta venue eksternal wajib diisi.', $pesan);
        $this->assertStringContainsString('Via Zoom: tautan rapat wajib diisi.', $pesan);
        $this->assertStringContainsString('Kuota: kuota onsite wajib diisi.', $pesan);
        $this->assertSame(KegiatanStatus::Draft, $k->fresh()->status);

        $k->update(['tautan_rapat' => self::ZOOM, 'kuota_onsite' => 3, 'lokasi_detail' => 'Jl. Contoh 1']);
        $this->actingAs($this->admin)->post(route('admin.kegiatan.ajukan', $k->id));
        $this->assertSame(KegiatanStatus::Diajukan, $k->fresh()->status);
    }

    public function test_hybrid_lama_tidak_diblokir_saat_diedit_dan_admin_melihat_peringatan_kuning(): void
    {
        $k = $this->hybrid(['kuota_onsite' => null, 'tautan_rapat' => null, 'status' => KegiatanStatus::Draft]);

        $this->actingAs($this->admin)->put(route('admin.kegiatan.update', $k->id), ['nama_kegiatan' => 'Nama Baru'])->assertSessionHasNoErrors();
        $this->assertSame('Nama Baru', $k->fresh()->nama_kegiatan);

        $props = $this->actingAs($this->admin)->get(route('admin.kegiatan.show', $k->id))->assertOk()->original->getData()['page']['props'];
        $this->assertSame(['Atur pembagian kuota onsite dan daring.', 'Tautan Zoom belum diisi.'], $props['kegiatan']['peringatan_hybrid']);
        $this->assertNull($props['kegiatan']['ringkasan_kuota_hybrid']);
    }

    public function test_admin_melihat_ringkasan_kuota_hybrid_berpembagian(): void
    {
        $k = $this->hybrid(['kuota' => 5, 'kuota_onsite' => 2]);
        $this->daftar($k, null, ['cara_hadir' => 'onsite']);
        $this->daftar($k, null, ['cara_hadir' => 'onsite']);
        $this->daftar($k, null, ['cara_hadir' => 'daring']);

        $props = $this->actingAs($this->admin)->get(route('admin.kegiatan.show', $k->id))->assertOk()->original->getData()['page']['props'];
        $this->assertSame('Onsite 2/2 · Daring 1/3 · Daring: terbuka', $props['kegiatan']['ringkasan_kuota_hybrid']);
        $this->assertSame([], $props['kegiatan']['peringatan_hybrid']);

        $daftar = $this->actingAs($this->admin)->get(route('admin.kegiatan'))->assertOk()->original->getData()['page']['props'];
        $baris = collect($daftar['kegiatans']['data'])->firstWhere('id', $k->id);
        $this->assertSame('Onsite 2/2 · Daring 1/3 · Daring: terbuka', $baris['ringkasan_kuota_hybrid']);
        $this->assertSame(2, $baris['kuota_onsite']);
    }

    public function test_halaman_detail_peserta_membawa_status_kartu_pilihan_tiga_keadaan(): void
    {
        $k = $this->hybrid(['kuota' => 3, 'kuota_onsite' => 2]);
        $pengguna = User::factory()->mahasiswaKipk()->create();
        $props = fn () => $this->actingAs($pengguna)->get(route('kegiatan.detail', $k->id))->assertOk()->original->getData()['page']['props'];

        $belum = $props()['hybrid'];
        $this->assertSame('belum_dibuka', $belum['daring']['status']);
        $this->assertSame(2, $belum['onsite']['sisa']);
        $this->assertSame(1, $belum['daring']['kuota']);

        $this->daftar($k, null, ['cara_hadir' => 'onsite']);
        $this->daftar($k, null, ['cara_hadir' => 'onsite']);
        $buka = $props()['hybrid'];
        $this->assertSame('terbuka', $buka['daring']['status']);
        $this->assertTrue($buka['onsite']['penuh']);

        $this->daftar($k, null, ['cara_hadir' => 'daring']);
        $this->assertSame('penuh', $props()['hybrid']['daring']['status']);
        $this->assertFalse($props()['hybrid']['tersedia']);
        $this->assertSame(0, $props()['pendaftaran']['kuota_tersisa']);
    }
}
