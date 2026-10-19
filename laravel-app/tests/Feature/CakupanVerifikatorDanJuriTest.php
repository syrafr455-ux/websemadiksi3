<?php

namespace Tests\Feature;

use App\Enums\KegiatanStatus;
use App\Enums\RegistrationPaymentStatus;
use App\Enums\UserRole;
use App\Models\CustomForm;
use App\Models\CustomFormSubmission;
use App\Models\Fakultas;
use App\Models\Kegiatan;
use App\Models\KegiatanRegistration;
use App\Models\KegiatanSubmission;
use App\Models\KipkDocument;
use App\Models\ProgramStudi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CakupanVerifikatorDanJuriTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    /** @var array<string, User> */
    protected array $mhs = [];

    /** @var array<string, KipkDocument> */
    protected array $berkas = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->superAdmin()->create();

        // Gizi & Kesmas di Fakultas Kesehatan, Pendidikan Dokter di Fakultas Kedokteran, satu mahasiswa tanpa prodi.
        foreach (['gizi' => 'FKES-S1-GIZI', 'kesmas' => 'FKES-S1-KESMAS', 'dokter' => 'FK-S1-PDOK', 'tanpa' => null] as $kunci => $kode) {
            $prodi = $kode ? ProgramStudi::where('kode', $kode)->firstOrFail() : null;
            $this->mhs[$kunci] = User::factory()->role(UserRole::MahasiswaKipk)->create([
                'name' => "Mahasiswa {$kunci}",
                'program_studi_id' => $prodi?->id,
                'fakultas_id' => $prodi?->fakultas_id,
            ]);
            $this->berkas[$kunci] = KipkDocument::create([
                'user_id' => $this->mhs[$kunci]->id,
                'user_name' => "Mahasiswa {$kunci}",
                'category' => 'Akademik',
                'tahap' => 3,
                'title' => "Berkas {$kunci}",
                'file_name' => "{$kunci}.pdf",
                'file_url' => "https://example.com/{$kunci}.pdf",
                'status' => 'Menunggu Review',
            ]);
        }
    }

    protected function adminProdi(string $kodeProdi): User
    {
        $prodi = ProgramStudi::where('kode', $kodeProdi)->firstOrFail();

        return User::factory()->role(UserRole::VerifikatorBerkas)->create([
            'verifikator_level' => 'prodi', 'program_studi_id' => $prodi->id, 'fakultas_id' => $prodi->fakultas_id,
        ]);
    }

    protected function adminFakultas(string $kodeFakultas): User
    {
        return User::factory()->role(UserRole::VerifikatorBerkas)->create([
            'verifikator_level' => 'fakultas', 'fakultas_id' => Fakultas::where('kode', $kodeFakultas)->firstOrFail()->id,
        ]);
    }

    /** @return list<string> judul berkas yang tampil di /admin/berkas */
    protected function berkasTerlihat(User $user): array
    {
        $response = $this->actingAs($user)->get(route('admin.berkas'))->assertOk();

        return collect($response->viewData('documents')->items())->pluck('title')->sort()->values()->all();
    }

    public function test_admin_prodi_only_sees_documents_of_own_program(): void
    {
        $this->assertSame(['Berkas gizi'], $this->berkasTerlihat($this->adminProdi('FKES-S1-GIZI')));
    }

    public function test_admin_fakultas_sees_all_programs_in_own_faculty_only(): void
    {
        $this->assertSame(['Berkas gizi', 'Berkas kesmas'], $this->berkasTerlihat($this->adminFakultas('FKES')));
        $this->assertSame(['Berkas dokter'], $this->berkasTerlihat($this->adminFakultas('FK')));
    }

    public function test_student_without_program_is_only_visible_to_super_admin_with_marker(): void
    {
        $this->assertSame(['Berkas dokter', 'Berkas gizi', 'Berkas kesmas', 'Berkas tanpa'], $this->berkasTerlihat($this->superAdmin));
        $this->actingAs($this->superAdmin)->get(route('admin.berkas'))->assertSee('Prodi belum diatur');

        foreach ([$this->adminProdi('FKES-S1-GIZI'), $this->adminFakultas('FKES'), $this->adminFakultas('FK')] as $verifikator) {
            $this->assertNotContains('Berkas tanpa', $this->berkasTerlihat($verifikator));
            $this->actingAs($verifikator)->post(route('admin.berkas.update', $this->berkas['tanpa']->id), ['status' => 'Disetujui'])->assertForbidden();
        }
    }

    public function test_verifikator_without_configured_scope_sees_nothing(): void
    {
        $tanpaCakupan = User::factory()->role(UserRole::VerifikatorBerkas)->create(['verifikator_level' => null]);
        $prodiKosong = User::factory()->role(UserRole::VerifikatorBerkas)->create(['verifikator_level' => 'prodi', 'program_studi_id' => null]);

        $this->assertSame([], $this->berkasTerlihat($tanpaCakupan));
        $this->assertSame([], $this->berkasTerlihat($prodiKosong));
    }

    public function test_status_change_and_download_outside_scope_are_forbidden(): void
    {
        $adminGizi = $this->adminProdi('FKES-S1-GIZI');

        $this->actingAs($adminGizi)->post(route('admin.berkas.update', $this->berkas['kesmas']->id), ['status' => 'Disetujui'])->assertForbidden();
        $this->actingAs($adminGizi)->patch(route('admin.berkas.status', $this->berkas['dokter']->id), ['status' => 'Ditolak'])->assertForbidden();
        $this->actingAs($adminGizi)->get(route('admin.berkas.download', $this->berkas['kesmas']->id))->assertForbidden();
        $this->assertSame('Menunggu Review', $this->berkas['kesmas']->fresh()->status);

        $this->actingAs($adminGizi)->post(route('admin.berkas.update', $this->berkas['gizi']->id), ['status' => 'Disetujui', 'score' => 90])
            ->assertSessionHas('success');
        $this->assertSame('Disetujui', $this->berkas['gizi']->fresh()->status);

        // Admin Fakultas Kesehatan boleh memproses prodi lain dalam fakultasnya, bukan fakultas lain.
        $adminFkes = $this->adminFakultas('FKES');
        $this->actingAs($adminFkes)->post(route('admin.berkas.update', $this->berkas['kesmas']->id), ['status' => 'Disetujui'])->assertSessionHas('success');
        $this->actingAs($adminFkes)->post(route('admin.berkas.update', $this->berkas['dokter']->id), ['status' => 'Disetujui'])->assertForbidden();
    }

    public function test_monev_forms_follow_the_same_scope_and_other_forms_are_hidden_from_verifikator(): void
    {
        $monev = CustomForm::create(['form_key' => 'monev', 'title' => 'Monev Akademik', 'is_active' => true]);
        $pencairan = CustomForm::create(['form_key' => 'pencairan', 'title' => 'Pencairan', 'is_active' => true]);
        $isian = fn (CustomForm $form, string $kunci) => CustomFormSubmission::create([
            'custom_form_id' => $form->id,
            'user_id' => $this->mhs[$kunci]->id,
            'applicant_name' => "Pengaju {$kunci} {$form->form_key}",
            'answers' => ['ipk' => '3.5'],
            'status' => 'Menunggu Review',
        ]);
        $monevGizi = $isian($monev, 'gizi');
        $monevDokter = $isian($monev, 'dokter');
        $pencairanGizi = $isian($pencairan, 'gizi');
        $isian($monev, 'tanpa');

        $adminGizi = $this->adminProdi('FKES-S1-GIZI');
        $terlihat = fn (User $u) => collect($this->actingAs($u)->get(route('admin.submissions'))->assertOk()->viewData('submissions')->items())
            ->pluck('applicant_name')->sort()->values()->all();

        $this->assertSame(['Pengaju gizi monev'], $terlihat($adminGizi));
        $this->assertCount(4, $terlihat($this->superAdmin));
        $this->actingAs($this->superAdmin)->get(route('admin.submissions'))->assertSee('Pengaju gizi monev')->assertSee('Prodi belum diatur');

        $this->actingAs($adminGizi)->post(route('admin.submissions.update', $monevDokter->id), ['status' => 'Disetujui'])->assertForbidden();
        $this->actingAs($adminGizi)->post(route('admin.submissions.update', $pencairanGizi->id), ['status' => 'Disetujui'])->assertForbidden();
        $this->actingAs($adminGizi)->post(route('admin.submissions.update', $monevGizi->id), ['status' => 'Disetujui'])->assertSessionHas('success');
        $this->assertSame('Disetujui', $monevGizi->fresh()->status);
    }

    protected function lombaDenganKarya(string $nama): array
    {
        $lomba = Kegiatan::create([
            'nama_kegiatan' => $nama, 'kategori' => 'lomba', 'metode_pelaksanaan' => 'online', 'sasaran_peserta' => 'umum',
            'tanggal_mulai' => now()->addWeek(), 'lokasi_type' => 'online', 'lokasi_nama' => 'Zoom',
            'file_panduan_pdf' => 'kegiatan/panduan.pdf', 'biaya' => 0, 'kuota' => 50,
            'is_submission_active' => true, 'status' => KegiatanStatus::Published,
        ]);
        $reg = KegiatanRegistration::create([
            'kegiatan_id' => $lomba->id, 'booker_user_id' => $this->superAdmin->id,
            'nomor_tiket' => KegiatanRegistration::generateTicketNumber($lomba->id, 'GEN'),
            'participant_name' => "Peserta {$nama}", 'status_pembayaran' => RegistrationPaymentStatus::Free,
        ]);
        $karya = KegiatanSubmission::create([
            'registration_id' => $reg->id, 'kegiatan_id' => $lomba->id,
            'nama_kelompok_peserta' => "Karya {$nama}", 'file_karya_url' => 'https://example.com/karya.pdf',
        ]);

        return [$lomba, $karya];
    }

    public function test_juri_only_accesses_assigned_lomba_including_direct_urls(): void
    {
        [$lombaA, $karyaA] = $this->lombaDenganKarya('Lomba A');
        [$lombaB, $karyaB] = $this->lombaDenganKarya('Lomba B');
        $juri = User::factory()->role(UserRole::PenilaiKarya)->create();
        $juri->lombaDinilai()->attach($lombaA->id);

        // Index: hanya lomba yang ditugaskan, karya lomba itu saja.
        $props = fn ($response) => json_decode(preg_match('#<script data-page="app" type="application/json">(.*?)</script>#s', $response->getContent(), $m) ? $m[1] : '{}', true)['props'];
        $index = $props($this->actingAs($juri)->get(route('admin.penilaian'))->assertOk());
        $this->assertSame(['Lomba A'], array_column($index['kegiatans'], 'nama_kegiatan'));
        $this->assertSame($lombaA->id, $index['selectedKegiatanId']);
        $this->assertSame([$karyaA->id], array_column($index['submissions']['data'], 'id'));

        // URL langsung ke lomba lain ditolak.
        $this->actingAs($juri)->get(route('admin.penilaian', ['kegiatan_id' => $lombaB->id]))->assertForbidden();
        $this->actingAs($juri)->post(route('admin.penilaian.score', $karyaB->id), ['final_score' => 80])->assertForbidden();
        $this->actingAs($juri)->get(route('admin.penilaian.export', $lombaB->id))->assertForbidden();
        $csv = UploadedFile::fake()->createWithContent('nilai.csv', "ID_SUBMISSION,NILAI_AKHIR\n{$karyaB->id},77\n");
        $this->actingAs($juri)->post(route('admin.penilaian.import', $lombaB->id), ['csv_file' => $csv])->assertForbidden();
        $this->assertNull($karyaB->fresh()->nilai);

        // Lomba yang ditugaskan tetap bisa dinilai, diekspor, dan diimpor.
        $this->actingAs($juri)->post(route('admin.penilaian.score', $karyaA->id), ['final_score' => 88])->assertSessionHas('success');
        $this->actingAs($juri)->get(route('admin.penilaian.export', $lombaA->id))->assertOk();
        $csvA = UploadedFile::fake()->createWithContent('nilai.csv', "ID_SUBMISSION,NILAI_AKHIR\n{$karyaA->id},91\n");
        $this->actingAs($juri)->post(route('admin.penilaian.import', $lombaA->id), ['csv_file' => $csvA])->assertSessionHas('success');
        $this->assertEquals(91, (float) $karyaA->fresh()->final_score);
    }

    public function test_juri_without_assignment_sees_no_submissions_and_super_admin_sees_all(): void
    {
        [$lombaA] = $this->lombaDenganKarya('Lomba A');
        [$lombaB, $karyaB] = $this->lombaDenganKarya('Lomba B');
        $juriBaru = User::factory()->role(UserRole::PenilaiKarya)->create();

        $props = fn ($response) => json_decode(preg_match('#<script data-page="app" type="application/json">(.*?)</script>#s', $response->getContent(), $m) ? $m[1] : '{}', true)['props'];
        $index = $props($this->actingAs($juriBaru)->get(route('admin.penilaian'))->assertOk());
        $this->assertSame([], $index['kegiatans']);
        $this->assertSame([], $index['submissions']['data']);

        $this->actingAs($this->superAdmin)->get(route('admin.penilaian', ['kegiatan_id' => $lombaB->id]))->assertOk();
        $this->actingAs($this->superAdmin)->post(route('admin.penilaian.score', $karyaB->id), ['final_score' => 70])->assertSessionHas('success');
        $this->actingAs($this->superAdmin)->get(route('admin.penilaian.export', $lombaA->id))->assertOk();
    }
}
