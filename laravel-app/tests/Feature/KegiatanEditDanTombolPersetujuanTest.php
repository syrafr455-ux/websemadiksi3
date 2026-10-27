<?php

namespace Tests\Feature;

use App\Enums\KegiatanStatus;
use App\Models\Kegiatan;
use App\Models\KegiatanPersetujuan;
use App\Models\User;
use App\Support\AksiPersetujuan;
use App\Support\Wib;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Form edit kegiatan butuh payload daftar yang lengkap (tanpa membocorkan rahasia rapat),
 * dan tombol persetujuan di daftar/detail mengikuti peran serta status. Server tetap penentu akhir.
 */
class KegiatanEditDanTombolPersetujuanTest extends TestCase
{
    use RefreshDatabase;

    private const TAUTAN = 'https://zoom.us/j/rahasia-link-4321';

    private const PASSCODE = 'PASSCODE-RAHASIA-EDIT';

    private const MEETING_ID = 'MEETING-ID-RAHASIA-EDIT';

    /** Kunci yang dibaca kegiatanKeForm() di resources/js/lib/kegiatanForm.js. */
    private const KUNCI_FORM_EDIT = [
        'nama_kegiatan', 'deskripsi', 'kategori', 'sasaran_peserta', 'metode_pelaksanaan',
        'tanggal_mulai', 'tanggal_selesai', 'registration_deadline', 'submission_deadline',
        'lokasi_type', 'lokasi_kampus', 'lokasi_gedung', 'lokasi_ruangan', 'lokasi_nama', 'lokasi_detail',
        'biaya', 'kuota', 'poin_xp', 'narasumber', 'is_seat_booking_active', 'is_submission_active',
        'submission_instructions', 'status', 'seat_layout_config',
        'platform_daring', 'join_button_mode', 'menit_buka_gabung', 'batas_tutup_gabung',
        'izin_tautan_presensi_akhir', 'wajib_tangkapan_layar',
        'tautan_rapat_tersimpan', 'meeting_id_tersimpan', 'passcode_rapat_tersimpan',
    ];

    private const KUNCI_BOOLEAN = [
        'is_seat_booking_active', 'is_submission_active', 'izin_tautan_presensi_akhir', 'wajib_tangkapan_layar',
        'tautan_rapat_tersimpan', 'meeting_id_tersimpan', 'passcode_rapat_tersimpan',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function superAdmin(string $nama = 'Super Admin'): User
    {
        return User::factory()->superAdmin()->create(['name' => $nama]);
    }

    private function buatKegiatan(?User $pembuat, array $attrs = []): Kegiatan
    {
        return Kegiatan::create(array_merge([
            'created_by' => $pembuat?->id,
            'nama_kegiatan' => 'Lomba Essay',
            'deskripsi' => 'Lomba menulis esai.',
            'kategori' => 'lomba',
            'metode_pelaksanaan' => 'offline',
            'sasaran_peserta' => 'umum',
            'tanggal_mulai' => Wib::keUtc('2026-11-20 09:00'),
            'tanggal_selesai' => Wib::keUtc('2026-11-20 12:00'),
            'lokasi_type' => 'unusa_campus',
            'lokasi_nama' => 'Auditorium Tower UNUSA',
            'file_panduan_pdf' => 'kegiatan/panduan.pdf',
            'biaya' => 0,
            'kuota' => 100,
            'status' => KegiatanStatus::Draft,
            'is_seat_booking_active' => false,
            'is_submission_active' => false,
        ], $attrs));
    }

    private function buatKegiatanDaring(?User $pembuat, array $attrs = []): Kegiatan
    {
        return $this->buatKegiatan($pembuat, array_merge([
            'nama_kegiatan' => 'Webinar Daring',
            'metode_pelaksanaan' => 'online',
            'lokasi_type' => 'online',
            'lokasi_nama' => 'Daring (Zoom)',
            'platform_daring' => 'zoom',
            'tautan_rapat' => self::TAUTAN,
            'meeting_id' => self::MEETING_ID,
            'passcode_rapat' => self::PASSCODE,
            'join_button_mode' => 'manual',
            'menit_buka_gabung' => 45,
            'izin_tautan_presensi_akhir' => true,
            'wajib_tangkapan_layar' => true,
        ], $attrs));
    }

    /** Non-super_admin tidak pernah mendapat halaman/aksi: dialihkan oleh middleware atau ditolak policy (403). */
    private function assertAksesAdminDitolak(User $user, TestResponse $response): void
    {
        $this->assertContains($response->getStatusCode(), [302, 403], "Peran {$user->role->value} seharusnya ditolak.");
    }

    /** @return array<string, mixed> props Inertia halaman daftar kegiatan. */
    private function propsDaftar(User $admin, string $query = ''): array
    {
        $res = $this->actingAs($admin)->get(route('admin.kegiatan').$query);
        $res->assertOk();

        return $res->original->getData()['page']['props'];
    }

    private function barisDaftar(User $admin, Kegiatan $kegiatan): array
    {
        $baris = collect($this->propsDaftar($admin)['kegiatans']['data'])->firstWhere('id', $kegiatan->id);
        $this->assertNotNull($baris, 'Kegiatan tidak ada di daftar.');

        return $baris;
    }

    /** @return iterable<string, array{0: string}> */
    public static function kasusKegiatan(): iterable
    {
        yield 'draft daring' => ['draft_daring'];
        yield 'daring terbit' => ['daring_terbit'];
        yield 'hybrid' => ['hybrid'];
        yield 'offline lama tanpa kolom daring' => ['offline_lama'];
    }

    private function buatKasus(string $kasus, User $pembuat): Kegiatan
    {
        return match ($kasus) {
            'draft_daring' => $this->buatKegiatanDaring($pembuat, ['status' => KegiatanStatus::Draft]),
            'daring_terbit' => $this->buatKegiatanDaring($pembuat, ['status' => KegiatanStatus::Published]),
            'hybrid' => $this->buatKegiatan($pembuat, [
                'metode_pelaksanaan' => 'hybrid',
                'platform_daring' => 'meet',
                'tautan_rapat' => 'https://meet.google.com/abc-defg-hij',
                'status' => KegiatanStatus::Published,
            ]),
            'offline_lama' => tap($this->buatKegiatan($pembuat, ['status' => KegiatanStatus::Published]), function (Kegiatan $k) {
                // Baris lama sebelum migrasi kolom daring: hanya nilai bawaan kolom, platform_daring kosong.
                DB::table('kegiatan')->where('id', $k->id)->update(['platform_daring' => null]);
            }),
        };
    }

    #[DataProvider('kasusKegiatan')]
    public function test_payload_daftar_memuat_semua_kunci_form_edit_tanpa_rahasia(string $kasus): void
    {
        $admin = $this->superAdmin();
        $kegiatan = $this->buatKasus($kasus, $admin);

        $baris = $this->barisDaftar($admin, $kegiatan);

        foreach (self::KUNCI_FORM_EDIT as $kunci) {
            $this->assertArrayHasKey($kunci, $baris, "Kunci '{$kunci}' dibutuhkan form edit tetapi tidak ada di payload ({$kasus}).");
        }
        foreach (self::KUNCI_BOOLEAN as $kunci) {
            $this->assertIsBool($baris[$kunci], "Kunci '{$kunci}' harus boolean ({$kasus}).");
        }

        foreach (['tautan_rapat', 'meeting_id', 'passcode_rapat'] as $rahasia) {
            $this->assertArrayNotHasKey($rahasia, $baris, "Nilai rahasia '{$rahasia}' tidak boleh dikirim ke daftar.");
        }
        $json = json_encode($baris);
        foreach ([self::TAUTAN, self::PASSCODE, self::MEETING_ID, 'meet.google.com/abc-defg-hij'] as $nilai) {
            $this->assertStringNotContainsString($nilai, $json);
        }
    }

    public function test_penanda_tersimpan_mengikuti_isi_kolom_rahasia(): void
    {
        $admin = $this->superAdmin();

        $daring = $this->barisDaftar($admin, $this->buatKegiatanDaring($admin));
        $this->assertTrue($daring['tautan_rapat_tersimpan']);
        $this->assertTrue($daring['meeting_id_tersimpan']);
        $this->assertTrue($daring['passcode_rapat_tersimpan']);

        $tanpaPasscode = $this->barisDaftar($admin, $this->buatKegiatanDaring($admin, [
            'nama_kegiatan' => 'Tanpa Passcode', 'passcode_rapat' => null, 'meeting_id' => null,
        ]));
        $this->assertTrue($tanpaPasscode['tautan_rapat_tersimpan']);
        $this->assertFalse($tanpaPasscode['meeting_id_tersimpan']);
        $this->assertFalse($tanpaPasscode['passcode_rapat_tersimpan']);

        $offline = $this->barisDaftar($admin, $this->buatKegiatan($admin, ['nama_kegiatan' => 'Offline']));
        $this->assertFalse($offline['tautan_rapat_tersimpan']);
    }

    public function test_menyimpan_edit_dengan_isian_rahasia_kosong_tidak_menghapus_data_tersimpan(): void
    {
        $admin = $this->superAdmin();
        $kegiatan = $this->buatKegiatanDaring($admin, ['status' => KegiatanStatus::Draft]);

        // Persis seperti form edit: rahasia dibiarkan kosong, pengaturan gabung ikut terkirim apa adanya.
        $this->actingAs($admin)->put(route('admin.kegiatan.update', $kegiatan->id), [
            'nama_kegiatan' => 'Webinar Daring (revisi)',
            'tautan_rapat' => '',
            'meeting_id' => '',
            'passcode_rapat' => '',
            'platform_daring' => 'zoom',
            'join_button_mode' => 'manual',
            'menit_buka_gabung' => 45,
            'batas_tutup_gabung' => '',
            'izin_tautan_presensi_akhir' => true,
            'wajib_tangkapan_layar' => true,
        ])->assertSessionHasNoErrors();

        $segar = $kegiatan->fresh();
        $this->assertSame('Webinar Daring (revisi)', $segar->nama_kegiatan);
        $this->assertSame(self::TAUTAN, $segar->tautan_rapat);
        $this->assertSame(self::MEETING_ID, $segar->meeting_id);
        $this->assertSame(self::PASSCODE, $segar->passcode_rapat);
        $this->assertSame('manual', $segar->join_button_mode);
        $this->assertSame(45, $segar->menit_buka_gabung);
        $this->assertTrue($segar->izin_tautan_presensi_akhir);
        $this->assertTrue($segar->wajib_tangkapan_layar);
    }

    public function test_mengisi_rahasia_baru_menggantikan_nilai_lama(): void
    {
        $admin = $this->superAdmin();
        $kegiatan = $this->buatKegiatanDaring($admin);

        $this->actingAs($admin)->put(route('admin.kegiatan.update', $kegiatan->id), [
            'tautan_rapat' => 'https://zoom.us/j/999000111',
            'passcode_rapat' => 'sandi-baru',
        ])->assertSessionHasNoErrors();

        $segar = $kegiatan->fresh();
        $this->assertSame('https://zoom.us/j/999000111', $segar->tautan_rapat);
        $this->assertSame('sandi-baru', $segar->passcode_rapat);
        $this->assertSame(self::MEETING_ID, $segar->meeting_id);
    }

    public function test_edit_dari_halaman_detail_membawa_kegiatan_yang_tidak_ada_di_halaman_pertama_daftar(): void
    {
        $admin = $this->superAdmin();
        $target = $this->buatKegiatanDaring($admin, ['nama_kegiatan' => 'Paling Lama']);
        for ($i = 1; $i <= 13; $i++) {
            $this->buatKegiatan($admin, ['nama_kegiatan' => "Kegiatan {$i}"]);
        }

        $this->assertNull(collect($this->propsDaftar($admin)['kegiatans']['data'])->firstWhere('id', $target->id));

        $props = $this->propsDaftar($admin, "?edit={$target->id}");
        $this->assertSame($target->id, $props['kegiatanEdit']['id']);
        foreach (self::KUNCI_FORM_EDIT as $kunci) {
            $this->assertArrayHasKey($kunci, $props['kegiatanEdit']);
        }
        $this->assertStringNotContainsString(self::TAUTAN, json_encode($props['kegiatanEdit']));

        $this->assertNull($this->propsDaftar($admin, '?edit=99999')['kegiatanEdit']);
        $this->assertNull($this->propsDaftar($admin)['kegiatanEdit']);
    }

    public function test_matriks_tombol_pembuat_pada_daftar_dan_detail(): void
    {
        $pembuat = $this->superAdmin('Ahmad Pembuat');
        $this->superAdmin('Budi Penyetuju');

        $draft = $this->buatKegiatan($pembuat, ['status' => KegiatanStatus::Draft]);
        $diajukan = $this->buatKegiatan($pembuat, ['nama_kegiatan' => 'Diajukan', 'status' => KegiatanStatus::Diajukan]);

        $baris = $this->barisDaftar($pembuat, $draft);
        $this->assertEquals(
            ['can_ajukan' => true, 'can_tarik_draft' => false, 'can_setujui' => false, 'can_tolak' => false, 'keputusan_saya' => null, 'alasan_nonaktif' => null, 'creator_name' => 'Ahmad Pembuat'],
            collect($baris)->only(['can_ajukan', 'can_tarik_draft', 'can_setujui', 'can_tolak', 'keputusan_saya', 'alasan_nonaktif', 'creator_name'])->all(),
        );

        $baris = $this->barisDaftar($pembuat, $diajukan);
        $this->assertFalse($baris['can_ajukan']);
        $this->assertTrue($baris['can_tarik_draft']);
        $this->assertFalse($baris['can_setujui']);
        $this->assertFalse($baris['can_tolak']);

        $approval = $this->actingAs($pembuat)->get(route('admin.kegiatan.show', $draft->id))->original->getData()['page']['props']['approval'];
        $this->assertTrue($approval['can_ajukan']);
        $this->assertSame('Ahmad Pembuat', $approval['creator_name']);
    }

    public function test_matriks_tombol_penyetuju_menurut_status_dan_keputusan(): void
    {
        $pembuat = $this->superAdmin('Ahmad Pembuat');
        $penyetuju = $this->superAdmin('Budi Penyetuju');
        $lain = $this->superAdmin('Citra Penyetuju');

        $draft = $this->buatKegiatan($pembuat, ['status' => KegiatanStatus::Draft]);
        $diajukan = $this->buatKegiatan($pembuat, ['nama_kegiatan' => 'Diajukan', 'status' => KegiatanStatus::Diajukan]);
        $terbit = $this->buatKegiatan($pembuat, ['nama_kegiatan' => 'Terbit', 'status' => KegiatanStatus::Published]);

        // Draft: Setujui tampil nonaktif dengan penjelasan, bukan aksi.
        $baris = $this->barisDaftar($penyetuju, $draft);
        $this->assertFalse($baris['can_setujui']);
        $this->assertFalse($baris['can_tolak']);
        $this->assertFalse($baris['can_ajukan']);
        $this->assertSame('Menunggu diajukan oleh Ahmad Pembuat', $baris['alasan_nonaktif']);

        // Diajukan, belum memutuskan: Setujui dan Tolak aktif.
        $baris = $this->barisDaftar($penyetuju, $diajukan);
        $this->assertTrue($baris['can_setujui']);
        $this->assertTrue($baris['can_tolak']);
        $this->assertNull($baris['keputusan_saya']);
        $this->assertNull($baris['alasan_nonaktif']);

        // Terbit: tidak ada tombol maupun alasan.
        $baris = $this->barisDaftar($penyetuju, $terbit);
        $this->assertFalse($baris['can_setujui'] || $baris['can_tolak'] || $baris['can_ajukan'] || $baris['can_tarik_draft']);
        $this->assertNull($baris['alasan_nonaktif']);

        // Setelah menyetujui: lencana, tanpa tombol. Penyetuju lain tetap punya tombol.
        $this->actingAs($penyetuju)->post(route('admin.kegiatan.setujui', $diajukan->id))->assertSessionHas('success');
        $baris = $this->barisDaftar($penyetuju, $diajukan);
        $this->assertSame('setuju', $baris['keputusan_saya']);
        $this->assertFalse($baris['can_setujui']);
        $this->assertFalse($baris['can_tolak']);
        $this->assertTrue($this->barisDaftar($lain, $diajukan)['can_setujui']);

        $approval = $this->actingAs($penyetuju)->get(route('admin.kegiatan.show', $diajukan->id))->original->getData()['page']['props']['approval'];
        $this->assertSame('setuju', $approval['keputusan_saya']);
        $this->assertFalse($approval['can_setujui']);
        $this->assertFalse($approval['can_tolak']);
    }

    public function test_tidak_ada_tombol_untuk_yang_tidak_berhak(): void
    {
        $pembuat = $this->superAdmin();
        $this->superAdmin();
        $diajukan = $this->buatKegiatan($pembuat, ['status' => KegiatanStatus::Diajukan]);

        foreach ([User::factory()->mahasiswaKipk()->create(), User::factory()->userUmum()->create(), User::factory()->verifikator()->create(), User::factory()->penilai()->create()] as $user) {
            $aksi = AksiPersetujuan::untuk($diajukan->load('persetujuan'), $user);
            foreach (['can_ajukan', 'can_tarik_draft', 'can_setujui', 'can_tolak'] as $kunci) {
                $this->assertFalse($aksi[$kunci], "{$user->role->value} tidak boleh punya {$kunci}.");
            }
            $this->assertNull($aksi['keputusan_saya']);
            $this->assertNull($aksi['alasan_nonaktif']);

            $this->assertAksesAdminDitolak($user, $this->actingAs($user)->get(route('admin.kegiatan')));
            $this->assertAksesAdminDitolak($user, $this->actingAs($user)->get(route('admin.kegiatan.show', $diajukan->id)));
        }
    }

    public function test_server_menolak_setujui_dan_tolak_dari_yang_tidak_berhak(): void
    {
        $pembuat = $this->superAdmin();
        $penyetuju = $this->superAdmin();
        $diajukan = $this->buatKegiatan($pembuat, ['status' => KegiatanStatus::Diajukan]);

        // Non-super_admin: 403.
        foreach ([User::factory()->mahasiswaKipk()->create(), User::factory()->verifikator()->create(), User::factory()->penilai()->create()] as $user) {
            $this->assertAksesAdminDitolak($user, $this->actingAs($user)->post(route('admin.kegiatan.setujui', $diajukan->id)));
            $this->assertAksesAdminDitolak($user, $this->actingAs($user)->post(route('admin.kegiatan.tolak', $diajukan->id), ['catatan' => 'Tidak sesuai ketentuan']));
        }

        // Pembuat sendiri: 403.
        $this->actingAs($pembuat)->post(route('admin.kegiatan.setujui', $diajukan->id))->assertForbidden();
        $this->actingAs($pembuat)->post(route('admin.kegiatan.tolak', $diajukan->id), ['catatan' => 'Tidak sesuai ketentuan'])->assertForbidden();

        $this->assertSame(KegiatanStatus::Diajukan, $diajukan->fresh()->status);
        $this->assertSame(0, KegiatanPersetujuan::where('kegiatan_id', $diajukan->id)->count());

        // Tolak wajib catatan.
        $this->actingAs($penyetuju)->post(route('admin.kegiatan.tolak', $diajukan->id), ['catatan' => ''])->assertSessionHasErrors('catatan');
        $this->assertSame(KegiatanStatus::Diajukan, $diajukan->fresh()->status);
    }

    public function test_server_menolak_setujui_dan_tolak_bila_status_bukan_diajukan(): void
    {
        $pembuat = $this->superAdmin();
        $penyetuju = $this->superAdmin();

        foreach ([KegiatanStatus::Draft, KegiatanStatus::Published] as $status) {
            $kegiatan = $this->buatKegiatan($pembuat, ['nama_kegiatan' => "Status {$status->value}", 'status' => $status]);

            $this->actingAs($penyetuju)->post(route('admin.kegiatan.setujui', $kegiatan->id))->assertSessionHas('error');
            $this->actingAs($penyetuju)->post(route('admin.kegiatan.tolak', $kegiatan->id), ['catatan' => 'Catatan penolakan'])->assertSessionHas('error');

            $this->assertSame($status, $kegiatan->fresh()->status);
            $this->assertSame(0, KegiatanPersetujuan::where('kegiatan_id', $kegiatan->id)->count());
        }
    }

    public function test_keputusan_ganda_tidak_menambah_suara_dan_tidak_mengubah_hasil(): void
    {
        $pembuat = $this->superAdmin();
        $penyetuju = $this->superAdmin();
        $this->superAdmin();
        $diajukan = $this->buatKegiatan($pembuat, ['status' => KegiatanStatus::Diajukan]);

        $this->actingAs($penyetuju)->post(route('admin.kegiatan.setujui', $diajukan->id));
        $this->actingAs($penyetuju)->post(route('admin.kegiatan.setujui', $diajukan->id));

        $this->assertSame(1, KegiatanPersetujuan::where('kegiatan_id', $diajukan->id)->count());
        $this->assertSame(KegiatanStatus::Diajukan, $diajukan->fresh()->status, 'Dua klik dari penyetuju yang sama tidak boleh menerbitkan kegiatan.');
    }
}
