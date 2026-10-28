<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\CaraHadir;
use App\Enums\GatewayPaymentStatus;
use App\Enums\JenisSertifikat;
use App\Enums\KegiatanStatus;
use App\Enums\PeranSertifikat;
use App\Enums\RegistrationPaymentStatus;
use App\Enums\SeatStatus;
use App\Enums\SeatTier;
use App\Enums\StatusSertifikat;
use App\Enums\UserRole;
use App\Models\Kegiatan;
use App\Models\KegiatanPenandatangan;
use App\Models\KegiatanRegistration;
use App\Models\KegiatanSeat;
use App\Models\Payment;
use App\Models\Penandatangan;
use App\Models\Sertifikat;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Setiap nilai enum yang diizinkan kode harus bisa disimpan ke tabelnya. SQLite tidak menegakkan batasan CHECK
 * maupun panjang varchar, jadi tes ini baru bermakna di suite PostgreSQL (php artisan test --configuration=phpunit.pgsql.xml),
 * tetapi tetap dijalankan di kedua suite.
 */
class NilaiEnumDanBatasanKolomTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<string> */
    private static function nilai(string $enum): array
    {
        return array_map(static fn ($kasus): string => $kasus->value, $enum::cases());
    }

    /**
     * Daftar kolom ber-CHECK (tabel.kolom => nilai yang diizinkan kode). Tambahkan di sini setiap kali ada
     * $table->enum(...) atau CHECK baru; tes katalog di bawah gagal bila ada batasan yang belum terdaftar.
     *
     * @return array<string, list<string>>
     */
    private static function batasanEnum(): array
    {
        return [
            'kegiatan.kategori' => Kegiatan::KATEGORI,
            'kegiatan.metode_pelaksanaan' => Kegiatan::METODE,
            'kegiatan.sasaran_peserta' => Kegiatan::SASARAN,
            'kegiatan.lokasi_type' => Kegiatan::LOKASI_TYPES,
            'kegiatan.status' => self::nilai(KegiatanStatus::class),
            'kegiatan_seats.tier' => self::nilai(SeatTier::class),
            'kegiatan_seats.status' => self::nilai(SeatStatus::class),
            'payments.payment_status' => self::nilai(GatewayPaymentStatus::class),
            'kegiatan_registrations.status_kehadiran' => self::nilai(AttendanceStatus::class),
            'kegiatan_registrations.status_pembayaran' => self::nilai(RegistrationPaymentStatus::class),
            'kegiatan_registrations.cara_hadir' => self::nilai(CaraHadir::class),
            'sertifikat.peran_sertifikat' => self::nilai(PeranSertifikat::class),
            'users.role' => self::nilai(UserRole::class),
        ];
    }

    private function dasarKegiatan(User $pembuat): array
    {
        return [
            'created_by' => $pembuat->id, 'nama_kegiatan' => 'Kegiatan Uji Enum', 'kategori' => 'seminar', 'metode_pelaksanaan' => 'offline',
            'sasaran_peserta' => 'umum', 'tanggal_mulai' => now()->addDays(2), 'tanggal_selesai' => now()->addDays(2)->addHours(2),
            'lokasi_type' => 'unusa_campus', 'lokasi_nama' => 'Auditorium', 'kuota' => 100, 'biaya' => 0,
            'file_panduan_pdf' => 'kegiatan/panduan.pdf', 'status' => KegiatanStatus::Draft,
        ];
    }

    /** Nilai mentah di basis data (bukan hasil cast), untuk membuktikan baris benar-benar tersimpan. */
    private function tersimpan(string $tabel, int $id, string $kolom): string
    {
        return (string) DB::table($tabel)->where('id', $id)->value($kolom);
    }

    public function test_setiap_nilai_enum_kolom_kegiatan_tersimpan(): void
    {
        $admin = User::factory()->superAdmin()->create();

        foreach (['kategori' => Kegiatan::KATEGORI, 'metode_pelaksanaan' => Kegiatan::METODE, 'sasaran_peserta' => Kegiatan::SASARAN, 'lokasi_type' => Kegiatan::LOKASI_TYPES] as $kolom => $daftar) {
            foreach ($daftar as $nilai) {
                $kegiatan = Kegiatan::create([$kolom => $nilai] + $this->dasarKegiatan($admin));
                $this->assertSame($nilai, $this->tersimpan('kegiatan', $kegiatan->id, $kolom), "kegiatan.{$kolom} = {$nilai}");
            }
        }

        foreach (KegiatanStatus::cases() as $status) {
            $kegiatan = Kegiatan::create(['status' => $status] + $this->dasarKegiatan($admin));
            $this->assertSame($status->value, $this->tersimpan('kegiatan', $kegiatan->id, 'status'), "kegiatan.status = {$status->value}");
        }
    }

    public function test_setiap_nilai_enum_kursi_pembayaran_dan_registrasi_tersimpan(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $peserta = User::factory()->mahasiswaKipk()->create();
        $kegiatan = Kegiatan::create($this->dasarKegiatan($admin));

        $urut = 0;
        foreach (SeatTier::cases() as $tier) {
            $kursi = KegiatanSeat::create(['kegiatan_id' => $kegiatan->id, 'seat_code' => 'T-'.++$urut, 'row_label' => 'T', 'seat_number' => $urut, 'col_number' => $urut, 'tier' => $tier, 'status' => SeatStatus::Available]);
            $this->assertSame($tier->value, $this->tersimpan('kegiatan_seats', $kursi->id, 'tier'));
        }
        foreach (SeatStatus::cases() as $status) {
            $kursi = KegiatanSeat::create(['kegiatan_id' => $kegiatan->id, 'seat_code' => 'S-'.++$urut, 'row_label' => 'S', 'seat_number' => $urut, 'col_number' => $urut, 'tier' => SeatTier::Reguler, 'status' => $status]);
            $this->assertSame($status->value, $this->tersimpan('kegiatan_seats', $kursi->id, 'status'));
        }

        foreach (GatewayPaymentStatus::cases() as $status) {
            $pembayaran = Payment::create(['order_id' => 'ORD-ENUM-'.$status->value, 'user_id' => $peserta->id, 'gross_amount' => 10000, 'payment_method' => 'qris', 'payment_status' => $status]);
            $this->assertSame($status->value, $this->tersimpan('payments', $pembayaran->id, 'payment_status'));
        }

        // Registrasi aktif (free/pending/paid) dibatasi satu per peserta, jadi tiap nilai memakai kegiatan sendiri.
        foreach (RegistrationPaymentStatus::cases() as $status) {
            foreach (AttendanceStatus::cases() as $kehadiran) {
                $kegiatanReg = Kegiatan::create($this->dasarKegiatan($admin));
                $registrasi = KegiatanRegistration::create([
                    'kegiatan_id' => $kegiatanReg->id, 'booker_user_id' => $peserta->id, 'participant_user_id' => $peserta->id,
                    'nomor_tiket' => "TKT-ENUM-{$status->value}-{$kehadiran->value}", 'status_pembayaran' => $status, 'status_kehadiran' => $kehadiran,
                ]);
                $this->assertSame($status->value, $this->tersimpan('kegiatan_registrations', $registrasi->id, 'status_pembayaran'));
                $this->assertSame($kehadiran->value, $this->tersimpan('kegiatan_registrations', $registrasi->id, 'status_kehadiran'));
            }
        }

        foreach (CaraHadir::cases() as $cara) {
            $kegiatanReg = Kegiatan::create($this->dasarKegiatan($admin));
            $registrasi = KegiatanRegistration::create([
                'kegiatan_id' => $kegiatanReg->id, 'booker_user_id' => $peserta->id, 'participant_user_id' => $peserta->id,
                'nomor_tiket' => "TKT-CARA-{$cara->value}", 'cara_hadir' => $cara, 'status_pembayaran' => RegistrationPaymentStatus::Free,
            ]);
            $this->assertSame($cara->value, $this->tersimpan('kegiatan_registrations', $registrasi->id, 'cara_hadir'));
        }

        $this->assertSame('onsite', $this->tersimpan('kegiatan_registrations', KegiatanRegistration::create([
            'kegiatan_id' => $kegiatan->id, 'booker_user_id' => $peserta->id, 'nomor_tiket' => 'TKT-CARA-LAMA',
        ])->id, 'cara_hadir'), 'Pendaftaran tanpa nilai dianggap onsite.');
    }

    public function test_setiap_nilai_enum_sertifikat_dan_role_pengguna_tersimpan(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $kegiatan = Kegiatan::create($this->dasarKegiatan($admin));

        $urut = 0;
        foreach (PeranSertifikat::cases() as $peran) {
            foreach (JenisSertifikat::cases() as $jenis) {
                foreach (StatusSertifikat::cases() as $status) {
                    $urut++;
                    $sertifikat = Sertifikat::create([
                        'kegiatan_id' => $kegiatan->id, 'nomor_sertifikat' => sprintf('CERT-ENUM-%04d', $urut), 'nama_penerima' => 'Penerima '.$urut,
                        'asal_institusi' => 'UNUSA', 'penerima_key' => Sertifikat::hitungPenerimaKey(null, 'Penerima '.$urut, 'UNUSA'),
                        'peran_sertifikat' => $peran, 'jenis' => $jenis, 'status' => $status, 'issued_at' => now(),
                    ]);
                    $this->assertSame($peran->value, $this->tersimpan('sertifikat', $sertifikat->id, 'peran_sertifikat'));
                    $this->assertSame($jenis->value, $this->tersimpan('sertifikat', $sertifikat->id, 'jenis'));
                    $this->assertSame($status->value, $this->tersimpan('sertifikat', $sertifikat->id, 'status'));
                }
            }
        }

        foreach (UserRole::cases() as $role) {
            $pengguna = User::factory()->role($role)->create();
            $this->assertSame($role->value, $this->tersimpan('users', $pengguna->id, 'role'));
        }
    }

    public function test_kunci_penerima_eksternal_dan_nilai_terpanjang_kolom_sertifikat_tersimpan(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $kegiatan = Kegiatan::create($this->dasarKegiatan($admin));
        $kunci = Sertifikat::hitungPenerimaKey(null, 'Tamu Undangan', 'ITB');
        $this->assertSame(68, strlen($kunci), '"ext:" + SHA-256 heksadesimal.');

        $sertifikat = Sertifikat::create([
            'kegiatan_id' => $kegiatan->id, 'nomor_sertifikat' => 'CERT-BATAS-0001', 'penerima_key' => $kunci,
            'nama_penerima' => str_repeat('N', 255), 'asal_institusi' => str_repeat('I', 255), 'nim_penerima' => str_repeat('1', 50),
            'predikat' => str_repeat('P', 100), 'jenis' => JenisSertifikat::Pembicara, 'status' => StatusSertifikat::Aktif,
            'file_disk' => str_repeat('d', 20), 'file_status' => str_repeat('s', 20), 'issued_at' => now(),
        ]);

        $this->assertSame($kunci, $this->tersimpan('sertifikat', $sertifikat->id, 'penerima_key'));
        $this->assertSame(100, mb_strlen($this->tersimpan('sertifikat', $sertifikat->id, 'predikat')));
    }

    public function test_impor_dengan_nilai_melebihi_batas_kolom_dilewati_dengan_alasan_bukan_error_500(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $admin = User::factory()->superAdmin()->create();
        $kegiatan = Kegiatan::create(['status' => KegiatanStatus::Published, 'tanggal_mulai' => now()->subDays(2), 'tanggal_selesai' => now()->subDay()] + $this->dasarKegiatan($admin));

        $csv = "nama,asal_institusi,jenis,predikat,email,nim\n";
        $csv .= 'Predikat Kepanjangan,UNUSA,pembicara,'.str_repeat('P', 101).",,\n";
        $csv .= 'NIM Kepanjangan,UNUSA,peserta,,,'.str_repeat('1', 51)."\n";
        $csv .= 'Nama Wajar,UNUSA,peserta,,wajar@example.com,3130021001'."\n";

        $res = $this->actingAs($admin)->post(route('admin.certificates.import'), [
            'kegiatan_id' => $kegiatan->id,
            'file' => UploadedFile::fake()->createWithContent('penerima.csv', $csv),
        ]);

        $res->assertRedirect()->assertSessionHas('success');
        $this->assertStringContainsString('2 baris dilewati', session('success'));
        $this->assertSame(['Nama Wajar'], Sertifikat::where('kegiatan_id', $kegiatan->id)->pluck('nama_penerima')->all());
    }

    public function test_penugasan_penandatangan_menolak_jenis_sertifikat_di_luar_daftar(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $kegiatan = Kegiatan::create($this->dasarKegiatan($admin));
        $penandatangan = Penandatangan::create(['nama' => 'Dr. Rektor', 'jabatan' => 'Rektor', 'institusi' => 'UNUSA', 'is_active' => true]);

        foreach ([str_repeat('x', 33), 'tidak-dikenal'] as $jenis) {
            $this->actingAs($admin)->post(route('admin.certificates.penandatangan.assign'), [
                'kegiatan_id' => $kegiatan->id, 'jenis_sertifikat' => $jenis, 'penandatangan_ids' => [$penandatangan->id],
            ])->assertSessionHasErrors('jenis_sertifikat');
        }
        $this->assertSame(0, KegiatanPenandatangan::count());

        foreach (['semua', 'peserta', 'juri', 'pembicara', 'juara'] as $jenis) {
            $this->actingAs($admin)->post(route('admin.certificates.penandatangan.assign'), [
                'kegiatan_id' => $kegiatan->id, 'jenis_sertifikat' => $jenis, 'penandatangan_ids' => [$penandatangan->id],
            ])->assertSessionHasNoErrors();
        }
        $this->assertSame(5, KegiatanPenandatangan::count());
    }

    // ---------- Khusus PostgreSQL: batasan CHECK di katalog harus persis sama dengan enum kode ----------

    /** @return array<string, list<string>> "tabel.kolom" => nilai pada batasan CHECK satu kolom di skema aktif */
    private function batasanDiKatalog(): array
    {
        $baris = DB::select(<<<'SQL'
            select t.relname as tabel, a.attname as kolom, pg_get_constraintdef(c.oid) as definisi
            from pg_constraint c
            join pg_class t on t.oid = c.conrelid
            join pg_namespace n on n.oid = t.relnamespace
            join pg_attribute a on a.attrelid = t.oid and a.attnum = c.conkey[1]
            where c.contype = 'c' and n.nspname = current_schema() and array_length(c.conkey, 1) = 1
            SQL);

        $ditemukan = [];
        foreach ($baris as $b) {
            preg_match_all("/'((?:[^']|'')*)'::/", $b->definisi, $cocok);
            $nilai = array_map(static fn (string $v): string => str_replace("''", "'", $v), $cocok[1]);
            $ditemukan["{$b->tabel}.{$b->kolom}"] = $nilai;
        }

        return $ditemukan;
    }

    public function test_batasan_check_postgresql_sama_dengan_enum_kode(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Katalog pg_constraint hanya ada di PostgreSQL; SQLite tidak menyimpan batasan CHECK enum yang bisa dibandingkan.');
        }

        $ditemukan = $this->batasanDiKatalog();

        foreach (self::batasanEnum() as $kolom => $diharapkan) {
            $this->assertArrayHasKey($kolom, $ditemukan, "Batasan CHECK {$kolom} tidak ada di PostgreSQL.");
            $a = $ditemukan[$kolom];
            $d = $diharapkan;
            sort($a);
            sort($d);
            $this->assertSame($d, $a, "Batasan CHECK {$kolom} berbeda dari enum kode.");
        }

        $takTerdaftar = array_diff(array_keys($ditemukan), array_keys(self::batasanEnum()));
        $this->assertSame([], array_values($takTerdaftar), 'Ada batasan CHECK baru yang belum didaftarkan di batasanEnum().');
    }

    public function test_migrasi_penyelaras_memperbaiki_batasan_check_yang_usang_tanpa_menyentuh_yang_benar(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Migrasi penyelaras hanya bekerja pada PostgreSQL.');
        }

        // Meniru database lama: batasan status kegiatan dibuat sebelum 'diajukan' ada.
        DB::statement('ALTER TABLE kegiatan DROP CONSTRAINT kegiatan_status_check');
        DB::statement("ALTER TABLE kegiatan ADD CONSTRAINT kegiatan_status_check CHECK (status IN ('draft', 'published', 'ongoing', 'completed', 'cancelled'))");
        $admin = User::factory()->superAdmin()->create();

        try {
            DB::transaction(fn () => Kegiatan::create(['status' => KegiatanStatus::Diajukan] + $this->dasarKegiatan($admin)));
            $this->fail("Batasan usang seharusnya menolak status 'diajukan'.");
        } catch (QueryException $e) {
            $this->assertSame('23514', $e->getCode());
        }

        (require database_path('migrations/2026_10_29_000002_selaraskan_batasan_check_enum.php'))->up();
        (require database_path('migrations/2026_10_29_000002_selaraskan_batasan_check_enum.php'))->up(); // idempoten

        $kegiatan = Kegiatan::create(['status' => KegiatanStatus::Diajukan] + $this->dasarKegiatan($admin));
        $this->assertSame('diajukan', $this->tersimpan('kegiatan', $kegiatan->id, 'status'));
        foreach (self::batasanEnum() as $kolom => $diharapkan) {
            $a = $this->batasanDiKatalog()[$kolom];
            sort($a);
            sort($diharapkan);
            $this->assertSame($diharapkan, $a, $kolom);
        }
    }
}
