<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\KipkDocument;
use App\Models\ProgramStudi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    protected function createSuperAdmin(): User
    {
        return User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'is_blocked' => false,
        ]);
    }

    protected function createVerifikatorBerkas(): User
    {
        return User::factory()->create([
            'role' => UserRole::VerifikatorBerkas,
            'is_blocked' => false,
        ]);
    }

    protected function createPenilaiKarya(): User
    {
        return User::factory()->create([
            'role' => UserRole::PenilaiKarya,
            'is_blocked' => false,
        ]);
    }

    protected function createMahasiswaKipk(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => UserRole::MahasiswaKipk,
            'is_blocked' => false,
        ], $attributes));
    }

    protected function createUserUmum(): User
    {
        return User::factory()->create([
            'role' => UserRole::UserUmum,
            'is_blocked' => false,
        ]);
    }

    /**
     * 1. Super Admin memiliki kontrol penuh ke seluruh modul /admin
     */
    public function test_super_admin_can_access_all_admin_modules(): void
    {
        $admin = $this->createSuperAdmin();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('admin.kegiatan'))->assertOk();
        $this->actingAs($admin)->get(route('admin.users'))->assertOk();
        $this->actingAs($admin)->get(route('admin.berkas'))->assertOk();
        $this->actingAs($admin)->get(route('admin.submissions'))->assertOk();
        $this->actingAs($admin)->get(route('admin.penilaian'))->assertOk();
    }

    /**
     * 2. Verifikator Berkas HANYA boleh mengakses modul berkas & submissions
     */
    public function test_verifikator_berkas_can_access_berkas_and_submissions(): void
    {
        $verifikator = $this->createVerifikatorBerkas();

        $this->actingAs($verifikator)->get(route('admin.berkas'))->assertOk();
        $this->actingAs($verifikator)->get(route('admin.submissions'))->assertOk();
    }

    /**
     * 3. Verifikator Berkas ditolak dari kegiatan, pengguna, penilaian, dan di-redirect ke admin.berkas tanpa loop
     */
    public function test_verifikator_berkas_redirected_to_berkas_from_unauthorized_routes_without_loop(): void
    {
        $verifikator = $this->createVerifikatorBerkas();

        // Mengakses kegiatan -> redirect ke admin.berkas
        $responseKegiatan = $this->actingAs($verifikator)->get(route('admin.kegiatan'));
        $responseKegiatan->assertRedirect(route('admin.berkas'));

        // Mengakses pengguna -> redirect ke admin.berkas
        $responseUsers = $this->actingAs($verifikator)->get(route('admin.users'));
        $responseUsers->assertRedirect(route('admin.berkas'));

        // Mengakses penilaian karya -> redirect ke admin.berkas
        $responsePenilaian = $this->actingAs($verifikator)->get(route('admin.penilaian'));
        $responsePenilaian->assertRedirect(route('admin.berkas'));

        // Mengakses dashboard overview -> redirect ke admin.berkas
        $responseDashboard = $this->actingAs($verifikator)->get(route('admin.dashboard'));
        $responseDashboard->assertRedirect(route('admin.berkas'));

        // Halaman tujuan (admin.berkas) dapat diakses dengan sukses (200 OK) sehingga TIDAK TERJADI LOOP
        $this->actingAs($verifikator)->get(route('admin.berkas'))->assertOk();
    }

    /**
     * 4. Penilai Karya HANYA boleh mengakses modul penilaian lomba
     */
    public function test_penilai_karya_can_access_penilaian(): void
    {
        $penilai = $this->createPenilaiKarya();

        $this->actingAs($penilai)->get(route('admin.penilaian'))->assertOk();
    }

    /**
     * 5. Penilai Karya ditolak dari berkas, submissions, kegiatan, pengguna, dan di-redirect ke admin.penilaian tanpa loop
     */
    public function test_penilai_karya_redirected_to_penilaian_from_unauthorized_routes_without_loop(): void
    {
        $penilai = $this->createPenilaiKarya();

        // Mengakses berkas KIP-K -> redirect ke admin.penilaian
        $responseBerkas = $this->actingAs($penilai)->get(route('admin.berkas'));
        $responseBerkas->assertRedirect(route('admin.penilaian'));

        // Mengakses submissions -> redirect ke admin.penilaian
        $responseSubmissions = $this->actingAs($penilai)->get(route('admin.submissions'));
        $responseSubmissions->assertRedirect(route('admin.penilaian'));

        // Mengakses kegiatan -> redirect ke admin.penilaian
        $responseKegiatan = $this->actingAs($penilai)->get(route('admin.kegiatan'));
        $responseKegiatan->assertRedirect(route('admin.penilaian'));

        // Mengakses pengguna -> redirect ke admin.penilaian
        $responseUsers = $this->actingAs($penilai)->get(route('admin.users'));
        $responseUsers->assertRedirect(route('admin.penilaian'));

        // Mengakses dashboard overview -> redirect ke admin.penilaian
        $responseDashboard = $this->actingAs($penilai)->get(route('admin.dashboard'));
        $responseDashboard->assertRedirect(route('admin.penilaian'));

        // Halaman tujuan (admin.penilaian) dapat diakses dengan sukses (200 OK) sehingga TIDAK TERJADI LOOP
        $this->actingAs($penilai)->get(route('admin.penilaian'))->assertOk();
    }

    /**
     * 6. Mahasiswa KIP-K dan User Umum ditolak 100% dari seluruh panel admin
     */
    public function test_students_and_public_users_cannot_access_any_admin_routes(): void
    {
        $mahasiswa = $this->createMahasiswaKipk();
        $userUmum = $this->createUserUmum();

        $adminRoutes = [
            route('admin.dashboard'),
            route('admin.kegiatan'),
            route('admin.users'),
            route('admin.berkas'),
            route('admin.submissions'),
            route('admin.penilaian'),
        ];

        foreach ($adminRoutes as $adminRoute) {
            $this->actingAs($mahasiswa)->get($adminRoute)->assertRedirect(route('admin.login'));
            $this->actingAs($userUmum)->get($adminRoute)->assertRedirect(route('admin.login'));
        }
    }

    /**
     * 7. Request JSON / Inertia ke rute yang tidak berhak mengembalikan status 403 Forbidden
     */
    public function test_ajax_or_inertia_unauthorized_receives_403_json(): void
    {
        $penilai = $this->createPenilaiKarya();
        $verifikator = $this->createVerifikatorBerkas();

        // Penilai karya mencoba mutasi kegiatan via JSON
        $response = $this->actingAs($penilai)
            ->postJson(route('admin.kegiatan.store'), [
                'nama_kegiatan' => 'Workshop Ilegal',
            ]);
        $response->assertStatus(403);

        // Verifikator berkas mencoba import CSV penilaian via JSON
        $response = $this->actingAs($verifikator)
            ->postJson(route('admin.penilaian.score', ['id' => 999]), [
                'score' => 90,
            ]);
        $response->assertStatus(403);
    }

    /**
     * 8. KipkDocumentPolicy: Mahasiswa pemilik dokumen KIP-K boleh melihat dan mengunduh berkasnya sendiri
     */
    public function test_kipk_document_policy_allows_owner_to_view_and_download(): void
    {
        $owner = $this->createMahasiswaKipk(['name' => 'Pemilik Berkas']);
        $doc = KipkDocument::create([
            'user_id' => $owner->id,
            'user_name' => $owner->name,
            'category' => 'Prestasi',
            'tahap' => 1,
            'title' => 'Sertifikat Lomba Nasional',
            'file_name' => 'prestasi.pdf',
            'file_url' => '/storage/kipk_documents/prestasi.pdf',
            'status' => 'Menunggu Review',
        ]);

        $this->assertTrue(Gate::forUser($owner)->allows('view', $doc));
        $this->assertTrue(Gate::forUser($owner)->allows('download', $doc));

        // Endpoint download berkas mahasiswa
        $response = $this->actingAs($owner)->get(route('dashboard.berkas.download', $doc->id));
        $this->assertNotEquals(403, $response->getStatusCode());
    }

    /**
     * 9. KipkDocumentPolicy: Mahasiswa lain dilarang melihat dan mengunduh berkas milik mahasiswa lain
     */
    public function test_kipk_document_policy_denies_other_student_from_view_and_download(): void
    {
        $owner = $this->createMahasiswaKipk(['name' => 'Mahasiswa A']);
        $otherStudent = $this->createMahasiswaKipk(['name' => 'Mahasiswa B']);

        $doc = KipkDocument::create([
            'user_id' => $owner->id,
            'user_name' => $owner->name,
            'category' => 'Ekonomi',
            'tahap' => 1,
            'title' => 'Surat Keterangan Tidak Mampu',
            'file_name' => 'sktm.pdf',
            'file_url' => '/storage/kipk_documents/sktm.pdf',
            'status' => 'Menunggu Review',
        ]);

        $this->assertFalse(Gate::forUser($otherStudent)->allows('view', $doc));
        $this->assertFalse(Gate::forUser($otherStudent)->allows('download', $doc));

        // Akses via endpoint download oleh mahasiswa lain harus 403 Forbidden
        $response = $this->actingAs($otherStudent)->get(route('dashboard.berkas.download', $doc->id));
        $response->assertStatus(403);
    }

    /**
     * 10. KipkDocumentPolicy: Verifikator berkas dan Super admin boleh melihat, mengunduh, dan memverifikasi berkas
     */
    public function test_kipk_document_policy_allows_verifikator_and_super_admin_to_verify_and_download(): void
    {
        // Verifikator hanya memproses berkas mahasiswa dalam cakupannya: Admin Prodi Gizi + mahasiswa Gizi.
        $prodi = ProgramStudi::where('kode', 'FKES-S1-GIZI')->firstOrFail();
        $verifikator = User::factory()->create([
            'role' => UserRole::VerifikatorBerkas,
            'is_blocked' => false,
            'verifikator_level' => 'prodi',
            'program_studi_id' => $prodi->id,
            'fakultas_id' => $prodi->fakultas_id,
        ]);
        $admin = $this->createSuperAdmin();
        $student = $this->createMahasiswaKipk(['program_studi_id' => $prodi->id, 'fakultas_id' => $prodi->fakultas_id]);

        $doc = KipkDocument::create([
            'user_id' => $student->id,
            'user_name' => $student->name,
            'category' => 'Akademik',
            'tahap' => 1,
            'title' => 'Transkrip Nilai Semester 3',
            'file_name' => 'transkrip.pdf',
            'file_url' => '/storage/kipk_documents/transkrip.pdf',
            'status' => 'Menunggu Review',
        ]);

        // Verifikator berkas
        $this->assertTrue(Gate::forUser($verifikator)->allows('view', $doc));
        $this->assertTrue(Gate::forUser($verifikator)->allows('download', $doc));
        $this->assertTrue(Gate::forUser($verifikator)->allows('verify', $doc));

        // Super Admin
        $this->assertTrue(Gate::forUser($admin)->allows('view', $doc));
        $this->assertTrue(Gate::forUser($admin)->allows('download', $doc));
        $this->assertTrue(Gate::forUser($admin)->allows('verify', $doc));

        // Verifikator berkas berhasil update verifikasi via endpoint admin
        $response = $this->actingAs($verifikator)->post(route('admin.berkas.update', $doc->id), [
            'status' => 'Disetujui',
            'score' => 95,
            'notes' => 'Berkas valid dan lengkap.',
        ]);
        $response->assertSessionHas('success');

        $doc->refresh();
        $this->assertSame('Disetujui', $doc->status);
        $this->assertSame(95, $doc->score);
    }

    /**
     * 11. KipkDocumentPolicy: Penilai karya (juri) dilarang melihat, mengunduh, atau memverifikasi berkas KIP-K
     */
    public function test_kipk_document_policy_denies_penilai_karya_from_viewing_or_verifying_kipk_document(): void
    {
        $penilai = $this->createPenilaiKarya();
        $student = $this->createMahasiswaKipk();

        $doc = KipkDocument::create([
            'user_id' => $student->id,
            'user_name' => $student->name,
            'category' => 'Akademik',
            'tahap' => 1,
            'title' => 'KHS Semester 2',
            'file_name' => 'khs.pdf',
            'file_url' => '/storage/kipk_documents/khs.pdf',
            'status' => 'Menunggu Review',
        ]);

        $this->assertFalse(Gate::forUser($penilai)->allows('view', $doc));
        $this->assertFalse(Gate::forUser($penilai)->allows('download', $doc));
        $this->assertFalse(Gate::forUser($penilai)->allows('verify', $doc));
    }
}
