<?php

namespace Tests\Feature;

use App\Enums\KegiatanStatus;
use App\Enums\UserRole;
use App\Http\Requests\Pengguna\RegisterUserUmumRequest;
use App\Http\Requests\Pengguna\UserUmumRequest;
use App\Models\Fakultas;
use App\Models\Kegiatan;
use App\Models\ProgramStudi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class PenggunaFormPerRoleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Pendaftaran umum publik disembunyikan secara bawaan; tes di sini menguji aturannya.
        config(['unusa.registrasi_umum_aktif' => true]);

        $this->admin = User::factory()->superAdmin()->create();
    }

    protected function kegiatan(string $kategori, string $nama): Kegiatan
    {
        return Kegiatan::create([
            'nama_kegiatan' => $nama,
            'kategori' => $kategori,
            'metode_pelaksanaan' => 'offline',
            'sasaran_peserta' => 'umum',
            'tanggal_mulai' => now()->addWeek(),
            'lokasi_type' => 'unusa_campus',
            'lokasi_nama' => 'Auditorium',
            'file_panduan_pdf' => 'kegiatan/panduan.pdf',
            'biaya' => 0,
            'kuota' => 100,
            'status' => KegiatanStatus::Published,
        ]);
    }

    protected function simpan(array $data)
    {
        return $this->actingAs($this->admin)->post(route('admin.users.store'), $data);
    }

    protected function ubah(User $user, array $data)
    {
        return $this->actingAs($this->admin)->put(route('admin.users.update', $user->id), $data);
    }

    protected function userUmumValid(array $override = []): array
    {
        return array_merge([
            'role' => 'user_umum',
            'name' => 'Siti Aminah, S.Kom.',
            'asal_kampus' => 'Universitas Airlangga',
            'nim' => '187'.Str::random(6),
            'program_studi_teks' => 'S1 Teknik Informatika',
            'email' => 'siti'.Str::random(4).'@gmail.com',
            'phone' => '+62 812-3456-7890',
            'password' => 'RahasiaKuat123',
        ], $override);
    }

    public function test_user_umum_required_fields_and_separate_columns(): void
    {
        $this->simpan(['role' => 'user_umum'])
            ->assertSessionHasErrors(['name', 'asal_kampus', 'nim', 'program_studi_teks', 'email', 'phone', 'password']);

        $this->simpan($this->userUmumValid(['email' => 'siti.aminah@gmail.com', 'nim' => '187001']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $user = User::where('email', 'siti.aminah@gmail.com')->firstOrFail();
        $this->assertSame(UserRole::UserUmum, $user->role);
        $this->assertSame('Universitas Airlangga', $user->asal_kampus);
        $this->assertSame('187001', $user->nim);
        $this->assertSame('S1 Teknik Informatika', $user->program_studi_teks);
        $this->assertSame('081234567890', $user->phone, 'Nomor +62 dinormalisasi ke format 08...');
        $this->assertTrue(Hash::check('RahasiaKuat123', $user->password));
    }

    public function test_user_umum_phone_must_be_indonesian_mobile_number(): void
    {
        foreach (['12345', '0212345678', 'nomor-saya', '+1 415 555 0100'] as $nomorSalah) {
            $this->simpan($this->userUmumValid(['phone' => $nomorSalah]))->assertSessionHasErrors('phone');
        }

        $this->simpan($this->userUmumValid(['phone' => '0857 1234 5678']))->assertSessionHasNoErrors();
    }

    public function test_verifikator_email_domain_and_scope_fields(): void
    {
        $prodi = ProgramStudi::where('kode', 'FKES-S1-GIZI')->first();
        $fakultas = Fakultas::where('kode', 'FKIP')->first();
        $dasar = ['role' => 'verifikator_berkas', 'name' => 'Admin Gizi', 'password' => 'Verifikator123'];

        $this->simpan($dasar + ['email' => 'admin.gizi@gmail.com', 'verifikator_level' => 'prodi', 'program_studi_id' => $prodi->id])
            ->assertSessionHasErrors(['email' => 'Email verifikator harus memakai domain resmi: @unusa.ac.id.']);
        $this->simpan($dasar + ['email' => 'admin.gizi@student.unusa.ac.id', 'verifikator_level' => 'prodi', 'program_studi_id' => $prodi->id])
            ->assertSessionHasErrors('email');

        $this->simpan($dasar + ['email' => 'admin.gizi@unusa.ac.id', 'verifikator_level' => 'prodi'])
            ->assertSessionHasErrors(['program_studi_id' => 'Admin Prodi wajib memilih program studi.']);
        $this->simpan($dasar + ['email' => 'admin.fkip@unusa.ac.id', 'verifikator_level' => 'fakultas'])
            ->assertSessionHasErrors(['fakultas_id' => 'Admin Fakultas wajib memilih fakultas.']);
        $this->simpan($dasar + ['email' => 'admin.x@unusa.ac.id', 'verifikator_level' => 'rektorat'])
            ->assertSessionHasErrors('verifikator_level');

        // Admin Prodi: fakultas terisi otomatis dari prodi.
        $this->simpan($dasar + ['email' => 'Admin.Gizi@UNUSA.ac.id', 'verifikator_level' => 'prodi', 'program_studi_id' => $prodi->id, 'fakultas_id' => $fakultas->id])
            ->assertSessionHasNoErrors();
        $adminProdi = User::where('email', 'admin.gizi@unusa.ac.id')->firstOrFail();
        $this->assertSame('prodi', $adminProdi->verifikator_level);
        $this->assertSame($prodi->id, $adminProdi->program_studi_id);
        $this->assertSame($prodi->fakultas_id, $adminProdi->fakultas_id);

        // Admin Fakultas: tanpa prodi.
        $this->simpan($dasar + ['email' => 'admin.fkip@unusa.ac.id', 'verifikator_level' => 'fakultas', 'fakultas_id' => $fakultas->id, 'program_studi_id' => $prodi->id])
            ->assertSessionHasNoErrors();
        $adminFakultas = User::where('email', 'admin.fkip@unusa.ac.id')->firstOrFail();
        $this->assertSame($fakultas->id, $adminFakultas->fakultas_id);
        $this->assertNull($adminFakultas->program_studi_id);
    }

    public function test_verifikator_email_domains_come_from_config(): void
    {
        config(['unusa.verifikator_email_domains' => ['unusa.ac.id', 'fk.unusa.ac.id']]);
        $fakultas = Fakultas::where('kode', 'FK')->first();

        $this->simpan([
            'role' => 'verifikator_berkas', 'name' => 'Admin FK', 'email' => 'admin@fk.unusa.ac.id',
            'password' => 'Verifikator123', 'verifikator_level' => 'fakultas', 'fakultas_id' => $fakultas->id,
        ])->assertSessionHasNoErrors();
    }

    public function test_juri_may_only_be_assigned_to_lomba_and_assignment_is_editable(): void
    {
        $lombaA = $this->kegiatan('lomba', 'Lomba Poster');
        $lombaB = $this->kegiatan('lomba', 'Lomba Esai');
        $seminar = $this->kegiatan('seminar', 'Seminar Kesehatan');
        $dasar = ['role' => 'penilai_karya', 'name' => 'Dr. Juri', 'email' => 'juri@gmail.com', 'password' => 'JuriLomba123'];

        $this->simpan($dasar + ['lomba_ids' => [$lombaA->id, $seminar->id]])
            ->assertSessionHasErrors(['lomba_ids.1' => 'Kegiatan yang dipilih untuk dijuri harus berkategori lomba.']);

        $this->simpan($dasar + ['lomba_ids' => [$lombaA->id]])->assertSessionHasNoErrors();
        $juri = User::where('email', 'juri@gmail.com')->firstOrFail();
        $this->assertSame([$lombaA->id], $juri->lombaDinilai()->pluck('kegiatan.id')->all());

        $this->ubah($juri, ['name' => 'Dr. Juri', 'email' => 'juri@gmail.com', 'lomba_ids' => [$lombaB->id]])->assertSessionHasNoErrors();
        $this->assertSame([$lombaB->id], $juri->lombaDinilai()->pluck('kegiatan.id')->all());

        // Edit tanpa field lomba_ids tidak menghapus penugasan.
        $this->ubah($juri, ['name' => 'Dr. Juri Baru', 'email' => 'juri@gmail.com'])->assertSessionHasNoErrors();
        $this->assertSame([$lombaB->id], $juri->fresh()->lombaDinilai()->pluck('kegiatan.id')->all());
    }

    public function test_super_admin_form_only_needs_name_email_password(): void
    {
        $this->simpan(['role' => 'super_admin'])->assertSessionHasErrors(['name', 'email', 'password']);
        $this->simpan(['role' => 'super_admin', 'name' => 'Admin Dua', 'email' => 'admin2@gmail.com', 'password' => 'AdminDua123'])
            ->assertSessionHasNoErrors();

        $this->assertSame(UserRole::SuperAdmin, User::where('email', 'admin2@gmail.com')->first()->role);
    }

    public function test_password_rules_require_letters_and_numbers(): void
    {
        $this->simpan($this->userUmumValid(['password' => 'pendek1']))->assertSessionHasErrors('password');
        $this->simpan($this->userUmumValid(['password' => 'hanyahurufsaja']))->assertSessionHasErrors('password');
        $this->simpan($this->userUmumValid(['password' => '1234567890']))->assertSessionHasErrors('password');
    }

    public function test_edit_without_password_keeps_password_and_role_cannot_change(): void
    {
        $this->simpan($this->userUmumValid(['email' => 'budi@gmail.com', 'nim' => '187002']))->assertSessionHasNoErrors();
        $user = User::where('email', 'budi@gmail.com')->firstOrFail();
        $hashLama = $user->password;

        $this->ubah($user, $this->userUmumValid([
            'role' => 'super_admin', 'email' => 'budi@gmail.com', 'nim' => '187002', 'password' => '', 'asal_kampus' => 'ITS',
        ]))->assertSessionHasNoErrors()->assertSessionHas('success');

        $user->refresh();
        $this->assertSame($hashLama, $user->password, 'Password kosong saat edit tidak boleh mengubah password.');
        $this->assertSame('ITS', $user->asal_kampus);
        $this->assertSame(UserRole::UserUmum, $user->role, 'Role tidak berubah lewat form edit.');

        $this->ubah($user, $this->userUmumValid(['email' => 'budi@gmail.com', 'nim' => '187002', 'password' => 'PasswordBaru99']))->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('PasswordBaru99', $user->fresh()->password));
    }

    public function test_edit_keeps_unique_checks_against_other_users(): void
    {
        // NIM wajib unik hanya di antara akun UNUSA (is_unusa); lihat PenyelarasanPenggunaTest.
        $this->simpan($this->userUmumValid(['email' => 'satu@gmail.com', 'nim' => '187101', 'is_unusa' => true]))->assertSessionHasNoErrors();
        $this->simpan($this->userUmumValid(['email' => 'dua@gmail.com', 'nim' => '187102', 'is_unusa' => true]))->assertSessionHasNoErrors();
        $dua = User::where('email', 'dua@gmail.com')->firstOrFail();

        $this->ubah($dua, $this->userUmumValid(['email' => 'satu@gmail.com', 'nim' => '187101', 'password' => '']))
            ->assertSessionHasErrors(['email', 'nim']);
    }

    public function test_public_registration_uses_the_same_user_umum_validation(): void
    {
        $this->assertSame(
            array_keys(UserUmumRequest::aturan(null, true)),
            array_keys((new RegisterUserUmumRequest)->rules())
        );

        $salah = ['name' => '', 'asal_kampus' => '', 'nim' => '', 'program_studi_teks' => '', 'email' => 'bukan-email', 'phone' => '0212345678', 'password' => 'abc'];

        // Session diserialisasi JSON: setelah respons, "errors" tersimpan sebagai array.
        $kunciError = function ($response): array {
            $errors = $response->getSession()->get('errors');
            $pesan = $errors instanceof ViewErrorBag
                ? $errors->getBag('default')->getMessages()
                : ($errors['default']['messages'] ?? []);

            return array_keys($pesan);
        };

        $errorAdmin = $kunciError($this->simpan(['role' => 'user_umum'] + $salah));

        $this->flushSession();
        auth()->logout();
        $errorPublik = $kunciError($this->post(route('register.post'), ['jalur' => 'umum'] + $salah));

        sort($errorAdmin);
        sort($errorPublik);
        $this->assertSame(['asal_kampus', 'email', 'name', 'nim', 'password', 'phone', 'program_studi_teks'], $errorAdmin);
        $this->assertSame($errorAdmin, $errorPublik);
    }

    public function test_public_registration_creates_user_umum_account(): void
    {
        $this->post(route('register.post'), $this->userUmumValid([
            'jalur' => 'umum', 'email' => 'rina@gmail.com', 'nim' => '187003', 'password_confirmation' => 'RahasiaKuat123',
        ]))->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'rina@gmail.com')->firstOrFail();
        $this->assertSame(UserRole::UserUmum, $user->role);
        $this->assertSame('Universitas Airlangga', $user->asal_kampus);
        $this->assertSame('081234567890', $user->phone);
        $this->assertSame('Pending', $user->verification_status);
        $this->assertSame('umum', $user->jalur_pendaftaran);
        $this->assertFalse($user->hasVerifiedEmail(), 'Akun Peserta Umum aktif setelah verifikasi email.');
        $this->assertAuthenticatedAs($user);

        auth()->logout();
        $this->post(route('register.post'), $this->userUmumValid(['jalur' => 'umum', 'password_confirmation' => 'beda']))
            ->assertSessionHasErrors('password');
    }

    public function test_mahasiswa_kipk_admin_form_behaves_as_before(): void
    {
        // Payload persis seperti form lama (field & nilai bawaan tersembunyi).
        $this->simpan([
            'role' => 'mahasiswa_kipk',
            'name' => '  Ahmad Fauzan  ',
            'email' => 'Fauzan.Uji@unusa.ac.id',
            'nim' => '',
            'phone' => '0812 3456',
            'angkatan' => '2024',
            'prodi' => 'S1 Sistem Informasi',
            'university' => 'Universitas Nahdlatul Ulama Surabaya',
            'password' => 'password',
        ])->assertSessionHasNoErrors();

        $user = User::where('email', 'fauzan.uji@unusa.ac.id')->firstOrFail();
        $this->assertSame(UserRole::MahasiswaKipk, $user->role);
        $this->assertSame('Ahmad Fauzan', $user->name);
        $this->assertNull($user->nim);
        $this->assertSame('0812 3456', $user->phone, 'Nomor telepon KIP-K tidak dinormalisasi/divalidasi format (perilaku lama).');
        $this->assertSame('KIP UNUSA', $user->kip_status);
        $this->assertSame('Verified', $user->verification_status);
        $this->assertSame('S1 Sistem Informasi', $user->prodi);
        $this->assertSame('2024', $user->angkatan);
        $this->assertNull($user->program_studi_id);

        // Aturan lama tetap: password minimal 8 (tanpa syarat huruf+angka), email unik.
        $this->simpan(['role' => 'mahasiswa_kipk', 'name' => 'X', 'email' => 'fauzan.uji@unusa.ac.id', 'password' => 'pendek'])
            ->assertSessionHasErrors(['email', 'password']);

        // Akun KIP-K belum bisa diedit di form ini.
        $this->ubah($user, ['name' => 'Ganti'])->assertSessionHas('error');
        $this->assertSame('Ahmad Fauzan', $user->fresh()->name);
    }

    public function test_old_self_selected_kipk_registration_no_longer_creates_kipk_account(): void
    {
        // Pilihan "Kategori Penerima" dihapus: status KIP-K kini ditentukan sistem (lihat PendaftaranDuaJalurTest).
        $this->post(route('register.post'), [
            'name' => 'Mahasiswa KIP Uji',
            'email' => 'kip.uji@unusa.ac.id',
            'nim' => '2240099001',
            'phone' => '',
            'angkatan' => '2025',
            'prodi' => 'S1 Gizi',
            'university' => 'Universitas Nahdlatul Ulama Surabaya',
            'kip_status' => 'KIP UNUSA',
            'password' => 'Mahasiswa123',
            'password_confirmation' => 'Mahasiswa123',
        ])->assertSessionHasErrors(['asal_kampus', 'program_studi_teks', 'phone']);

        $this->assertFalse(User::where('email', 'kip.uji@unusa.ac.id')->exists());
    }

    public function test_pengguna_page_sends_options_and_role_details(): void
    {
        $lomba = $this->kegiatan('lomba', 'Lomba Video');
        $this->kegiatan('seminar', 'Seminar Umum');
        $juri = User::factory()->role(UserRole::PenilaiKarya)->create();
        $juri->lombaDinilai()->attach($lomba->id);
        $prodi = ProgramStudi::where('kode', 'FKK-S1-KEB')->first();
        User::factory()->role(UserRole::VerifikatorBerkas)->create([
            'email' => 'admin.keb@unusa.ac.id', 'verifikator_level' => 'prodi', 'program_studi_id' => $prodi->id, 'fakultas_id' => $prodi->fakultas_id,
        ]);

        $html = $this->actingAs($this->admin)->get(route('admin.users'))->assertOk()->getContent();
        preg_match('#<script data-page="app" type="application/json">(.*?)</script>#s', $html, $m);
        $props = json_decode($m[1], true)['props'];

        $this->assertCount(5, $props['fakultasOptions']);
        $this->assertSame(26, collect($props['fakultasOptions'])->sum(fn ($f) => count($f['program_studi'])));
        $this->assertSame(['Lomba Video'], array_column($props['lombaOptions'], 'nama_kegiatan'));
        $this->assertSame(['unusa.ac.id'], $props['verifikatorEmailDomains']);

        $baris = collect($props['users']['data'])->keyBy('email');
        $this->assertSame(['Lomba Video'], array_column($baris[$juri->email]['lomba_dinilai'], 'nama_kegiatan'));
        $this->assertSame('S1 Kebidanan', $baris['admin.keb@unusa.ac.id']['program_studi']['nama']);
    }
}
