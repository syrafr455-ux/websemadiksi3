<?php

namespace Tests\Feature;

use App\Enums\JenisSertifikat;
use App\Enums\KegiatanStatus;
use App\Enums\PeranSertifikat;
use App\Enums\StatusSertifikat;
use App\Models\Kegiatan;
use App\Models\PengaturanVerifikasi;
use App\Models\Sertifikat;
use App\Models\SertifikatRiwayatPerubahan;
use App\Models\User;
use App\Support\Masking;
use App\Support\Wib;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VerifikasiSertifikatDinamisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-27 10:00:00');
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function buatKegiatan(array $overrides = []): Kegiatan
    {
        return Kegiatan::create(array_merge([
            'nama_kegiatan' => 'Lomba Karya Tulis Ilmiah Nasional 2026',
            'deskripsi' => 'Deskripsi kegiatan kompetisi',
            'kategori' => 'lomba',
            'metode_pelaksanaan' => 'offline',
            'sasaran_peserta' => 'umum',
            'tanggal_mulai' => '2026-10-20 08:00:00',
            'tanggal_selesai' => '2026-10-20 16:00:00',
            'lokasi_type' => 'unusa_campus',
            'lokasi_nama' => 'Auditorium Tower Lantai 9',
            'kuota' => 100,
            'biaya' => 0,
            'penyelenggara' => 'Himpunan Mahasiswa KIP-K UNUSA',
            'file_panduan_pdf' => 'kegiatan/panduan.pdf',
            'status' => KegiatanStatus::Published,
            'is_published' => true,
        ], $overrides));
    }

    private function buatSertifikat(array $attributes): Sertifikat
    {
        return Sertifikat::create(array_merge([
            'file_pdf_path' => 'certificates/dummy.pdf',
            'status' => StatusSertifikat::Aktif,
            'issued_at' => now(),
            'peran' => PeranSertifikat::Peserta,
        ], $attributes));
    }

    public function test_dynamic_badges_for_all_roles_and_predicates(): void
    {
        $kegiatan = $this->buatKegiatan();

        // 1. Juara dengan predikat
        $sertifikatJuara = $this->buatSertifikat([
            'kegiatan_id' => $kegiatan->id,
            'nomor_sertifikat' => 'CERT-LOMBA-2026-0001',
            'nama_penerima' => 'Ahmad Juara Nasional',
            'jenis' => JenisSertifikat::Juara,
            'predikat' => 'Juara 1',
            'asal_institusi' => 'Institut Teknologi Sepuluh Nopember',
            'nim_penerima' => '5025201001',
            'document_hash' => hash('sha256', 'CERT-LOMBA-2026-0001'),
        ]);

        $response = $this->get(route('verifikasi.sertifikat', ['code' => 'CERT-LOMBA-2026-0001']));
        $response->assertOk()
            ->assertSee('Ahmad Juara Nasional')
            ->assertSee('Juara')
            ->assertSee('Juara 1')
            ->assertSee('Institut Teknologi Sepuluh Nopember')
            ->assertSee(Masking::nim('5025201001'))
            ->assertSee('SAH')
            ->assertSee('Lomba Karya Tulis Ilmiah Nasional 2026')
            ->assertSee('Divisi Penyelenggara / SEMADIKSI');

        // Pastikan NIM utuh tidak bocor
        $response->assertDontSee('5025201001');

        // 2. Juri
        $sertifikatJuri = $this->buatSertifikat([
            'kegiatan_id' => $kegiatan->id,
            'nomor_sertifikat' => 'CERT-LOMBA-2026-0002',
            'nama_penerima' => 'Dr. Hendra Gunawan',
            'jenis' => JenisSertifikat::Juri,
            'asal_institusi' => 'Universitas Indonesia',
            'document_hash' => hash('sha256', 'CERT-LOMBA-2026-0002'),
            'peran' => PeranSertifikat::Panitia,
        ]);

        $this->get(route('verifikasi.sertifikat', ['code' => 'CERT-LOMBA-2026-0002']))
            ->assertOk()
            ->assertSee('Dr. Hendra Gunawan')
            ->assertSee('Juri')
            ->assertSee('Universitas Indonesia');

        // 3. Pembicara
        $sertifikatSpeaker = $this->buatSertifikat([
            'kegiatan_id' => $kegiatan->id,
            'nomor_sertifikat' => 'CERT-LOMBA-2026-0003',
            'nama_penerima' => 'Prof. Siti Maryam',
            'jenis' => JenisSertifikat::Pembicara,
            'predikat' => 'Keynote Speaker',
            'asal_institusi' => 'Universitas Gadjah Mada',
            'document_hash' => hash('sha256', 'CERT-LOMBA-2026-0003'),
            'peran' => PeranSertifikat::Narasumber,
        ]);

        $this->get(route('verifikasi.sertifikat', ['code' => 'CERT-LOMBA-2026-0003']))
            ->assertOk()
            ->assertSee('Prof. Siti Maryam')
            ->assertSee('Pembicara')
            ->assertSee('Keynote Speaker');
    }

    public function test_revoked_certificate_displays_status_dicabut_and_mandatory_reason(): void
    {
        $kegiatan = $this->buatKegiatan();

        $sertifikat = $this->buatSertifikat([
            'kegiatan_id' => $kegiatan->id,
            'nomor_sertifikat' => 'CERT-REVOKED-2026-0099',
            'nama_penerima' => 'Budi Santoso',
            'jenis' => JenisSertifikat::Peserta,
            'status' => StatusSertifikat::Dicabut,
            'alasan_pencabutan' => 'Plagiarisme karya terbukti setelah evaluasi komite etik.',
            'dicabut_pada' => Wib::keUtc('2026-10-25 14:00:00'),
            'document_hash' => hash('sha256', 'CERT-REVOKED-2026-0099'),
            'issued_at' => '2026-10-20 16:00:00',
        ]);

        $response = $this->get(route('verifikasi.sertifikat', ['code' => 'CERT-REVOKED-2026-0099']));
        $response->assertOk()
            ->assertSee('DICABUT')
            ->assertSee('Plagiarisme karya terbukti setelah evaluasi komite etik.')
            ->assertSee('25 Oktober 2026, 14:00 WIB')
            ->assertSee('Tidak Valid'); // Validity column in table must be Tidak Valid
    }

    public function test_real_certified_and_validity_meaning(): void
    {
        $kegiatan = $this->buatKegiatan();

        // Buat dummy PDF fisik
        $dummyContent = '%PDF-1.4 dummy certificate content';
        $path = 'certificates/test_valid.pdf';
        Storage::disk('public')->put($path, $dummyContent);
        $correctHash = hash('sha256', $dummyContent);

        // Kasus 1: Sertifikat Sah, Hash Cocok
        $certValid = $this->buatSertifikat([
            'kegiatan_id' => $kegiatan->id,
            'nomor_sertifikat' => 'CERT-REAL-2026-1111',
            'nama_penerima' => 'Rina Wijaya',
            'jenis' => JenisSertifikat::Peserta,
            'status' => StatusSertifikat::Aktif,
            'file_pdf_path' => $path,
            'document_hash' => $correctHash,
            'penandatangan_snapshot' => [
                [
                    'id' => 1,
                    'nama_lengkap' => 'Prof. Dr. Ir. Achmad Jazidie, M.Eng.',
                    'jabatan' => 'Rektor',
                    'jenis_identitas' => 'nip',
                    'nomor_identitas' => '195902191986011001',
                    'institusi' => 'Universitas Nahdlatul Ulama Surabaya',
                    'mode_tampil' => 'samarkan',
                ],
            ],
        ]);

        $res1 = $this->get(route('verifikasi.sertifikat', ['code' => 'CERT-REAL-2026-1111']));
        $res1->assertOk()
            ->assertSee('Prof. Dr. Ir. Achmad Jazidie, M.Eng.')
            ->assertSee('Ya') // Certified
            ->assertSee('Valid') // Validity
            ->assertSee('1959****1001'); // Masked NIP

        // Kasus 2: Berkas PDF fisik dimanipulasi / diubah di server (Hash tidak cocok)
        Storage::disk('public')->put($path, '%PDF-1.4 TAMPERED CONTENT');

        $res2 = $this->get(route('verifikasi.sertifikat', ['code' => 'CERT-REAL-2026-1111']));
        $res2->assertOk()
            ->assertSee('Tidak Valid'); // Validity column turns into Tidak Valid
    }

    public function test_notice_is_displayed_when_display_data_was_edited(): void
    {
        $kegiatan = $this->buatKegiatan();

        $cert = $this->buatSertifikat([
            'kegiatan_id' => $kegiatan->id,
            'nomor_sertifikat' => 'CERT-EDITED-2026-2222',
            'nama_penerima' => 'Muhammad Rizky Pratama',
            'jenis' => JenisSertifikat::Peserta,
            'document_hash' => hash('sha256', 'CERT-EDITED-2026-2222'),
            'issued_at' => '2026-10-21 10:00:00',
        ]);

        $admin = User::factory()->create(['name' => 'Admin Operator']);

        // Catat riwayat perubahan
        SertifikatRiwayatPerubahan::create([
            'sertifikat_id' => $cert->id,
            'user_id' => $admin->id,
            'ip_address' => '127.0.0.1',
            'kolom_diubah' => 'nama_penerima',
            'nilai_lama' => 'M. Rizky',
            'nilai_baru' => 'Muhammad Rizky Pratama',
            'alasan' => 'Perbaikan penulisan ejaan nama lengkap sesuai KTP',
            'created_at' => Wib::keUtc('2026-10-24 15:30:00'),
        ]);

        $response = $this->get(route('verifikasi.sertifikat', ['code' => 'CERT-EDITED-2026-2222']));
        $response->assertOk()
            ->assertSee('Data diperbarui oleh panitia pada 24 Oktober 2026, 15:30 WIB');
    }

    public function test_verification_settings_toggles_hide_and_show_sections(): void
    {
        $kegiatan = $this->buatKegiatan();

        $cert = $this->buatSertifikat([
            'kegiatan_id' => $kegiatan->id,
            'nomor_sertifikat' => 'CERT-TOGGLE-2026-3333',
            'nama_penerima' => 'Dewi Lestari',
            'jenis' => JenisSertifikat::Peserta,
            'asal_institusi' => 'Kampus Merdeka Surabaya',
            'nim_penerima' => '3130021099',
            'keterangan_publik' => 'Lulus dengan nilai memuaskan pada ujian praktik.',
            'document_hash' => hash('sha256', 'CERT-TOGGLE-2026-3333'),
        ]);

        $pengaturan = PengaturanVerifikasi::ambil();

        // 1. Matikan tampilan asal institusi dan NIM
        $pengaturan->update([
            'tampilkan_asal_institusi' => false,
            'tampilkan_nim_samar' => false,
            'tampilkan_keterangan' => false,
            'tampilkan_tabel_penandatangan' => false,
            'teks_pengantar' => 'Pengumuman verifikasi khusus pengujian sistem.',
        ]);

        $response = $this->get(route('verifikasi.sertifikat', ['code' => 'CERT-TOGGLE-2026-3333']));
        $response->assertOk()
            ->assertSee('Pengumuman verifikasi khusus pengujian sistem.')
            ->assertDontSee('Kampus Merdeka Surabaya')
            ->assertDontSee(Masking::nim('3130021099'))
            ->assertDontSee('Lulus dengan nilai memuaskan')
            ->assertDontSee('Informasi Penandatanganan (TTE)');

        // 2. Aktifkan kembali
        $pengaturan->update([
            'tampilkan_asal_institusi' => true,
            'tampilkan_nim_samar' => true,
            'tampilkan_keterangan' => true,
            'tampilkan_tabel_penandatangan' => true,
        ]);

        $res2 = $this->get(route('verifikasi.sertifikat', ['code' => 'CERT-TOGGLE-2026-3333']));
        $res2->assertOk()
            ->assertSee('Kampus Merdeka Surabaya')
            ->assertSee(Masking::nim('3130021099'))
            ->assertSee('Lulus dengan nilai memuaskan')
            ->assertSee('Informasi Penandatanganan (TTE)');
    }

    public function test_no_internal_pii_leak_in_verification_page(): void
    {
        $kegiatan = $this->buatKegiatan();
        $user = User::factory()->create([
            'name' => 'Peserta Rahasia',
            'email' => 'peserta.rahasia@student.unusa.ac.id',
            'nim' => '3130021077',
        ]);

        $cert = $this->buatSertifikat([
            'kegiatan_id' => $kegiatan->id,
            'user_id' => $user->id,
            'nomor_sertifikat' => 'CERT-PII-2026-4444',
            'nama_penerima' => $user->name,
            'email_penerima' => $user->email,
            'nim_penerima' => $user->nim,
            'jenis' => JenisSertifikat::Peserta,
            'document_hash' => hash('sha256', 'CERT-PII-2026-4444'),
        ]);

        $response = $this->get(route('verifikasi.sertifikat', ['code' => 'CERT-PII-2026-4444']));
        $response->assertOk()
            ->assertSee('Peserta Rahasia')
            ->assertDontSee('peserta.rahasia@student.unusa.ac.id') // Email internal tidak boleh bocor
            ->assertDontSee('user_id')
            ->assertDontSee('"user_id":'.$user->id);
    }

    public function test_legacy_code_cert_asyk_2026_8280_remains_valid(): void
    {
        $kegiatan = $this->buatKegiatan(['nama_kegiatan' => 'Temu Akbar KIP-K UNUSA']);

        $sertifikatLama = $this->buatSertifikat([
            'kegiatan_id' => $kegiatan->id,
            'nomor_sertifikat' => 'CERT-ASYK-2026-8280',
            'nama_penerima' => 'Alumni KIP-K Hebat',
            'jenis' => JenisSertifikat::Peserta,
            'document_hash' => '8280c805ba07b8c6869e1f37847d0287a9b1c2d3e4f5a6b7c8d9e0f1a2b3c4d5',
            'issued_at' => '2026-08-15 10:00:00',
        ]);

        $response = $this->get(route('verifikasi.sertifikat', ['code' => 'CERT-ASYK-2026-8280']));
        $response->assertOk()
            ->assertSee('CERT-ASYK-2026-8280')
            ->assertSee('Alumni KIP-K Hebat')
            ->assertSee('SAH')
            ->assertSee('VALID');
    }
}
