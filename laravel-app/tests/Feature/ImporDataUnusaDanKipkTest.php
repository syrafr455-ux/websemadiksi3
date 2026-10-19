<?php

namespace Tests\Feature;

use App\Contracts\KipRecipientProvider;
use App\Contracts\UnusaAcademicProvider;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\MasterMahasiswaUnusa;
use App\Models\PenerimaKipk;
use App\Models\User;
use App\Services\Academic\ApiUnusaAcademicProvider;
use App\Services\Kip\ApiKipRecipientProvider;
use App\Support\NomorKip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImporDataUnusaDanKipkTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->admin = User::factory()->superAdmin()->create();
    }

    protected function csv(string $isi, string $nama = 'impor.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nama, $isi);
    }

    protected function imporMaster(string $isi)
    {
        $this->actingAs($this->admin)->post(route('admin.mahasiswa-unusa.unggah'), ['file' => $this->csv($isi)])
            ->assertRedirect(route('admin.mahasiswa-unusa.pratinjau'));

        return $this->actingAs($this->admin)->get(route('admin.mahasiswa-unusa.pratinjau'))->assertOk();
    }

    protected function imporPenerima(string $isi)
    {
        $this->actingAs($this->admin)->post(route('admin.penerima-kipk.unggah'), ['file' => $this->csv($isi, 'penerima.csv')])
            ->assertRedirect(route('admin.penerima-kipk.pratinjau'));

        return $this->actingAs($this->admin)->get(route('admin.penerima-kipk.pratinjau'))->assertOk();
    }

    public function test_master_import_validates_each_row_and_upserts_by_nim(): void
    {
        $isi = "nim,nama_lengkap,email_kampus,program_studi,angkatan,status_aktif\n"
            ."2241010001,Ahmad Fauzan,ahmad@student.unusa.ac.id,FEBTD-S1-SI,2024,aktif\n"
            ."2241010002,Siti Aminah,siti@student.unusa.ac.id,S1 Gizi,2023,\n"
            ."2241010003,Email Salah,bukan-email,S1 Gizi,2023,aktif\n"
            ."2241010004,Prodi Asing,asing@student.unusa.ac.id,S1 Teknik Nuklir,2023,aktif\n"
            ."2241010005,Kembar Satu,k1@student.unusa.ac.id,S1 Gizi,2023,aktif\n"
            ."2241010005,Kembar Dua,k2@student.unusa.ac.id,S1 Gizi,2023,aktif\n";

        $pratinjau = $this->imporMaster($isi);
        $periksa = $pratinjau->viewData('periksa');
        $this->assertSame(['total' => 6, 'baru' => 2, 'perbarui' => 0, 'galat' => 4], $periksa['ringkasan']);
        $pesan = collect($periksa['baris'])->keyBy('baris')->map->pesan;
        $this->assertContains('Email kampus tidak valid.', $pesan[4]);
        $this->assertStringContainsString('Program studi tidak dikenal', implode(' ', $pesan[5]));
        $this->assertStringContainsString('NIM ganda di file (juga di baris 7)', implode(' ', $pesan[6]));

        $this->actingAs($this->admin)->post(route('admin.mahasiswa-unusa.impor'))->assertRedirect(route('admin.mahasiswa-unusa'))->assertSessionHas('success');
        $this->assertSame(2, MasterMahasiswaUnusa::count());
        $this->assertSame('aktif', MasterMahasiswaUnusa::where('nim', '2241010002')->value('status_aktif'));

        // Impor ulang (pemisah ";" + BOM Excel): upsert tanpa duplikasi.
        $ulang = "\xEF\xBB\xBFnim;nama_lengkap;email_kampus;program_studi;angkatan;status_aktif\n2241010001;Ahmad Fauzan Updated;ahmad@student.unusa.ac.id;FEBTD-S1-SI;2024;cuti\n";
        $this->assertSame(['total' => 1, 'baru' => 0, 'perbarui' => 1, 'galat' => 0], $this->imporMaster($ulang)->viewData('periksa')['ringkasan']);
        $this->actingAs($this->admin)->post(route('admin.mahasiswa-unusa.impor'));
        $this->assertSame(2, MasterMahasiswaUnusa::count());
        $this->assertSame('Ahmad Fauzan Updated', MasterMahasiswaUnusa::where('nim', '2241010001')->value('nama_lengkap'));
        $this->assertSame('cuti', MasterMahasiswaUnusa::where('nim', '2241010001')->value('status_aktif'));

        $this->assertSame(2, AuditLog::where('action', 'impor_master_mahasiswa')->count());
        Storage::disk('local')->assertDirectoryEmpty('impor');
    }

    public function test_missing_required_columns_are_reported(): void
    {
        $periksa = $this->imporMaster("nim,nama\n123,Budi\n")->viewData('periksa');

        $this->assertSame(['email_kampus', 'program_studi'], $periksa['kolom_hilang']);
        $this->assertSame([], $periksa['baris']);
    }

    public function test_penerima_import_normalizes_numbers_encrypts_and_upserts_by_hash(): void
    {
        $isi = "nomor_pendaftaran,nama_lengkap,nim,program_studi,angkatan,status_penetapan\n"
            ."1234.567.89012.1766.761,Ahmad Fauzan,2241010001,S1 Sistem Informasi,2024,Ditetapkan\n"
            ."9876 543 21098 7654 321,Siti Aminah,,S1 Gizi,2023,Ditetapkan\n"
            ."123456789012 1766761,Kembar Nomor,,S1 Gizi,2023,Ditetapkan\n"
            .",Tanpa Nomor,,,,\n"
            ."12AB,Nomor Pendek,,,,\n";

        $pratinjau = $this->imporPenerima($isi);
        $periksa = $pratinjau->viewData('periksa');
        $this->assertSame(['total' => 5, 'baru' => 1, 'perbarui' => 0, 'galat' => 4], $periksa['ringkasan'], 'Baris 2 & 4 bernomor sama (dengan/tanpa titik) sehingga keduanya galat.');
        $pratinjau->assertSee('****.***.*****.1766.761');
        $pratinjau->assertDontSee('1234.567.89012.1766.761')->assertDontSee('1234567890121766761');
        $pesan = collect($periksa['baris'])->keyBy('baris')->map->pesan;
        $this->assertStringContainsString('Nomor pendaftaran ganda di file (juga di baris 4)', implode(' ', $pesan[2]));
        $this->assertContains('Nomor pendaftaran kosong.', $pesan[5]);
        $this->assertStringContainsString('tidak valid', implode(' ', $pesan[6]));

        $this->actingAs($this->admin)->post(route('admin.penerima-kipk.impor'))->assertSessionHas('success');
        $this->assertSame(1, PenerimaKipk::count());

        // Tersimpan terenkripsi; dicari lewat HMAC dari nomor ternormalisasi.
        $mentah = DB::table('daftar_penerima_kipk')->first();
        $this->assertStringNotContainsString('98765432109876543', $mentah->nomor_pendaftaran_kip);
        $this->assertSame(hash_hmac('sha256', '9876543210987654321', config('app.key')), $mentah->nomor_kip_hash);
        $this->assertSame('9876543210987654321', PenerimaKipk::first()->nomor_pendaftaran_kip);

        // Impor ulang dengan format berbeda (bertitik) = baris yang sama (upsert), NIM kosong tidak menghapus NIM.
        PenerimaKipk::first()->update(['nim' => '2241020002']);
        $ulang = "nomor_pendaftaran,nama_lengkap,nim\n9876.543.21098.7654.321,Siti Aminah Putri,\n";
        $this->assertSame('perbarui', $this->imporPenerima($ulang)->viewData('periksa')['baris'][0]['status']);
        $this->actingAs($this->admin)->post(route('admin.penerima-kipk.impor'));
        $this->assertSame(1, PenerimaKipk::count());
        $this->assertSame('Siti Aminah Putri', PenerimaKipk::first()->nama_lengkap);
        $this->assertSame('2241020002', PenerimaKipk::first()->nim);

        // Audit tercatat tanpa nomor.
        $audit = AuditLog::where('action', 'impor_penerima_kipk')->get();
        $this->assertCount(2, $audit);
        $this->assertStringNotContainsString('98765432109876543', json_encode($audit->toArray()));
        Storage::disk('local')->assertDirectoryEmpty('impor');
    }

    public function test_uploaded_file_is_stored_encrypted_while_previewing(): void
    {
        $this->actingAs($this->admin)->post(route('admin.penerima-kipk.unggah'), [
            'file' => $this->csv("nomor_pendaftaran,nama_lengkap\n1234.567.89012.1766.761,Ahmad\n"),
        ]);

        $file = Storage::disk('local')->files('impor');
        $this->assertCount(1, $file);
        $this->assertStringNotContainsString('1234.567.89012', Storage::disk('local')->get($file[0]));

        $this->actingAs($this->admin)->post(route('admin.penerima-kipk.batal'));
        Storage::disk('local')->assertDirectoryEmpty('impor');
    }

    public function test_full_number_never_appears_on_index_page(): void
    {
        $penerima = (new PenerimaKipk(['nama_lengkap' => 'Ahmad']))->aturNomor('1234.567.89012.1766.761');
        $penerima->save();

        $this->actingAs($this->admin)->get(route('admin.penerima-kipk'))->assertOk()
            ->assertSee('****.***.*****.1766.761')
            ->assertDontSee('1234567890121766761')
            ->assertDontSee('1234.567.89012.1766.761');

        $this->assertArrayNotHasKey('nomor_pendaftaran_kip', $penerima->fresh()->toArray());
        $this->assertArrayNotHasKey('nomor_kip_hash', $penerima->fresh()->toArray());
    }

    public function test_only_super_admin_can_import_and_download_templates(): void
    {
        $this->actingAs($this->admin)->get(route('admin.penerima-kipk.template'))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->actingAs($this->admin)->get(route('admin.mahasiswa-unusa.template'))->assertOk();

        foreach ([UserRole::VerifikatorBerkas, UserRole::PenilaiKarya, UserRole::MahasiswaKipk, UserRole::UserUmum] as $role) {
            $user = User::factory()->role($role)->create();
            foreach (['admin.mahasiswa-unusa', 'admin.penerima-kipk', 'admin.penerima-kipk.template'] as $route) {
                $this->assertContains($this->actingAs($user)->get(route($route))->status(), [302, 403], "{$role->value} -> {$route}");
            }
            $this->actingAs($user)->post(route('admin.penerima-kipk.unggah'), ['file' => $this->csv("nomor_pendaftaran,nama_lengkap\n1234567890,Budi\n")]);
        }
        Storage::disk('local')->assertDirectoryEmpty('impor');
    }

    public function test_nomor_kip_helpers(): void
    {
        $this->assertSame('1234567890121766761', NomorKip::normalisasi(' 1234.567.89012 1766-761 '));
        $this->assertSame(NomorKip::hash('1234.567.89012.1766.761'), NomorKip::hash('1234567890121766761'));
        $this->assertSame('****.***.*****.1766.761', NomorKip::samarkan('1234567890121766761'));
        // Nomor non-19-digit: maksimal 7 digit terakhir terlihat, minimal 4 digit tetap tersamar.
        $this->assertSame('****567890', NomorKip::samarkan('1234567890'));
        $this->assertSame('-', NomorKip::samarkan(null));
    }

    public function test_providers_are_selected_by_config_and_read_local_tables(): void
    {
        $penerima = (new PenerimaKipk(['nama_lengkap' => 'Ahmad', 'nim' => '2241010001']))->aturNomor('1234.567.89012.1766.761');
        $penerima->save();

        $kip = app(KipRecipientProvider::class);
        $this->assertTrue($kip->cariByNim('2241010001')->is($penerima));
        $this->assertTrue($kip->cariByNomorPendaftaran('1234 567 89012 1766 761')->is($penerima));
        $this->assertNull($kip->cariByNomorPendaftaran('0000.000.00000.0000.000'));
        $this->assertNull($kip->cariByNim(''));

        config(['unusa.kip_recipient_provider' => 'api', 'unusa.academic_provider' => 'api']);
        $this->assertInstanceOf(ApiKipRecipientProvider::class, app(KipRecipientProvider::class));
        $this->assertInstanceOf(ApiUnusaAcademicProvider::class, app(UnusaAcademicProvider::class));
        $this->expectException(\RuntimeException::class);
        app(KipRecipientProvider::class)->cariByNim('2241010001');
    }
}
