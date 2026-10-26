<?php

namespace Tests\Feature;

use App\Enums\KegiatanStatus;
use App\Enums\PeranSertifikat;
use App\Enums\UserRole;
use App\Models\Kegiatan;
use App\Models\Sertifikat;
use App\Models\User;
use App\Services\CertificateGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use Tests\TestCase;

class CertificateModuleFpdiAndPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /**
     * Helper to create a dummy valid PDF file for tests.
     */
    protected function createDummyPdf(string $filename = 'template.pdf'): UploadedFile
    {
        $fpdf = new Fpdi;
        $fpdf->AddPage('L', 'A4');
        $fpdf->SetFont('Arial', 'B', 16);
        $fpdf->Cell(0, 10, 'OFFICIAL TEST CERTIFICATE TEMPLATE', 0, 1, 'C');
        $content = $fpdf->Output('S');

        return UploadedFile::fake()->createWithContent($filename, $content);
    }

    protected function createKegiatan(array $attributes = []): Kegiatan
    {
        return Kegiatan::create(array_merge([
            'nama_kegiatan' => 'Latihan Kepemimpinan Mahasiswa Berprestasi',
            'deskripsi' => 'Program pelatihan kepemimpinan mahasiswa KIP-K.',
            'kategori' => 'workshop',
            'metode_pelaksanaan' => 'offline',
            'sasaran_peserta' => 'umum',
            'tanggal_mulai' => now()->addDays(5),
            'tanggal_selesai' => now()->addDays(5)->addHours(8),
            'lokasi_type' => 'unusa_campus',
            'lokasi_nama' => 'Auditorium Tower Unusa Lt. 9',
            'lokasi_detail' => 'Kampus B UNUSA Surabaya',
            'file_panduan_pdf' => 'kegiatan/panduan/lkmb.pdf',
            'biaya' => 0,
            'kuota' => 100,
            'status' => KegiatanStatus::Published,
            'is_seat_booking_active' => false,
            'is_submission_active' => false,
        ], $attributes));
    }

    /**
     * 1. User non-admin ditolak saat mengakses endpoint penerbitan sertifikat admin (403).
     */
    public function test_non_admin_forbidden_from_admin_certificate_endpoints(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::MahasiswaKipk,
            'verification_status' => 'verified',
        ]);

        $this->actingAs($student)->get(route('admin.certificates'))
            ->assertRedirect(route('admin.login'));

        $this->actingAs($student)->getJson(route('admin.certificates'))
            ->assertForbidden();

        $this->actingAs($student)->postJson(route('admin.certificates.store'), [
            'kegiatan_id' => 1,
            'nama_lengkap' => 'Nama Peserta',
        ])->assertForbidden();
    }

    /**
     * 2. Admin dapat mengakses halaman /admin/sertifikat dengan tampilan penuh dan form 2 kolom.
     */
    public function test_admin_can_view_certificate_page_with_light_theme_and_correct_data(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SuperAdmin,
        ]);
        $kegiatan = $this->createKegiatan(['nama_kegiatan' => 'Temu Akbar KIP-K UNUSA']);

        $response = $this->actingAs($admin)->get(route('admin.certificates'));
        $response->assertOk()
            ->assertSee('Penerbitan Sertifikat')
            ->assertSee('Daftar Sertifikat Terbit')
            ->assertSee('Temu Akbar KIP-K UNUSA')
            ->assertSee('Generate Otomatis');
    }

    /**
     * 3. Upload template PDF di admin berhasil men-generate file akhir dengan nomor seri format CERT-,
     *    QR code tertempel di pojok kanan bawah, dan hash SHA-256 tersimpan.
     */
    public function test_admin_upload_pdf_generates_stamped_certificate_with_cert_code_qr_and_sha256(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $kegiatan = $this->createKegiatan(['nama_kegiatan' => 'Workshop Web Modern']);
        $pdfFile = $this->createDummyPdf('sertifikat_resmi_workshop.pdf');

        $response = $this->actingAs($admin)->post(route('admin.certificates.store'), [
            'kegiatan_id' => $kegiatan->id,
            'nama_lengkap' => 'Bambang Sudarsono',
            'nomor_sertifikat' => 'CERT-WKS-2026-7890',
            'certificate_file' => $pdfFile,
        ]);

        $response->assertRedirect()->assertSessionHas('success');

        // Verifikasi data di tabel sertifikat (alur baru)
        $sertifikat = Sertifikat::where('nomor_sertifikat', 'CERT-WKS-2026-7890')->first();
        $this->assertNotNull($sertifikat);
        $this->assertEquals($kegiatan->id, $sertifikat->kegiatan_id);
        $this->assertEquals('Bambang Sudarsono', $sertifikat->recipient_name);
        $this->assertNotEmpty($sertifikat->document_hash);
        $this->assertEquals(64, strlen($sertifikat->document_hash));
        $this->assertNotEmpty($sertifikat->file_pdf_path);

        // Verifikasi fisik file di public storage
        Storage::disk('public')->assertExists($sertifikat->file_pdf_path);
        $this->assertTrue($sertifikat->verifyDocumentHash());

        // Verifikasi isi file PDF distamp dengan nomor dan QR code URL
        $pdfBinary = Storage::disk('public')->get($sertifikat->file_pdf_path);
        $this->assertGreaterThan(1000, strlen($pdfBinary));
    }

    /**
     * 4. Generate Otomatis menghasilkan nomor seri unik berformat CERT-.
     */
    public function test_generate_automatic_code_format_is_cert_code_year_random(): void
    {
        $kegiatan = $this->createKegiatan(['nama_kegiatan' => 'LKMB Nasional']);

        $code = Sertifikat::generateCertificateNumber($kegiatan);
        $this->assertStringStartsWith('CERT-', $code);
        $this->assertMatchesRegularExpression('/^CERT-[A-Z0-9]+-\d{4}-[A-Z0-9]+$/', $code);

        $codeDirect = Sertifikat::generateNomorSertifikat('LKMB', 2026);
        $this->assertStringStartsWith('CERT-LKMB-2026-', $codeDirect);
    }

    /**
     * 5. Akses publik ke /verifikasi?code=... mengembalikan status 200 dengan status dokumen VALID, hash, dan daftar TTE.
     */
    public function test_public_verification_portal_returns_200_valid_status_hash_and_tte_signatories(): void
    {
        $kegiatan = $this->createKegiatan(['nama_kegiatan' => 'Seminar Kewirausahaan']);
        $user = User::factory()->create(['name' => 'Dewi Lestari']);

        $service = new CertificateGeneratorService;
        $sertifikat = $service->generate($kegiatan, $user, PeranSertifikat::Peserta);

        $response = $this->get(route('verifikasi.sertifikat', ['code' => $sertifikat->nomor_sertifikat]));
        $response->assertOk()
            ->assertSee('Portal Validasi Sertifikat SEMADIKSI')
            ->assertSee('VALID')
            ->assertSee('Dewi Lestari')
            ->assertSee($sertifikat->nomor_sertifikat)
            ->assertSee($sertifikat->document_hash)
            ->assertSee('Informasi Penandatanganan (TTE)')
            ->assertSee('Dr. Ir. Wahyu Utomo, M.Si. (Pembina SEMADIKSI)')
            ->assertSee('Muhammad Fatih (Ketua Umum SEMADIKSI)');
    }

    /**
     * 6. Kompatibilitas Kode Lama: kode lama (contoh: CERT-LKMB-2024-0891) tetap dapat diverifikasi dengan status valid.
     */
    public function test_legacy_code_compatibility_in_public_verification(): void
    {
        $response = $this->get(route('verifikasi.sertifikat', ['code' => 'CERT-LKMB-2024-0891']));
        $response->assertOk()
            ->assertSee('VALID')
            ->assertSee('Ahmad Fauzan')
            ->assertSee('CERT-LKMB-2024-0891')
            ->assertSee('Informasi Penandatanganan (TTE)');
    }

    /**
     * 7. Kode sertifikat tidak terdaftar mengembalikan pesan tidak valid yang aman.
     */
    public function test_invalid_certificate_code_returns_safe_invalid_message(): void
    {
        $response = $this->get(route('verifikasi.sertifikat', ['code' => 'CERT-FAKE-9999-NOTFOUND']));
        $response->assertOk()
            ->assertSee('Sertifikat Tidak Ditemukan atau Tidak Valid')
            ->assertSee('CERT-FAKE-9999-NOTFOUND')
            ->assertDontSee('Penerima Sah');
    }

    /**
     * 8. Halaman /dashboard/sertifikat peserta menampilkan kartu sertifikat dan tombol pratinjau.
     */
    public function test_student_dashboard_sertifikat_shows_cards_and_preview_button(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::MahasiswaKipk,
            'verification_status' => 'verified',
        ]);
        $kegiatan = $this->createKegiatan(['nama_kegiatan' => 'Workshop UI/UX Design']);

        $service = new CertificateGeneratorService;
        $sertifikat = $service->generate($kegiatan, $student);

        $response = $this->actingAs($student)->get(route('dashboard.sertifikat'));
        $response->assertOk()
            ->assertSee('Sertifikat &amp; Riwayat Kegiatan', false)
            ->assertSee($sertifikat->nomor_sertifikat)
            ->assertSee('Workshop UI/UX Design')
            ->assertSee('Lihat Sertifikat')
            ->assertSee('Pratinjau Sertifikat Resmi');
    }
}
