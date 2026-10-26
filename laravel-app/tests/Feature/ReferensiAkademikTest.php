<?php

namespace Tests\Feature;

use App\Contracts\UnusaAcademicProvider;
use App\Enums\UserRole;
use App\Models\Fakultas;
use App\Models\MasterMahasiswaUnusa;
use App\Models\ProgramStudi;
use App\Models\User;
use App\Services\Academic\DatabaseUnusaAcademicProvider;
use App\Services\UnusaAcademicService;
use Database\Seeders\ReferensiAkademikSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferensiAkademikTest extends TestCase
{
    use RefreshDatabase;

    protected function jumlahProdiPerFakultas(): array
    {
        return Fakultas::withCount('programStudi')->orderBy('kode')->pluck('program_studi_count', 'kode')->all();
    }

    public function test_migration_fills_5_fakultas_and_26_program_studi(): void
    {
        $this->assertSame(5, Fakultas::count());
        $this->assertSame(26, ProgramStudi::count());
        $this->assertSame(['FEBTD' => 5, 'FK' => 4, 'FKES' => 5, 'FKIP' => 5, 'FKK' => 7], $this->jumlahProdiPerFakultas());

        $this->assertDatabaseHas('program_studi', ['kode' => 'FKES-D4-TLM', 'nama' => 'D4 Analis Kesehatan (Teknologi Laboratorium Medis)', 'jenjang' => 'D4']);
        $this->assertDatabaseHas('program_studi', ['kode' => 'FKIP-PRO-PPG', 'nama' => 'Pendidikan Profesi Guru (PPG)', 'jenjang' => 'Profesi']);
        $this->assertSame('Fakultas Ekonomi Bisnis dan Teknologi Digital (FEBTD)', ProgramStudi::where('kode', 'FEBTD-S1-SI')->first()->fakultas->nama);
    }

    public function test_seeder_and_command_are_idempotent_and_restore_changed_names(): void
    {
        $idSebelum = ProgramStudi::orderBy('id')->pluck('id', 'kode')->all();
        Fakultas::where('kode', 'FK')->update(['nama' => 'Nama Diubah Manual']);

        (new ReferensiAkademikSeeder)->run();
        (new ReferensiAkademikSeeder)->run();
        $this->artisan('semadiksi:referensi-akademik')
            ->expectsOutputToContain('5 fakultas, 26 program studi')
            ->assertSuccessful();

        $this->assertSame(5, Fakultas::count());
        $this->assertSame(26, ProgramStudi::count());
        $this->assertSame($idSebelum, ProgramStudi::orderBy('id')->pluck('id', 'kode')->all(), 'Baris tidak boleh dibuat ulang.');
        $this->assertSame('Fakultas Kedokteran', Fakultas::where('kode', 'FK')->value('nama'));
    }

    public function test_reference_seeder_may_run_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('semadiksi:referensi-akademik')->assertSuccessful();
        $this->assertSame(26, ProgramStudi::count());
    }

    public function test_academic_provider_contract_reads_local_tables(): void
    {
        $provider = app(UnusaAcademicProvider::class);
        $this->assertInstanceOf(DatabaseUnusaAcademicProvider::class, $provider);

        $fk = Fakultas::where('kode', 'FK')->first();
        $this->assertCount(5, $provider->fakultas());
        $this->assertCount(26, $provider->programStudi());
        $this->assertCount(4, $provider->programStudi($fk->id));

        $opsi = collect(app(UnusaAcademicService::class)->fakultasDenganProdi())->keyBy('kode');
        $this->assertCount(7, $opsi['FKK']['program_studi']);

        $prodi = ProgramStudi::where('kode', 'FKK-S1-KEP')->first();
        $mahasiswa = User::factory()->role(UserRole::MahasiswaKipk)->create([
            'nim' => '2241010001',
            'program_studi_id' => $prodi->id,
            'fakultas_id' => $prodi->fakultas_id,
        ]);
        $this->assertSame('Fakultas Keperawatan dan Kebidanan', $mahasiswa->programStudi->fakultas->nama);

        // cariMahasiswaByNim membaca master_mahasiswa_unusa (bukan tabel users).
        $this->assertNull($provider->cariMahasiswaByNim('2241010001'));
        MasterMahasiswaUnusa::create([
            'nim' => '2241010001', 'nama_lengkap' => 'Mahasiswa Keperawatan', 'email_kampus' => 'kep@student.unusa.ac.id',
            'program_studi_id' => $prodi->id, 'angkatan' => '2024',
        ]);
        $hasil = $provider->cariMahasiswaByNim(' 2241010001 ');
        $this->assertSame($prodi->id, $hasil['program_studi_id']);
        $this->assertSame('S1 Keperawatan', $hasil['program_studi']);
        $this->assertSame('Fakultas Keperawatan dan Kebidanan', $hasil['fakultas']);
        $this->assertNull($provider->cariMahasiswaByNim('tidak-ada'));

        $verifikator = User::factory()->role(UserRole::VerifikatorBerkas)->create(['email' => 'admin.fkk@unusa.ac.id', 'fakultas_id' => $prodi->fakultas_id]);
        $this->assertSame($verifikator->fakultas_id, $provider->findStaffByEmail('ADMIN.FKK@unusa.ac.id')['fakultas_id']);
    }
}
