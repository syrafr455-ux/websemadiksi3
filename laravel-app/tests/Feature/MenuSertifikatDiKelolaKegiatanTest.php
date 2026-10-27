<?php

namespace Tests\Feature;

use App\Enums\KegiatanStatus;
use App\Enums\UserRole;
use App\Models\Kegiatan;
use App\Models\KegiatanPenandatangan;
use App\Models\Penandatangan;
use App\Models\User;
use App\Support\MenuAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pengelolaan sertifikat dipindah dari sidebar ke Kelola Kegiatan (/admin/kegiatan).
 */
class MenuSertifikatDiKelolaKegiatanTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Kegiatan $draft;

    private Kegiatan $terbit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->superAdmin()->create();
        $dasar = [
            'created_by' => $this->admin->id, 'kategori' => 'seminar', 'metode_pelaksanaan' => 'offline',
            'sasaran_peserta' => 'umum', 'tanggal_mulai' => '2026-11-20 02:00:00', 'tanggal_selesai' => '2026-11-20 05:00:00',
            'lokasi_type' => 'unusa_campus', 'lokasi_nama' => 'Auditorium', 'kuota' => 50, 'biaya' => 0,
            'file_panduan_pdf' => 'kegiatan/panduan.pdf',
        ];
        $this->draft = Kegiatan::create($dasar + ['nama_kegiatan' => 'Kegiatan Draft', 'status' => KegiatanStatus::Draft]);
        $this->terbit = Kegiatan::create($dasar + ['nama_kegiatan' => 'Kegiatan Terbit', 'status' => KegiatanStatus::Published]);
    }

    public function test_tombol_sertifikat_ada_di_setiap_baris_untuk_semua_status_dan_super_admin(): void
    {
        $props = $this->actingAs($this->admin)->get(route('admin.kegiatan'))->assertOk()->original->getData()['page']['props'];

        $this->assertTrue($props['izinSertifikat']);
        $ids = collect($props['kegiatans']['data'])->pluck('id')->all();
        $this->assertContains($this->draft->id, $ids);
        $this->assertContains($this->terbit->id, $ids);
    }

    public function test_sidebar_tidak_lagi_memuat_sertifikat_digital(): void
    {
        $menu = collect(MenuAdmin::untuk($this->admin))->pluck('label')->all();
        $this->assertNotContains('Sertifikat Digital', $menu);
        $this->assertContains('Kelola Kegiatan', $menu);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->assertDontSee('Sertifikat Digital');
    }

    public function test_rute_lama_mengalihkan_302_ke_halaman_yang_sesuai(): void
    {
        $this->actingAs($this->admin);

        $this->get('/admin/sertifikat')->assertStatus(302)->assertRedirect(route('admin.kegiatan'));
        $this->get('/admin/sertifikat?tab=terbitkan')->assertStatus(302)->assertRedirect(route('admin.kegiatan'));
        $this->get('/admin/sertifikat?tab=terbitkan&kegiatan_id='.$this->terbit->id.'&jenis=juri')
            ->assertStatus(302)
            ->assertRedirect(route('admin.certificates', ['tab' => 'terbitkan', 'kegiatan_id' => $this->terbit->id, 'jenis' => 'juri']));
        $this->get('/admin/sertifikat?tab=arsip&q=abc')->assertStatus(302)->assertRedirect(route('admin.certificates', ['tab' => 'arsip', 'q' => 'abc']));
        $this->get('/admin/sertifikat?tab=penandatangan')->assertStatus(302)->assertRedirect(route('admin.certificates', ['tab' => 'penandatangan']));
        $this->get('/admin/sertifikat?tab=pengaturan')->assertStatus(302)->assertRedirect(route('admin.certificates', ['tab' => 'pengaturan']));
    }

    public function test_halaman_per_kegiatan_memuat_header_ringkasan_dan_tautan_halaman_terbitkan_dan_arsip(): void
    {
        $ttd = Penandatangan::create(['nama' => 'Dr. Rektor', 'jabatan' => 'Rektor', 'institusi' => 'UNUSA', 'is_active' => true]);
        KegiatanPenandatangan::create(['kegiatan_id' => $this->draft->id, 'penandatangan_id' => $ttd->id, 'jenis_sertifikat' => 'semua', 'urutan' => 1]);

        foreach ([$this->draft, $this->terbit] as $kegiatan) {
            $res = $this->actingAs($this->admin)->get(route('admin.kegiatan.sertifikat', $kegiatan->id))->assertOk();
            $halaman = $this->halamanInertia($res);

            $this->assertSame('Admin/Kegiatan/Sertifikat', $halaman['component']);
            $props = $halaman['props'];
            $this->assertSame($kegiatan->nama_kegiatan, $props['kegiatan']['nama_kegiatan']);
            $this->assertArrayHasKey('layak', $props['ringkasan']);
            $this->assertSame('landscape', $props['templat']['orientasi']);
            $this->assertArrayHasKey('qr', $props['templat']['layout']);
            $this->assertContains('DejaVu Sans', $props['templat']['aturan']['font']);
            $this->assertArrayNotHasKey('kegiatanLain', $props, 'Fitur salin templat dihapus dari editor yang disederhanakan.');
            $this->assertArrayHasKey('terbit', $props['ringkasan']);
            $this->assertSame(route('admin.certificates', ['tab' => 'terbitkan', 'kegiatan_id' => $kegiatan->id]), $props['tautan']['terbitkan']);
            $this->assertSame(route('admin.certificates', ['tab' => 'arsip', 'kegiatan_id' => $kegiatan->id]), $props['tautan']['arsip']);
            $this->assertSame(route('admin.kegiatan'), $props['tautan']['kembali']);
        }

        $props = $this->halamanInertia($this->actingAs($this->admin)->get(route('admin.kegiatan.sertifikat', $this->draft->id)))['props'];
        $this->assertCount(1, $props['penandatangan']['ditugaskan']);
        // Migrasi T2a sudah menyemai penandatangan bawaan, jadi cukup memastikan yang baru ikut ditawarkan.
        $this->assertContains($ttd->id, collect($props['penandatangan']['master'])->pluck('id')->all());
    }

    public function test_halaman_terbitkan_dan_arsip_menampilkan_tautan_kembali_ke_halaman_kegiatan(): void
    {
        $this->actingAs($this->admin)->get(route('admin.certificates', ['tab' => 'arsip', 'kegiatan_id' => $this->terbit->id]))
            ->assertOk()->assertSee(route('admin.kegiatan.sertifikat', $this->terbit->id), false);
        $this->actingAs($this->admin)->get(route('admin.certificates', ['tab' => 'arsip']))
            ->assertOk()->assertSee(route('admin.kegiatan'), false);
    }

    public function test_peran_lain_ditolak_dan_peserta_dialihkan(): void
    {
        $urls = [route('admin.kegiatan.sertifikat', $this->terbit->id), route('admin.certificates'), '/admin/sertifikat'];

        foreach ([UserRole::VerifikatorBerkas, UserRole::PenilaiKarya] as $peran) {
            $staf = User::factory()->create(['role' => $peran]);
            foreach ($urls as $url) {
                $kode = $this->actingAs($staf)->get($url)->getStatusCode();
                $this->assertContains($kode, [302, 403], "{$peran->value} {$url}");
                $this->assertNotSame(200, $kode);
            }
        }
        $peserta = User::factory()->mahasiswaKipk()->create();
        foreach ($urls as $url) {
            $this->actingAs($peserta)->get($url)->assertRedirect();
        }
    }
}
