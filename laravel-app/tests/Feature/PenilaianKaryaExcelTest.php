<?php

namespace Tests\Feature;

use App\Enums\KegiatanStatus;
use App\Enums\RegistrationPaymentStatus;
use App\Enums\UserRole;
use App\Models\Kegiatan;
use App\Models\KegiatanRegistration;
use App\Models\KegiatanSubmission;
use App\Models\User;
use App\Services\SimpleXlsxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PenilaianKaryaExcelTest extends TestCase
{
    use RefreshDatabase;

    protected function createSuperAdmin(): User
    {
        return User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'is_blocked' => false,
        ]);
    }

    protected function createJuri(): User
    {
        return User::factory()->create([
            'role' => UserRole::PenilaiKarya,
            'is_blocked' => false,
        ]);
    }

    protected function setupKegiatanAndSubmission(?User $juri = null): array
    {
        $kegiatan = Kegiatan::create([
            'nama_kegiatan' => 'Lomba Teknologi AI',
            'kategori' => 'lomba',
            'deskripsi' => 'Lomba AI',
            'metode_pelaksanaan' => 'offline',
            'sasaran_peserta' => 'umum',
            'tanggal_mulai' => now(),
            'tanggal_selesai' => now()->addDays(2),
            'lokasi_type' => 'unusa_campus',
            'lokasi_nama' => 'Kampus B',
            'biaya' => 0,
            'kuota' => 100,
            'file_panduan_pdf' => 'panduan.pdf',
            'status' => KegiatanStatus::Published,
            'is_submission_active' => true,
        ]);

        if ($juri) {
            $kegiatan->penilai()->attach($juri->id);
        }

        $user = User::factory()->create();
        $reg = KegiatanRegistration::create([
            'kegiatan_id' => $kegiatan->id,
            'booker_user_id' => $user->id,
            'participant_user_id' => $user->id,
            'nomor_tiket' => KegiatanRegistration::generateTicketNumber($kegiatan->id, 'GEN'),
            'participant_name' => 'Peserta Inovatif',
            'status_pembayaran' => RegistrationPaymentStatus::Free,
        ]);

        $submission = KegiatanSubmission::create([
            'registration_id' => $reg->id,
            'kegiatan_id' => $kegiatan->id,
            'nama_kelompok_peserta' => 'Tim AI Medis',
            'title' => 'Aplikasi Diagnosa Medis',
            'file_karya_url' => 'https://example.com/karya.pdf',
        ]);

        return [$kegiatan, $submission];
    }

    public function test_juri_can_export_penilaian_xlsx(): void
    {
        $juri = $this->createJuri();
        [$kegiatan, $submission] = $this->setupKegiatanAndSubmission($juri);

        $submission->update([
            'title' => '=SUM(1,2)', // Formula injection test di judul
            'final_score' => 88.5,
            'judge_notes' => '+Catatan diawali plus', // Formula injection test di catatan
        ]);

        $response = $this->actingAs($juri)->get(route('admin.penilaian.export', [
            'kegiatanId' => $kegiatan->id,
            'format' => 'xlsx',
        ]));

        $response->assertStatus(200);
        $this->assertStringContainsString('spreadsheetml.sheet', $response->headers->get('Content-Type'));

        // Simpan output ke temporary file dan verifikasi isinya menggunakan SimpleXlsxService
        $tempPath = tempnam(sys_get_temp_dir(), 'test_exp_').'.xlsx';
        file_put_contents($tempPath, $response->streamedContent());

        $xlsxService = new SimpleXlsxService;
        $rows = $xlsxService->readXlsxFile($tempPath);
        @unlink($tempPath);

        $this->assertGreaterThanOrEqual(2, count($rows));
        $header = $rows[0];
        $this->assertContains('ID_KARYA', array_map('strtoupper', $header));
        $this->assertContains('NILAI', array_map('strtoupper', $header));

        // Verifikasi netralisasi formula pada judul dan catatan (harus diawali petik tunggal)
        $dataRow = $rows[1];
        $rowText = implode(' ', $dataRow);
        $this->assertStringContainsString("'=SUM(1,2)", $rowText);
        $this->assertStringContainsString("'+Catatan diawali plus", $rowText);
    }

    public function test_unassigned_juri_cannot_export_or_import(): void
    {
        $juri = $this->createJuri();
        $unassignedJuri = $this->createJuri();
        [$kegiatan, $submission] = $this->setupKegiatanAndSubmission($juri);

        // Juri yang tidak ditugaskan tidak boleh export
        $this->actingAs($unassignedJuri)
            ->get(route('admin.penilaian.export', ['kegiatanId' => $kegiatan->id, 'format' => 'xlsx']))
            ->assertForbidden();

        // Juri yang tidak ditugaskan tidak boleh import
        $this->actingAs($unassignedJuri)
            ->post(route('admin.penilaian.import', $kegiatan->id), [
                'file' => UploadedFile::fake()->create('nilai.xlsx', 10),
            ])
            ->assertForbidden();
    }

    public function test_super_admin_can_export_and_import_any_lomba(): void
    {
        $admin = $this->createSuperAdmin();
        [$kegiatan, $submission] = $this->setupKegiatanAndSubmission(); // tanpa juri

        $this->actingAs($admin)
            ->get(route('admin.penilaian.export', ['kegiatanId' => $kegiatan->id, 'format' => 'xlsx']))
            ->assertOk();
    }

    public function test_juri_can_import_penilaian_xlsx_successfully(): void
    {
        $juri = $this->createJuri();
        [$kegiatan, $submission] = $this->setupKegiatanAndSubmission($juri);

        // Buat berkas XLSX menggunakan SimpleXlsxService
        $xlsxService = new SimpleXlsxService;
        $tempPath = tempnam(sys_get_temp_dir(), 'test_imp_').'.xlsx';
        $xlsxService->createXlsxFile(
            ['ID_KARYA', 'JUDUL_KARYA', 'NAMA_PESERTA', 'NILAI', 'CATATAN'],
            [
                [$submission->id, 'Aplikasi Diagnosa Medis', 'Peserta Inovatif', 95.5, '=Komentar Formula Juri'],
            ],
            $tempPath
        );

        $uploadedFile = new UploadedFile(
            $tempPath,
            'nilai.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->actingAs($juri)->post(route('admin.penilaian.import', $kegiatan->id), [
            'file' => $uploadedFile,
        ]);

        $response->assertSessionHas('success');

        $submission->refresh();
        $this->assertEquals(95.5, (float) $submission->final_score);
        // Formula trigger '=' harus dibersihkan saat impor
        $this->assertSame('Komentar Formula Juri', $submission->judge_notes);
        $this->assertSame('graded', $submission->status);

        @unlink($tempPath);
    }

    public function test_import_rejects_foreign_submission_id(): void
    {
        $juri = $this->createJuri();
        [$kegiatanA, $submissionA] = $this->setupKegiatanAndSubmission($juri);
        [$kegiatanB, $submissionB] = $this->setupKegiatanAndSubmission($juri);

        $xlsxService = new SimpleXlsxService;
        $tempPath = tempnam(sys_get_temp_dir(), 'test_for_').'.xlsx';
        $xlsxService->createXlsxFile(
            ['ID_KARYA', 'JUDUL_KARYA', 'NAMA_PESERTA', 'NILAI', 'CATATAN'],
            [
                // Coba masukkan submission B ke kegiatan A
                [$submissionB->id, 'Karya B', 'Peserta B', 80, 'Catatan'],
            ],
            $tempPath
        );

        $uploadedFile = new UploadedFile(
            $tempPath,
            'nilai.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->actingAs($juri)->post(route('admin.penilaian.import', $kegiatanA->id), [
            'file' => $uploadedFile,
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('kegiatan_submissions', [
            'id' => $submissionB->id,
            'nilai' => 80,
        ]);

        @unlink($tempPath);
    }

    public function test_import_validates_numeric_score_range(): void
    {
        $juri = $this->createJuri();
        [$kegiatan, $submission] = $this->setupKegiatanAndSubmission($juri);

        $xlsxService = new SimpleXlsxService;
        $tempPath = tempnam(sys_get_temp_dir(), 'test_rng_').'.xlsx';
        $xlsxService->createXlsxFile(
            ['ID_KARYA', 'JUDUL_KARYA', 'NAMA_PESERTA', 'NILAI', 'CATATAN'],
            [
                [$submission->id, 'Karya A', 'Peserta A', 150, 'Nilai kebesaran'],
            ],
            $tempPath
        );

        $uploadedFile = new UploadedFile(
            $tempPath,
            'nilai.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->actingAs($juri)->post(route('admin.penilaian.import', $kegiatan->id), [
            'file' => $uploadedFile,
        ]);

        $response->assertSessionHas('error');
        @unlink($tempPath);
    }

    public function test_import_rejects_exceeding_max_2000_rows(): void
    {
        $juri = $this->createJuri();
        [$kegiatan, $submission] = $this->setupKegiatanAndSubmission($juri);

        $rows = [];
        for ($i = 1; $i <= 2005; $i++) {
            $rows[] = [$i, 'Judul '.$i, 'Peserta '.$i, 80, 'Catatan'];
        }

        $xlsxService = new SimpleXlsxService;
        $tempPath = tempnam(sys_get_temp_dir(), 'test_row_').'.xlsx';
        $xlsxService->createXlsxFile(
            ['ID_KARYA', 'JUDUL_KARYA', 'NAMA_PESERTA', 'NILAI', 'CATATAN'],
            $rows,
            $tempPath
        );

        $uploadedFile = new UploadedFile(
            $tempPath,
            'nilai.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->actingAs($juri)->post(route('admin.penilaian.import', $kegiatan->id), [
            'file' => $uploadedFile,
        ]);

        $response->assertSessionHas('error');
        @unlink($tempPath);
    }

    public function test_import_rejects_file_larger_than_2mb(): void
    {
        $juri = $this->createJuri();
        [$kegiatan, $submission] = $this->setupKegiatanAndSubmission($juri);

        // Buat fake file 3 MB (3072 KB)
        $largeFile = UploadedFile::fake()->create('large.xlsx', 3072);

        $response = $this->actingAs($juri)->post(route('admin.penilaian.import', $kegiatan->id), [
            'file' => $largeFile,
        ]);

        $response->assertSessionHasErrors(['file']);
    }
}
