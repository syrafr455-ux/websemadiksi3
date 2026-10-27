<?php

namespace Tests\Feature;

use App\Enums\JenisSertifikat;
use App\Enums\KegiatanStatus;
use App\Enums\PeranSertifikat;
use App\Enums\StatusSertifikat;
use App\Enums\UserRole;
use App\Models\Kegiatan;
use App\Models\Sertifikat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Semua halaman admin memakai layout bersama (acuan: AdminLayout.jsx). Layout Blade dan AdminLayout.jsx sama-sama
 * menulis data-tata-letak="admin" pada elemen akar; halaman yang memakai header lama gagal di sini.
 */
class TataLetakAdminSeragamTest extends TestCase
{
    use RefreshDatabase;

    private const PENANDA = 'data-tata-letak="admin"';

    /** Kelas teks yang kontrasnya di bawah 4,5:1 pada kartu gelap (slate-900/950) atau berasal dari tema terang. */
    private const KELAS_TEKS_REDUP = [
        'text-slate-500', 'text-slate-600', 'text-slate-700', 'text-slate-800', 'text-slate-900',
        'text-emerald-600', 'text-emerald-700', 'text-emerald-800', 'text-emerald-900',
        'text-rose-500', 'text-rose-600', 'text-rose-700', 'text-rose-800', 'text-rose-900',
        'text-amber-600', 'text-amber-700', 'text-amber-800', 'text-amber-900',
        'text-sky-600', 'text-sky-700', 'text-blue-600', 'text-blue-700', 'text-indigo-600', 'text-indigo-700',
    ];

    private User $admin;

    private Kegiatan $kegiatan;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->admin = User::factory()->superAdmin()->create();
        $this->kegiatan = Kegiatan::create([
            'created_by' => $this->admin->id, 'nama_kegiatan' => 'Lomba Essay', 'kategori' => 'lomba', 'metode_pelaksanaan' => 'offline',
            'sasaran_peserta' => 'umum', 'tanggal_mulai' => now()->subDays(3), 'tanggal_selesai' => now()->subDays(2), 'lokasi_type' => 'unusa_campus',
            'lokasi_nama' => 'Auditorium', 'kuota' => 10, 'biaya' => 0, 'file_panduan_pdf' => 'kegiatan/panduan.pdf', 'status' => KegiatanStatus::Published,
        ]);
        Sertifikat::create([
            'kegiatan_id' => $this->kegiatan->id, 'nomor_sertifikat' => 'CERT-SERAGAM-0001', 'nama_penerima' => 'Tamu Undangan', 'asal_institusi' => 'ITB',
            'penerima_key' => Sertifikat::hitungPenerimaKey(null, 'Tamu Undangan', 'ITB'), 'jenis' => JenisSertifikat::Pembicara,
            'peran_sertifikat' => PeranSertifikat::Peserta, 'status' => StatusSertifikat::Aktif, 'issued_at' => now(),
        ]);
    }

    /** @return array<string, string> nama => url halaman Blade admin */
    private function halamanBlade(): array
    {
        $k = $this->kegiatan->id;

        return [
            'dashboard' => route('admin.dashboard'),
            'presensi dan kehadiran' => route('admin.attendances', ['kegiatan_id' => $k]),
            'pemindai tiket' => route('admin.kegiatan.scanner', $k),
            'verifikasi berkas kip-k' => route('admin.berkas'),
            'data mahasiswa unusa' => route('admin.mahasiswa-unusa'),
            'daftar penerima kip-k' => route('admin.penerima-kipk'),
            'formulir masuk' => route('admin.submissions'),
            'sertifikat terbitkan' => route('admin.certificates', ['tab' => 'terbitkan', 'kegiatan_id' => $k]),
            'sertifikat terbitkan juri' => route('admin.certificates', ['tab' => 'terbitkan', 'kegiatan_id' => $k, 'jenis' => 'juri']),
            'sertifikat arsip' => route('admin.certificates', ['tab' => 'arsip']),
            'sertifikat arsip per kegiatan' => route('admin.certificates', ['tab' => 'arsip', 'kegiatan_id' => $k]),
            'sertifikat penandatangan' => route('admin.certificates', ['tab' => 'penandatangan']),
            'sertifikat pengaturan verifikasi' => route('admin.certificates', ['tab' => 'pengaturan']),
        ];
    }

    public function test_semua_halaman_blade_admin_memakai_layout_bersama_tanpa_header_lama(): void
    {
        foreach ($this->halamanBlade() as $nama => $url) {
            $html = $this->actingAs($this->admin)->get($url)->assertOk()->getContent();

            $this->assertStringContainsString(self::PENANDA, $html, "{$nama}: penanda tata letak admin tidak ada");
            $this->assertStringNotContainsString('Pusat Sertifikat Digital', $html, "{$nama}: header lama masih ada");
            $this->assertStringNotContainsString('Panel Pengelola', $html, "{$nama}: nama panel lama masih ada");
            $this->assertStringNotContainsString('admin_panel_settings', $html, "{$nama}: ikon logo lama masih ada");
            $this->assertStringContainsString('Panel Admin', $html, "{$nama}: judul panel acuan tidak ada");
            $this->assertMatchesRegularExpression('/>\s*SD\s*</', $html, "{$nama}: logo SD acuan tidak ada");
            $this->assertStringContainsString('bg-emerald-700', $html, "{$nama}: warna aktif acuan (emerald) tidak dipakai");
            $this->assertDoesNotMatchRegularExpression('/data-menu-admin="[^"]*"[^>]*bg-amber-600/', $html, "{$nama}: warna menu aktif lama (amber) masih dipakai");
        }
    }

    public function test_halaman_inertia_admin_memakai_adminlayout_dan_penandanya(): void
    {
        $layout = file_get_contents(resource_path('js/Layouts/AdminLayout.jsx'));
        $this->assertStringContainsString(self::PENANDA, $layout, 'AdminLayout.jsx harus menulis penanda yang sama dengan layout Blade.');

        $halaman = [
            'Admin/Kegiatan/Index' => route('admin.kegiatan'),
            'Admin/Kegiatan/Show' => route('admin.kegiatan.show', $this->kegiatan->id),
            'Admin/Kegiatan/Sertifikat' => route('admin.kegiatan.sertifikat', $this->kegiatan->id),
            'Admin/Pengguna/Index' => route('admin.users'),
            'Admin/Penilaian/Index' => route('admin.penilaian'),
        ];
        foreach ($halaman as $komponen => $url) {
            $res = $this->actingAs($this->admin)->get($url)->assertOk();
            $this->assertSame($komponen, $this->halamanInertia($res)['component'], $url);
            $sumber = file_get_contents(resource_path("js/Pages/{$komponen}.jsx"));
            $this->assertStringContainsString("from '@/Layouts/AdminLayout'", $sumber, "{$komponen} harus memakai AdminLayout");
            $this->assertStringContainsString('<AdminLayout', $sumber, "{$komponen} harus merender AdminLayout");
        }
    }

    public function test_setiap_halaman_inertia_di_folder_admin_memakai_adminlayout(): void
    {
        $berkas = array_merge(glob(resource_path('js/Pages/Admin/*.jsx')), glob(resource_path('js/Pages/Admin/*/*.jsx')));
        $this->assertNotEmpty($berkas);
        foreach ($berkas as $f) {
            $isi = file_get_contents($f);
            if (preg_match("/^import \w+ from '\.\//", $isi) === 1) {
                continue; // Berkas pengalih (mis. Pengguna.jsx) hanya mengekspor ulang halaman di subfolder.
            }
            $this->assertStringContainsString("from '@/Layouts/AdminLayout'", $isi, $f);
        }
    }

    public function test_halaman_sertifikat_blade_memakai_kartu_gelap_dan_teks_terbaca(): void
    {
        foreach (['terbitkan', 'arsip', 'penandatangan', 'pengaturan'] as $tab) {
            $html = $this->actingAs($this->admin)->get(route('admin.certificates', ['tab' => $tab, 'kegiatan_id' => $this->kegiatan->id]))->assertOk()->getContent();

            $this->assertStringNotContainsString('bg-white', $html, "{$tab}: kartu putih di atas latar gelap");
            foreach (['text-[9px]', 'text-[10px]', 'text-[11px]'] as $kecil) {
                $this->assertStringNotContainsString($kecil, $html, "{$tab}: teks di bawah 12px ({$kecil})");
            }
            foreach (self::KELAS_TEKS_REDUP as $kelas) {
                $this->assertDoesNotMatchRegularExpression('/[\s"\']'.preg_quote($kelas, '/').'[\s"\']/', $html, "{$tab}: kelas {$kelas} berkontras rendah pada latar gelap");
            }
        }
    }

    public function test_halaman_sertifikat_terfilter_menampilkan_konteks_kegiatan_dan_kembali_ke_halaman_kegiatan(): void
    {
        $kembali = route('admin.kegiatan.sertifikat', $this->kegiatan->id);
        foreach (['terbitkan', 'arsip'] as $tab) {
            $res = $this->actingAs($this->admin)->get(route('admin.certificates', ['tab' => $tab, 'kegiatan_id' => $this->kegiatan->id]))->assertOk();

            $res->assertSee('data-konteks-kegiatan', false);
            $res->assertSee('Lomba Essay');
            $res->assertSee(KegiatanStatus::Published->label());
            $res->assertSee('href="'.$kembali.'"', false);
            $res->assertSee('Kembali ke sertifikat kegiatan');
            // Tab Terbitkan dan Arsip mempertahankan kegiatan yang sedang dibuka.
            $res->assertSee('href="'.e(route('admin.certificates', ['tab' => 'terbitkan', 'kegiatan_id' => $this->kegiatan->id])).'"', false);
            $res->assertSee('href="'.e(route('admin.certificates', ['tab' => 'arsip', 'kegiatan_id' => $this->kegiatan->id])).'"', false);
        }
    }

    public function test_tab_sertifikat_hanya_untuk_super_admin(): void
    {
        foreach (['terbitkan', 'arsip', 'penandatangan', 'pengaturan'] as $tab) {
            $url = route('admin.certificates', ['tab' => $tab, 'kegiatan_id' => $this->kegiatan->id]);
            $this->actingAs($this->admin)->get($url)->assertOk();

            foreach ([UserRole::VerifikatorBerkas, UserRole::PenilaiKarya] as $role) {
                $staf = User::factory()->role($role)->create();
                $this->actingAs($staf)->getJson($url)->assertForbidden();
                $this->actingAs($staf)->get($url)->assertRedirect(); // peramban dialihkan ke beranda role-nya
            }
            foreach ([UserRole::MahasiswaKipk, UserRole::UserUmum] as $role) {
                $this->actingAs(User::factory()->role($role)->create())->get($url)->assertRedirect(route('admin.login'));
            }
        }
        $this->app['auth']->forgetGuards();
        $this->get(route('admin.certificates', ['tab' => 'arsip']))->assertRedirect(route('login'));
    }
}
