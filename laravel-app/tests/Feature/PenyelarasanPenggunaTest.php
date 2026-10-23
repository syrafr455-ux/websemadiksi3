<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Requests\Pengguna\PenggunaRequest;
use App\Models\AuditLog;
use App\Models\CustomForm;
use App\Models\CustomFormSubmission;
use App\Models\KipkDocument;
use App\Models\MasterMahasiswaUnusa;
use App\Models\ProgramStudi;
use App\Models\User;
use App\Services\AktivasiMahasiswaService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Penyelarasan pengguna: formulir verifikator, keunikan NIM akun UNUSA,
 * prodi akun KIP-K lama, dan tes tanpa internet.
 */
class PenyelarasanPenggunaTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected ProgramStudi $gizi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->superAdmin()->create();
        $this->gizi = ProgramStudi::where('kode', 'FKES-S1-GIZI')->firstOrFail();
    }

    protected function adminProdiGizi(): User
    {
        return User::factory()->verifikator()->create([
            'verifikator_level' => 'prodi', 'program_studi_id' => $this->gizi->id, 'fakultas_id' => $this->gizi->fakultas_id,
        ]);
    }

    protected function mahasiswaGizi(array $atribut = []): User
    {
        return User::factory()->mahasiswaKipk()->create([
            'program_studi_id' => $this->gizi->id, 'fakultas_id' => $this->gizi->fakultas_id, ...$atribut,
        ]);
    }

    protected function isian(string $formKey, User $mahasiswa): CustomFormSubmission
    {
        $form = CustomForm::firstOrCreate(['form_key' => $formKey], ['title' => ucfirst($formKey), 'is_active' => true]);

        return CustomFormSubmission::create([
            'custom_form_id' => $form->id, 'user_id' => $mahasiswa->id,
            'applicant_name' => "{$mahasiswa->name} {$formKey}", 'answers' => ['isi' => 'x'], 'status' => 'Menunggu Review',
        ]);
    }

    // ---- C.1 Formulir verifikator -------------------------------------------------------------

    public function test_verifikator_processes_monev_and_pelaporan_in_scope_but_never_pencairan(): void
    {
        $this->assertSame(['monev', 'pelaporan'], config('unusa.verifikator_form_keys'));

        $verifikator = $this->adminProdiGizi();
        $mhs = $this->mahasiswaGizi(['name' => 'Ani']);
        $luar = User::factory()->mahasiswaKipk()->create(['name' => 'Budi', 'program_studi_id' => ProgramStudi::where('kode', 'FK-S1-PDOK')->value('id')]);

        $monev = $this->isian('monev', $mhs);
        $pelaporan = $this->isian('pelaporan', $mhs);
        $pencairan = $this->isian('pencairan', $mhs);
        $pelaporanLuar = $this->isian('pelaporan', $luar);

        $terlihat = collect($this->actingAs($verifikator)->get(route('admin.submissions'))->assertOk()->viewData('submissions')->items())
            ->pluck('id')->sort()->values()->all();
        $this->assertSame([$monev->id, $pelaporan->id], $terlihat);

        foreach ([$monev, $pelaporan] as $isian) {
            $this->actingAs($verifikator)->post(route('admin.submissions.update', $isian->id), ['status' => 'Disetujui'])->assertSessionHas('success');
            $this->assertSame('Disetujui', $isian->fresh()->status);
        }

        $this->actingAs($verifikator)->post(route('admin.submissions.update', $pencairan->id), ['status' => 'Disetujui'])->assertForbidden();
        $this->actingAs($verifikator)->post(route('admin.submissions.update', $pelaporanLuar->id), ['status' => 'Disetujui'])->assertForbidden();
        $this->assertSame('Menunggu Review', $pencairan->fresh()->status);

        // super_admin tetap memproses pencairan.
        $this->actingAs($this->admin)->post(route('admin.submissions.update', $pencairan->id), ['status' => 'Disetujui'])->assertSessionHas('success');
    }

    public function test_pencairan_stays_super_admin_only_even_if_listed_in_config(): void
    {
        config(['unusa.verifikator_form_keys' => ['monev', 'pelaporan', 'pencairan']]);
        $pencairan = $this->isian('pencairan', $this->mahasiswaGizi());

        $this->assertSame(['monev', 'pelaporan'], CustomFormSubmission::formVerifikator());
        $this->actingAs($this->adminProdiGizi())->post(route('admin.submissions.update', $pencairan->id), ['status' => 'Disetujui'])->assertForbidden();
    }

    // ---- C.2 Keunikan NIM -------------------------------------------------------------------

    public function test_database_enforces_unique_nim_only_among_unusa_accounts(): void
    {
        $kipk = User::factory()->mahasiswaKipk()->create(['nim' => '2241019999']);
        $this->assertTrue($kipk->is_unusa, 'Akun mahasiswa_kipk selalu akun UNUSA.');

        // Peserta umum kampus lain boleh memakai NIM yang sama, termasuk sesamanya.
        User::factory()->userUmum()->create(['nim' => '2241019999']);
        User::factory()->userUmum()->create(['nim' => '2241019999']);
        $this->assertSame(3, User::where('nim', '2241019999')->count());

        $this->expectException(QueryException::class);
        User::factory()->userUmum()->create(['nim' => '2241019999', 'is_unusa' => true]);
    }

    public function test_validation_matches_the_nim_rule(): void
    {
        User::factory()->userUmum()->create(['nim' => '187001', 'is_unusa' => true]);
        User::factory()->userUmum()->create(['nim' => '187002', 'is_unusa' => false]);
        $umum = fn (array $data) => [
            'role' => 'user_umum', 'name' => 'Peserta', 'asal_kampus' => 'Universitas Airlangga', 'program_studi_teks' => 'S1 Hukum',
            'email' => 'peserta'.uniqid().'@gmail.com', 'phone' => '081234567890', 'password' => 'RahasiaKuat123', ...$data,
        ];
        $simpan = fn (array $data) => $this->actingAs($this->admin)->post(route('admin.users.store'), $data);

        // Akun UNUSA: NIM yang sudah dipakai akun UNUSA ditolak; NIM milik peserta kampus lain boleh.
        $simpan($umum(['nim' => '187001', 'is_unusa' => true]))->assertSessionHasErrors('nim');
        $simpan($umum(['nim' => '187002', 'is_unusa' => true]))->assertSessionHasNoErrors();
        // Peserta kampus lain: NIM tidak wajib unik, cukup email unik.
        $simpan($umum(['nim' => '187001', 'is_unusa' => false]))->assertSessionHasNoErrors();
        $simpan($umum(['nim' => '187001', 'email' => User::where('nim', '187002')->value('email')]))->assertSessionHasErrors('email');

        // Mahasiswa KIP-K (selalu UNUSA).
        $kipk = fn (string $nim) => ['role' => 'mahasiswa_kipk', 'name' => 'KIP', 'email' => 'kip'.uniqid().'@gmail.com', 'nim' => $nim, 'password' => 'RahasiaKuat123'];
        $simpan($kipk('187001'))->assertSessionHasErrors('nim');
        $simpan($kipk('187003'))->assertSessionHasNoErrors();

        // Pendaftaran publik jalur Peserta Umum: bukan akun UNUSA, NIM boleh sama.
        config(['unusa.registrasi_umum_aktif' => true]);
        auth()->logout();
        $this->post(route('register.post'), [
            'name' => 'Peserta Publik', 'asal_kampus' => 'Universitas Brawijaya', 'nim' => '187001', 'program_studi_teks' => 'S1 Gizi',
            'email' => 'publik@gmail.com', 'phone' => '081234567891', 'password' => 'RahasiaKuat123', 'password_confirmation' => 'RahasiaKuat123',
            'is_unusa' => true,
        ])->assertSessionHasNoErrors();
        $this->assertFalse(User::where('email', 'publik@gmail.com')->firstOrFail()->is_unusa);
    }

    public function test_unusa_activation_and_nim_login_ignore_non_unusa_accounts_with_the_same_nim(): void
    {
        Mail::fake();
        MasterMahasiswaUnusa::create([
            'nim' => '2241010001', 'nama_lengkap' => 'Fauzan Hidayat', 'email_kampus' => 'fauzan@student.unusa.ac.id',
            'program_studi_id' => $this->gizi->id, 'angkatan' => '2024', 'status_aktif' => 'aktif',
        ]);
        $lain = User::factory()->userUmum()->create(['nim' => '2241010001', 'email' => 'kampus.lain@gmail.com', 'password' => 'RahasiaKuat123']);

        $this->post(app(AktivasiMahasiswaService::class)->buatLink('2241010001'), [
            'password' => 'RahasiaKuat1', 'password_confirmation' => 'RahasiaKuat1',
        ])->assertRedirect(route('login'));
        $unusa = User::where('email', 'fauzan@student.unusa.ac.id')->firstOrFail();
        $this->assertTrue($unusa->is_unusa);
        $this->post(route('logout'));

        // Login dengan NIM hanya mencocokkan akun UNUSA; peserta kampus lain masuk dengan email.
        $this->post(route('login.post'), ['identity' => '2241010001', 'password' => 'RahasiaKuat1'])->assertRedirect();
        $this->assertAuthenticatedAs($unusa);
        $this->post(route('logout'));
        $this->post(route('login.post'), ['identity' => '2241010001', 'password' => 'RahasiaKuat123'])->assertSessionHasErrors('identity');
        $this->post(route('login.post'), ['identity' => 'kampus.lain@gmail.com', 'password' => 'RahasiaKuat123']);
        $this->assertAuthenticatedAs($lain);
    }

    // ---- C.3 Prodi akun KIP-K lama -----------------------------------------------------------

    public function test_super_admin_sets_program_of_legacy_kipk_account_and_verifikator_then_sees_it(): void
    {
        $verifikator = $this->adminProdiGizi();
        $lama = User::factory()->mahasiswaKipk()->create(['name' => 'KIP Lama', 'nim' => '2041000001', 'phone' => '081200000001']);
        KipkDocument::create([
            'user_id' => $lama->id, 'user_name' => 'KIP Lama', 'category' => 'Akademik', 'tahap' => 1,
            'title' => 'Berkas KIP Lama', 'file_name' => 'lama.pdf', 'file_url' => 'https://example.com/lama.pdf', 'status' => 'Menunggu Review',
        ]);
        $berkasTerlihat = fn () => collect($this->actingAs($verifikator)->get(route('admin.berkas'))->assertOk()->viewData('documents')->items())->pluck('title')->all();
        $this->assertSame([], $berkasTerlihat());

        // Prodi di luar fakultas yang dipilih ditolak.
        $this->actingAs($this->admin)->post(route('admin.users.akademik', $lama->id), [
            'fakultas_id' => ProgramStudi::where('kode', 'FK-S1-PDOK')->value('fakultas_id'), 'program_studi_id' => $this->gizi->id,
        ])->assertSessionHasErrors('program_studi_id');

        $this->actingAs($this->admin)->post(route('admin.users.akademik', $lama->id), [
            'fakultas_id' => $this->gizi->fakultas_id, 'program_studi_id' => $this->gizi->id,
            // Field lain diabaikan.
            'name' => 'Diubah', 'nim' => '999', 'role' => 'super_admin',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $lama->refresh();
        $this->assertSame([$this->gizi->id, $this->gizi->fakultas_id], [$lama->program_studi_id, $lama->fakultas_id]);
        $this->assertSame(['KIP Lama', '2041000001', UserRole::MahasiswaKipk], [$lama->name, $lama->nim, $lama->role]);
        $this->assertSame(['Berkas KIP Lama'], $berkasTerlihat());

        $log = AuditLog::where('action', 'update_user_akademik')->latest('id')->firstOrFail();
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertStringContainsString('belum diatur -> S1 Gizi', $log->description);
    }

    public function test_program_can_only_be_set_on_kipk_accounts_by_super_admin(): void
    {
        $umum = User::factory()->userUmum()->create();
        $kipk = User::factory()->mahasiswaKipk()->create();
        $data = ['fakultas_id' => $this->gizi->fakultas_id, 'program_studi_id' => $this->gizi->id];

        $this->actingAs($this->admin)->post(route('admin.users.akademik', $umum->id), $data)->assertStatus(422);
        $this->actingAs($this->adminProdiGizi())->post(route('admin.users.akademik', $kipk->id), $data)->assertRedirect();
        $this->assertNull($kipk->fresh()->program_studi_id);
    }

    // ---- C.4 Tes tanpa internet --------------------------------------------------------------

    public function test_tests_never_need_the_internet(): void
    {
        // Pemeriksaan DNS email dimatikan hanya untuk tes (phpunit.xml); production tetap memakai email:rfc,dns.
        $this->assertSame('email:rfc', PenggunaRequest::aturanFormatEmailDns());
        config(['unusa.validasi_email_dns' => true]);
        $this->assertSame('email:rfc,dns', PenggunaRequest::aturanFormatEmailDns());

        // Request HTTP keluar yang tidak di-fake langsung gagal.
        $this->expectException(StrayRequestException::class);
        Http::get('https://example.com');
    }
}
