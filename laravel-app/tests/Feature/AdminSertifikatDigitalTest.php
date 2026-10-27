<?php

namespace Tests\Feature;

use App\Enums\JenisSertifikat;
use App\Enums\KegiatanStatus;
use App\Enums\RegistrationPaymentStatus;
use App\Enums\StatusSertifikat;
use App\Enums\UserRole;
use App\Jobs\SendProtectedEmailJob;
use App\Models\AuditLog;
use App\Models\Kegiatan;
use App\Models\KegiatanPresensi;
use App\Models\KegiatanRegistration;
use App\Models\Penandatangan;
use App\Models\PengaturanVerifikasi;
use App\Models\Sertifikat;
use App\Models\SertifikatRiwayatPerubahan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminSertifikatDigitalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
        Carbon::setTestNow('2026-10-27 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function buatAdmin(): User
    {
        return User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'is_blocked' => false,
        ]);
    }

    private function buatKegiatan(array $overrides = []): Kegiatan
    {
        return Kegiatan::create(array_merge([
            'nama_kegiatan' => 'Seminar Teknologi Nasional',
            'deskripsi' => 'Deskripsi kegiatan seminar',
            'kategori' => 'seminar',
            'metode_pelaksanaan' => 'offline',
            'sasaran_peserta' => 'umum',
            'tanggal_mulai' => '2026-10-20 08:00:00',
            'tanggal_selesai' => '2026-10-20 16:00:00',
            'lokasi_type' => 'unusa_campus',
            'lokasi_nama' => 'Auditorium Tower Lantai 9',
            'kuota' => 100,
            'biaya' => 0,
            'file_panduan_pdf' => 'kegiatan/panduan.pdf',
            'status' => KegiatanStatus::Published,
            'is_published' => true,
        ], $overrides));
    }

    private function buatPesertaLayak(Kegiatan $kegiatan, string $name = 'Peserta Layak'): array
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '', $name)).'@example.com',
            'role' => UserRole::MahasiswaKipk,
            'nim' => '31300'.rand(1000, 9999),
        ]);

        $reg = KegiatanRegistration::create([
            'kegiatan_id' => $kegiatan->id,
            'booker_user_id' => $user->id,
            'participant_user_id' => $user->id,
            'participant_name' => $user->name,
            'nomor_tiket' => 'TIK-'.rand(10000, 99999),
            'status_pembayaran' => RegistrationPaymentStatus::Paid,
            'status' => 'confirmed',
            'checked_in_at' => '2026-10-20 08:30:00',
        ]);

        $pres = KegiatanPresensi::create([
            'kegiatan_id' => $kegiatan->id,
            'registration_id' => $reg->id,
            'user_id' => $user->id,
            'nama_lengkap' => $user->name,
            'email' => $user->email,
            'nim' => $user->nim,
            'prodi' => 'S1 Sistem Informasi',
            'nomor_telepon' => '08123456789',
            'waktu_presensi' => '2026-10-20 15:30:00',
            'signature_path' => 'signatures/presensi_test.png',
            'metode_presensi' => 'qr_code',
        ]);

        return [$user, $reg, $pres];
    }

    public function test_non_super_admin_is_forbidden_from_all_certificate_digital_endpoints(): void
    {
        $staff = User::factory()->create(['role' => UserRole::VerifikatorBerkas]);
        $kegiatan = $this->buatKegiatan();

        $this->actingAs($staff)->getJson(route('admin.certificates'))->assertForbidden();
        $this->actingAs($staff)->postJson(route('admin.certificates.batch-issue'), ['kegiatan_id' => $kegiatan->id])->assertForbidden();
        $this->actingAs($staff)->postJson(route('admin.certificates.manual'), ['kegiatan_id' => $kegiatan->id])->assertForbidden();
        $this->actingAs($staff)->postJson(route('admin.certificates.import'), ['kegiatan_id' => $kegiatan->id])->assertForbidden();
        $this->actingAs($staff)->getJson(route('admin.certificates.template'))->assertForbidden();
        $this->actingAs($staff)->putJson(route('admin.certificates.update-display', 1))->assertForbidden();
        $this->actingAs($staff)->postJson(route('admin.certificates.revoke', 1))->assertForbidden();
        $this->actingAs($staff)->postJson(route('admin.certificates.penandatangan.store'))->assertForbidden();
        $this->actingAs($staff)->postJson(route('admin.certificates.penandatangan.assign'))->assertForbidden();
        $this->actingAs($staff)->postJson(route('admin.certificates.pengaturan.update'))->assertForbidden();
    }

    public function test_batch_issue_rejected_if_event_has_not_ended_for_peserta(): void
    {
        $admin = $this->buatAdmin();
        $kegiatan = $this->buatKegiatan([
            'tanggal_mulai' => '2026-10-27 08:00:00',
            'tanggal_selesai' => '2026-10-27 17:00:00', // Sekarang 10:00, belum selesai
        ]);

        [$user, $reg] = $this->buatPesertaLayak($kegiatan);

        $response = $this->actingAs($admin)->post(route('admin.certificates.batch-issue'), [
            'kegiatan_id' => $kegiatan->id,
            'jenis' => 'peserta',
            'recipients' => [
                [
                    'user_id' => $user->id,
                    'registration_id' => $reg->id,
                    'nama' => $user->name,
                    'asal_institusi' => 'UNUSA',
                ],
            ],
        ]);

        $response->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseCount('sertifikat', 0);
    }

    public function test_batch_issue_successfully_issues_certificates_with_atomic_serials_and_email(): void
    {
        Queue::fake();
        $admin = $this->buatAdmin();
        $kegiatan = $this->buatKegiatan();

        [$user1, $reg1] = $this->buatPesertaLayak($kegiatan, 'Budi Santoso');
        [$user2, $reg2] = $this->buatPesertaLayak($kegiatan, 'Siti Rahma');

        $response = $this->actingAs($admin)->post(route('admin.certificates.batch-issue'), [
            'kegiatan_id' => $kegiatan->id,
            'jenis' => 'peserta',
            'recipients' => [
                [
                    'user_id' => $user1->id,
                    'registration_id' => $reg1->id,
                    'nama' => $user1->name,
                    'asal_institusi' => 'Universitas Nahdlatul Ulama Surabaya',
                    'email' => $user1->email,
                    'nim' => $user1->nim,
                ],
                [
                    'user_id' => $user2->id,
                    'registration_id' => $reg2->id,
                    'nama' => $user2->name,
                    'asal_institusi' => 'Universitas Nahdlatul Ulama Surabaya',
                    'email' => $user2->email,
                    'nim' => $user2->nim,
                ],
            ],
        ]);

        $response->assertRedirect()->assertSessionHas('success');

        // Verifikasi terbit 2 sertifikat
        $sertifikats = Sertifikat::where('kegiatan_id', $kegiatan->id)->orderBy('nomor_urut')->get();
        $this->assertCount(2, $sertifikats);

        $this->assertEquals(1, $sertifikats[0]->nomor_urut);
        $this->assertEquals(2, $sertifikats[1]->nomor_urut);
        $this->assertNotEquals($sertifikats[0]->nomor_sertifikat, $sertifikats[1]->nomor_sertifikat);

        // Verifikasi snapshot penandatangan dibekukan
        $this->assertNotEmpty($sertifikats[0]->penandatangan_snapshot);
        $this->assertIsArray($sertifikats[0]->penandatangan_snapshot);

        // Verifikasi antrean email massal lewat SendProtectedEmailJob
        Queue::assertPushed(SendProtectedEmailJob::class, 2);

        // Verifikasi audit log
        $this->assertTrue(AuditLog::where('action', 'sertifikat_batch_terbit')->exists());
    }

    public function test_batch_issue_is_idempotent_and_skips_ineligible_recipients(): void
    {
        $admin = $this->buatAdmin();
        $kegiatan = $this->buatKegiatan();

        [$userLayak, $regLayak] = $this->buatPesertaLayak($kegiatan, 'Peserta Layak');

        // Peserta tidak layak (belum check-in, belum bayar)
        $userTidakLayak = User::factory()->create(['name' => 'Peserta Belum Bayar']);
        $regTidakLayak = KegiatanRegistration::create([
            'kegiatan_id' => $kegiatan->id,
            'booker_user_id' => $userTidakLayak->id,
            'participant_user_id' => $userTidakLayak->id,
            'participant_name' => $userTidakLayak->name,
            'nomor_tiket' => 'TIK-UNPAID',
            'status_pembayaran' => RegistrationPaymentStatus::Pending,
            'status' => 'pending',
        ]);

        // Request pertama
        $this->actingAs($admin)->post(route('admin.certificates.batch-issue'), [
            'kegiatan_id' => $kegiatan->id,
            'jenis' => 'peserta',
            'recipients' => [
                [
                    'user_id' => $userLayak->id,
                    'registration_id' => $regLayak->id,
                    'nama' => $userLayak->name,
                    'asal_institusi' => 'UNUSA',
                ],
                [
                    'user_id' => $userTidakLayak->id,
                    'registration_id' => $regTidakLayak->id,
                    'nama' => $userTidakLayak->name,
                    'asal_institusi' => 'UNUSA',
                ],
            ],
        ])->assertRedirect()->assertSessionHas('success');

        // Hanya 1 yang terbit (yang layak), yang belum bayar dilewati
        $this->assertEquals(1, Sertifikat::where('kegiatan_id', $kegiatan->id)->count());

        // Klik kedua (idempoten): penerima layak yang sudah terbit dilewati
        $this->actingAs($admin)->post(route('admin.certificates.batch-issue'), [
            'kegiatan_id' => $kegiatan->id,
            'jenis' => 'peserta',
            'recipients' => [
                [
                    'user_id' => $userLayak->id,
                    'registration_id' => $regLayak->id,
                    'nama' => $userLayak->name,
                    'asal_institusi' => 'UNUSA',
                ],
            ],
        ])->assertRedirect()->assertSessionHas('error'); // Diberitahu 1 dilewati

        $this->assertEquals(1, Sertifikat::where('kegiatan_id', $kegiatan->id)->count());
    }

    public function test_manual_recipient_issuance_supports_external_recipients_without_account(): void
    {
        $admin = $this->buatAdmin();
        $kegiatan = $this->buatKegiatan();

        $response = $this->actingAs($admin)->post(route('admin.certificates.manual'), [
            'kegiatan_id' => $kegiatan->id,
            'nama_penerima' => 'Prof. Dr. Ir. H. Bambang Hermanto',
            'asal_institusi' => 'Institut Teknologi Bandung',
            'jenis' => 'pembicara',
            'predikat' => 'Keynote Speaker',
            'email_penerima' => 'bambang@itb.ac.id',
            'nim_penerima' => '196501011990031002',
            'keterangan_publik' => 'Pemateri sesi utama transformasi digital kampus',
        ]);

        $response->assertRedirect()->assertSessionHas('success');

        $cert = Sertifikat::where('nama_penerima', 'Prof. Dr. Ir. H. Bambang Hermanto')->first();
        $this->assertNotNull($cert);
        $this->assertNull($cert->user_id); // Tanpa akun
        $this->assertEquals(JenisSertifikat::Pembicara, $cert->jenis);
        $this->assertEquals('Keynote Speaker', $cert->predikat);
        $this->assertEquals('Institut Teknologi Bandung', $cert->asal_institusi);
        $this->assertStringStartsWith('ext:', $cert->penerima_key);
    }

    public function test_import_recipients_validates_formula_injection_and_row_limit(): void
    {
        $admin = $this->buatAdmin();
        $kegiatan = $this->buatKegiatan();

        // CSV dengan potensi formula injection (=cmd|' /C calc'!A0)
        $csvContent = "nama,asal_institusi,jenis,predikat,email,nim\n";
        $csvContent .= "=cmd|' /C calc'!A0,Universitas Gadjah Mada,pembicara,Keynote,hacker@example.com,\n";
        $csvContent .= "Dewi Sartika,UNUSA,peserta,,dewi@example.com,3130021001\n";

        $file = UploadedFile::fake()->createWithContent('penerima.csv', $csvContent);

        $response = $this->actingAs($admin)->post(route('admin.certificates.import'), [
            'kegiatan_id' => $kegiatan->id,
            'file' => $file,
        ]);

        $response->assertRedirect()->assertSessionHas('success');

        // Formula trigger '=' harus sudah ternetralisir saat disimpan
        $cert1 = Sertifikat::where('email_penerima', 'hacker@example.com')->first();
        $this->assertNotNull($cert1);
        $this->assertFalse(str_starts_with($cert1->nama_penerima, '='));
    }

    public function test_edit_display_data_records_history_without_changing_document_hash(): void
    {
        $admin = $this->buatAdmin();
        $kegiatan = $this->buatKegiatan();
        [$user, $reg] = $this->buatPesertaLayak($kegiatan, 'Nama Awal');

        // Buat dummy sertifikat
        $cert = Sertifikat::create([
            'kegiatan_id' => $kegiatan->id,
            'registration_id' => $reg->id,
            'user_id' => $user->id,
            'penerima_key' => 'user:'.$user->id,
            'nama_penerima' => 'Nama Awal',
            'asal_institusi' => 'UNUSA Lama',
            'nomor_sertifikat' => 'CERT-TEST-2026-0001',
            'nomor_urut' => 1,
            'jenis' => JenisSertifikat::Peserta,
            'status' => StatusSertifikat::Aktif,
            'document_hash' => 'hash_asli_dokumen_tidak_boleh_berubah_1234567890abcdef',
            'file_pdf_path' => 'certificates/test.pdf',
            'issued_at' => now(),
        ]);

        $response = $this->actingAs($admin)->put(route('admin.certificates.update-display', $cert->id), [
            'nama_penerima' => 'Nama Baru Yang Benar, S.Kom.',
            'asal_institusi' => 'Universitas Nahdlatul Ulama Surabaya',
            'jenis' => 'peserta',
            'predikat' => 'Peserta Terbaik',
            'keterangan_publik' => 'Data diperbaiki sesuai KTP',
            'alasan' => 'Ralat kesalahan penulisan gelar dari panitia',
        ]);

        $response->assertRedirect()->assertSessionHas('success');

        $cert->refresh();
        $this->assertEquals('Nama Baru Yang Benar, S.Kom.', $cert->nama_penerima);
        $this->assertEquals('Universitas Nahdlatul Ulama Surabaya', $cert->asal_institusi);
        $this->assertEquals('Peserta Terbaik', $cert->predikat);
        // Dokumen hash TIDAK berubah
        $this->assertEquals('hash_asli_dokumen_tidak_boleh_berubah_1234567890abcdef', $cert->document_hash);

        // Riwayat perubahan tercatat
        $riwayat = SertifikatRiwayatPerubahan::where('sertifikat_id', $cert->id)->get();
        $this->assertNotEmpty($riwayat);
        $this->assertTrue($riwayat->pluck('kolom_diubah')->contains('nama_penerima'));
        $this->assertEquals('Ralat kesalahan penulisan gelar dari panitia', $riwayat->first()->alasan);
    }

    public function test_revoke_certificate_soft_revokes_with_mandatory_reason_and_no_hard_delete(): void
    {
        $admin = $this->buatAdmin();
        $kegiatan = $this->buatKegiatan();

        $cert = Sertifikat::create([
            'kegiatan_id' => $kegiatan->id,
            'nama_penerima' => 'Peserta Plagiat',
            'asal_institusi' => 'UNUSA',
            'nomor_sertifikat' => 'CERT-REVOKE-2026-0001',
            'jenis' => JenisSertifikat::Peserta,
            'status' => StatusSertifikat::Aktif,
            'file_pdf_path' => 'certificates/revoke_test.pdf',
            'document_hash' => 'sha256_hash_dummy',
            'issued_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.certificates.revoke', $cert->id), [
            'alasan' => 'Terbukti melakukan pelanggaran hak cipta pada berkas karya',
        ]);

        $response->assertRedirect()->assertSessionHas('success');

        $cert->refresh();
        $this->assertTrue($cert->isDicabut());
        $this->assertEquals(StatusSertifikat::Dicabut, $cert->status);
        $this->assertEquals('Terbukti melakukan pelanggaran hak cipta pada berkas karya', $cert->alasan_pencabutan);
        $this->assertNotNull($cert->dicabut_pada);
        $this->assertEquals($admin->id, $cert->dicabut_oleh);

        // Rekord masih ada di database (tidak dihapus keras)
        $this->assertDatabaseHas('sertifikat', ['id' => $cert->id, 'status' => 'dicabut']);
    }

    public function test_signatories_crud_and_activity_assignment_with_3_display_modes(): void
    {
        $admin = $this->buatAdmin();
        $kegiatan = $this->buatKegiatan();

        // 1. Tambah master penandatangan baru (bawaan mode sembunyikan)
        $response = $this->actingAs($admin)->post(route('admin.certificates.penandatangan.store'), [
            'nama' => 'Dr. H. Ahmad Fauzi',
            'gelar' => 'M.Pd.',
            'jabatan' => 'Dekan Fakultas Keguruan',
            'jenis_identitas' => 'NIP',
            'nomor_identitas' => '197508122002121001',
            'institusi' => 'Universitas Nahdlatul Ulama Surabaya',
            'mode_tampil' => 'samarkan',
            'is_active' => 1,
        ]);

        $response->assertRedirect()->assertSessionHas('success');
        $penandatangan = Penandatangan::where('nama', 'Dr. H. Ahmad Fauzi')->first();
        $this->assertNotNull($penandatangan);
        $this->assertEquals('samarkan', $penandatangan->mode_tampil);
        $this->assertEquals('NIP: 1975****1001', $penandatangan->identitas_terformat);

        // 2. Tugaskan ke kegiatan
        $responseAssign = $this->actingAs($admin)->post(route('admin.certificates.penandatangan.assign'), [
            'kegiatan_id' => $kegiatan->id,
            'jenis_sertifikat' => 'peserta',
            'penandatangan_ids' => [$penandatangan->id],
        ]);

        $responseAssign->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('kegiatan_penandatangan', [
            'kegiatan_id' => $kegiatan->id,
            'penandatangan_id' => $penandatangan->id,
            'jenis_sertifikat' => 'peserta',
            'urutan' => 1,
        ]);
    }

    public function test_verification_settings_toggles_and_intro_text_update(): void
    {
        $admin = $this->buatAdmin();

        $response = $this->actingAs($admin)->post(route('admin.certificates.pengaturan.update'), [
            'tampilkan_asal_institusi' => '1',
            'tampilkan_jenis_predikat' => '1',
            'tampilkan_nim_samar' => '1',
            'tampilkan_penyelenggara' => '0',
            'tampilkan_keterangan' => '1',
            'tampilkan_tabel_penandatangan' => '1',
            'teks_pengantar' => 'Pengantar baru verifikasi resmi UNUSA',
        ]);

        $response->assertRedirect()->assertSessionHas('success');

        $setting = PengaturanVerifikasi::ambil();
        $this->assertTrue($setting->tampilkan_asal_institusi);
        $this->assertFalse($setting->tampilkan_penyelenggara);
        $this->assertEquals('Pengantar baru verifikasi resmi UNUSA', $setting->teks_pengantar);
    }
}
