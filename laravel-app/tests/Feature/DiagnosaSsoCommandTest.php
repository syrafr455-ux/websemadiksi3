<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\MasterMahasiswaUnusa;
use App\Models\PenerimaKipk;
use App\Models\User;
use App\Support\Masking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiagnosaSsoCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_diagnosa_kasus_akun_tertunda(): void
    {
        $nim = '4330024012';

        MasterMahasiswaUnusa::create([
            'nim' => $nim,
            'nama_lengkap' => 'Ahmad Fulan',
            'angkatan' => 2024,
            'status_aktif' => 'aktif',
            'email_kampus' => '4330024012@student.unusa.ac.id',
        ]);

        $penerima = new PenerimaKipk([
            'nim' => $nim,
            'nama_lengkap' => 'Ahmad Fulan',
            'status_penetapan' => 'ditetapkan',
        ]);
        $penerima->aturNomor('KIP-2024-001');
        $penerima->save();

        User::factory()->create([
            'nim' => $nim,
            'role' => UserRole::MahasiswaKipk,
            'is_unusa' => true,
            'is_blocked' => false,
            'email_verified_at' => null,
            'keycloak_sub' => null,
        ]);

        $this->artisan('semadiksi:diagnosa-sso', ['nim' => $nim])
            ->assertSuccessful()
            ->expectsOutputToContain(Masking::nim($nim))
            ->expectsOutputToContain('Ada')
            ->expectsOutputToContain('mahasiswa_kipk')
            ->expectsOutputToContain('Belum terverifikasi')
            ->expectsOutputToContain('aktivasi via SSO');
    }

    public function test_diagnosa_kasus_staf(): void
    {
        $nim = '1122334455';

        MasterMahasiswaUnusa::create([
            'nim' => $nim,
            'nama_lengkap' => 'Admin Staff',
            'angkatan' => 2020,
            'status_aktif' => 'aktif',
            'email_kampus' => '1122334455@student.unusa.ac.id',
        ]);

        User::factory()->create([
            'nim' => $nim,
            'role' => UserRole::SuperAdmin,
            'is_unusa' => true,
            'is_blocked' => false,
            'email_verified_at' => now(),
            'keycloak_sub' => null,
        ]);

        $this->artisan('semadiksi:diagnosa-sso', ['nim' => $nim])
            ->assertSuccessful()
            ->expectsOutputToContain('super_admin')
            ->expectsOutputToContain('role tidak diizinkan SSO');
    }

    public function test_diagnosa_kasus_akun_diblokir(): void
    {
        $nim = '4330024013';

        MasterMahasiswaUnusa::create([
            'nim' => $nim,
            'nama_lengkap' => 'Mahasiswa Terblokir',
            'angkatan' => 2024,
            'status_aktif' => 'aktif',
            'email_kampus' => '4330024013@student.unusa.ac.id',
        ]);

        User::factory()->create([
            'nim' => $nim,
            'role' => UserRole::MahasiswaKipk,
            'is_unusa' => true,
            'is_blocked' => true,
            'email_verified_at' => now(),
            'keycloak_sub' => 'sub-blocked',
        ]);

        $this->artisan('semadiksi:diagnosa-sso', ['nim' => $nim])
            ->assertSuccessful()
            ->expectsOutputToContain('Diblokir')
            ->expectsOutputToContain('akun diblokir');
    }

    public function test_diagnosa_kasus_akun_tidak_ada_tapi_di_master_dan_penerima_kipk(): void
    {
        $nim = '4330024099';

        MasterMahasiswaUnusa::create([
            'nim' => $nim,
            'nama_lengkap' => 'Calon Mahasiswa',
            'angkatan' => 2024,
            'status_aktif' => 'aktif',
            'email_kampus' => '4330024099@student.unusa.ac.id',
        ]);

        $penerima = new PenerimaKipk([
            'nim' => $nim,
            'nama_lengkap' => 'Calon Mahasiswa',
            'status_penetapan' => 'ditetapkan',
        ]);
        $penerima->aturNomor('KIP-2024-099');
        $penerima->save();

        $this->artisan('semadiksi:diagnosa-sso', ['nim' => $nim])
            ->assertSuccessful()
            ->expectsOutputToContain('Tidak Ada')
            ->expectsOutputToContain('Ada')
            ->expectsOutputToContain('aktivasi via SSO');
    }

    public function test_diagnosa_kasus_nim_tidak_ada_di_master(): void
    {
        $nim = '9999999999';

        $this->artisan('semadiksi:diagnosa-sso', ['nim' => $nim])
            ->assertSuccessful()
            ->expectsOutputToContain('Tidak Ada')
            ->expectsOutputToContain('NIM tidak ada di data master');
    }

    public function test_diagnosa_nim_dengan_spasi_dinormalisasi(): void
    {
        $nim = ' 4330024012 ';

        MasterMahasiswaUnusa::create([
            'nim' => '4330024012',
            'nama_lengkap' => 'Ahmad Fulan',
            'angkatan' => 2024,
            'status_aktif' => 'aktif',
            'email_kampus' => '4330024012@student.unusa.ac.id',
        ]);

        $penerima = new PenerimaKipk([
            'nim' => '4330024012',
            'nama_lengkap' => 'Ahmad Fulan',
            'status_penetapan' => 'ditetapkan',
        ]);
        $penerima->aturNomor('KIP-2024-001');
        $penerima->save();

        $this->artisan('semadiksi:diagnosa-sso', ['nim' => $nim])
            ->assertSuccessful()
            ->expectsOutputToContain(Masking::nim('4330024012'));
    }

    public function test_diagnosa_tidak_mencetak_kata_sandi_atau_token(): void
    {
        $nim = '4330024012';

        $user = User::factory()->create([
            'nim' => $nim,
            'role' => UserRole::MahasiswaKipk,
            'is_unusa' => true,
            'password' => 'rahasia-super-terenkripsi-123',
            'remember_token' => 'token-remember-sangat-rahasia',
        ]);

        $this->artisan('semadiksi:diagnosa-sso', ['nim' => $nim])
            ->assertSuccessful()
            ->doesntExpectOutputToContain('rahasia-super-terenkripsi-123')
            ->doesntExpectOutputToContain('token-remember-sangat-rahasia');
    }

    public function test_diagnosa_kasus_mahasiswa_non_kipk_dapat_login_sebagai_user_umum(): void
    {
        $nim = '4330024100';

        MasterMahasiswaUnusa::create([
            'nim' => $nim,
            'nama_lengkap' => 'Mahasiswa Non KIP Baru',
            'angkatan' => 2024,
            'status_aktif' => 'aktif',
            'email_kampus' => '4330024100@student.unusa.ac.id',
        ]);

        // Tidak ada baris penerima KIP-K dan belum ada akun users

        $this->artisan('semadiksi:diagnosa-sso', ['nim' => $nim])
            ->assertSuccessful()
            ->expectsOutputToContain('Tidak Ada')
            ->expectsOutputToContain('Ada')
            ->expectsOutputToContain('Tidak Terdaftar')
            ->expectsOutputToContain('buat akun baru via SSO')
            ->expectsOutputToContain('Dapat Login (Akun Baru User Umum)');
    }

    public function test_diagnosa_menampilkan_petunjuk_untuk_staf_dan_role_yang_ditolak_sso(): void
    {
        $nim = '1122334466';

        MasterMahasiswaUnusa::create([
            'nim' => $nim,
            'nama_lengkap' => 'Staf Verifikator',
            'angkatan' => 2020,
            'status_aktif' => 'aktif',
            'email_kampus' => '1122334466@student.unusa.ac.id',
        ]);

        User::factory()->create([
            'nim' => $nim,
            'role' => UserRole::VerifikatorBerkas,
            'is_unusa' => true,
            'is_blocked' => false,
            'email_verified_at' => now(),
            'keycloak_sub' => null,
        ]);

        $this->artisan('semadiksi:diagnosa-sso', ['nim' => $nim])
            ->assertSuccessful()
            ->expectsOutputToContain('verifikator_berkas')
            ->expectsOutputToContain('role tidak diizinkan SSO')
            ->expectsOutputToContain('gunakan login kata sandi');
    }
}
