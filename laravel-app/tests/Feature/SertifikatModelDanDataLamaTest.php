<?php

namespace Tests\Feature;

use App\Enums\JenisSertifikat;
use App\Enums\KegiatanStatus;
use App\Enums\StatusSertifikat;
use App\Models\Kegiatan;
use App\Models\Sertifikat;
use App\Models\SertifikatRiwayatPerubahan;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SertifikatModelDanDataLamaTest extends TestCase
{
    use RefreshDatabase;

    protected function buatKegiatan(): Kegiatan
    {
        $creator = User::factory()->superAdmin()->create();

        return Kegiatan::create([
            'created_by' => $creator->id,
            'nama_kegiatan' => 'Seminar Teknologi Nasional',
            'deskripsi' => 'Deskripsi kegiatan',
            'kategori' => 'seminar',
            'metode_pelaksanaan' => 'offline',
            'sasaran_peserta' => 'umum',
            'tanggal_mulai' => now()->addDays(2),
            'tanggal_selesai' => now()->addDays(2)->addHours(2),
            'lokasi_type' => 'unusa_campus',
            'lokasi_nama' => 'Auditorium Tower',
            'file_panduan_pdf' => 'panduan.pdf',
            'biaya' => 0,
            'kuota' => 100,
            'status' => KegiatanStatus::Published,
            'is_seat_booking_active' => false,
            'is_submission_active' => false,
        ]);
    }

    public function test_sertifikat_lama_tetap_valid_dan_memiliki_default_jenis_peserta(): void
    {
        $kegiatan = $this->buatKegiatan();
        $user = User::factory()->create(['name' => 'Fauzan Lama']);

        $nomorLama = 'CERT-ASYK-2026-8280';
        $hashLama = hash('sha256', 'dummy-pdf-content');

        $sertifikat = Sertifikat::create([
            'nomor_sertifikat' => $nomorLama,
            'kegiatan_id' => $kegiatan->id,
            'user_id' => $user->id,
            'penerima_key' => Sertifikat::hitungPenerimaKey($user->id, null, null),
            'nama_penerima' => $user->name,
            'file_pdf_path' => 'certificates/'.$kegiatan->id.'/'.$nomorLama.'.pdf',
            'document_hash' => $hashLama,
            'is_valid' => true,
        ]);

        $sertifikat->refresh();

        $this->assertSame($nomorLama, $sertifikat->nomor_sertifikat);
        $this->assertSame($hashLama, $sertifikat->document_hash);
        $this->assertSame(JenisSertifikat::Peserta, $sertifikat->jenis);
        $this->assertSame(StatusSertifikat::Aktif, $sertifikat->status);
        $this->assertTrue($sertifikat->isAktif());
        $this->assertFalse($sertifikat->isDicabut());
        $this->assertSame(url('/verifikasi?code='.$nomorLama), $sertifikat->verification_url);
    }

    public function test_penerima_tanpa_akun_didukung_dengan_kunci_penerima_eksternal(): void
    {
        $kegiatan = $this->buatKegiatan();

        $nama = 'Prof. Alexander Graham';
        $institusi = 'Institut Teknologi Bandung';

        $key = Sertifikat::hitungPenerimaKey(null, $nama, $institusi);
        $this->assertStringStartsWith('ext:', $key);

        $sertifikat = Sertifikat::create([
            'nomor_sertifikat' => 'CERT-SMD-2026-0001',
            'kegiatan_id' => $kegiatan->id,
            'user_id' => null,
            'penerima_key' => $key,
            'nama_penerima' => $nama,
            'asal_institusi' => $institusi,
            'jenis' => JenisSertifikat::Pembicara,
            'file_pdf_path' => 'certificates/sample.pdf',
            'document_hash' => hash('sha256', 'sample'),
            'status' => StatusSertifikat::Aktif,
        ]);

        $this->assertNull($sertifikat->user_id);
        $this->assertSame($nama, $sertifikat->recipient_name);
        $this->assertSame($institusi, $sertifikat->institution_name);
        $this->assertSame(JenisSertifikat::Pembicara, $sertifikat->jenis);
    }

    public function test_keunikan_satu_sertifikat_per_kegiatan_penerima_jenis(): void
    {
        $kegiatan = $this->buatKegiatan();
        $user = User::factory()->create(['name' => 'Budi Santoso']);
        $key = Sertifikat::hitungPenerimaKey($user->id, null, null);

        // 1. Buat sertifikat peserta
        Sertifikat::create([
            'nomor_sertifikat' => 'CERT-SMD-2026-P01',
            'kegiatan_id' => $kegiatan->id,
            'user_id' => $user->id,
            'penerima_key' => $key,
            'nama_penerima' => $user->name,
            'jenis' => JenisSertifikat::Peserta,
            'file_pdf_path' => 'sample.pdf',
            'document_hash' => 'hash1',
        ]);

        // 2. Buat sertifikat juri untuk orang yang sama pada kegiatan yang sama -> BOLEH
        $sertifikatJuri = Sertifikat::create([
            'nomor_sertifikat' => 'CERT-SMD-2026-J01',
            'kegiatan_id' => $kegiatan->id,
            'user_id' => $user->id,
            'penerima_key' => $key,
            'nama_penerima' => $user->name,
            'jenis' => JenisSertifikat::Juri,
            'file_pdf_path' => 'sample2.pdf',
            'document_hash' => 'hash2',
        ]);
        $this->assertNotNull($sertifikatJuri->id);

        // 3. Duplikat sertifikat peserta untuk orang yang sama pada kegiatan yang sama -> GAGAL (Unique violation)
        // Dibungkus savepoint: PostgreSQL membatalkan seluruh transaksi tes setelah galat, sehingga query
        // sesudahnya (termasuk auditor kolom di tearDown) gagal dengan SQLSTATE 25P02. SQLite tidak begitu.
        $this->expectException(QueryException::class);
        DB::transaction(fn () => Sertifikat::create([
            'nomor_sertifikat' => 'CERT-SMD-2026-P02',
            'kegiatan_id' => $kegiatan->id,
            'user_id' => $user->id,
            'penerima_key' => $key,
            'nama_penerima' => $user->name,
            'jenis' => JenisSertifikat::Peserta,
            'file_pdf_path' => 'sample3.pdf',
            'document_hash' => 'hash3',
        ]));
    }

    public function test_pencabutan_sertifikat_mencatat_alasan_dan_tidak_menghapus_data(): void
    {
        $kegiatan = $this->buatKegiatan();
        $admin = User::factory()->superAdmin()->create();
        $user = User::factory()->create();

        $sertifikat = Sertifikat::create([
            'nomor_sertifikat' => 'CERT-SMD-2026-0005',
            'kegiatan_id' => $kegiatan->id,
            'user_id' => $user->id,
            'penerima_key' => Sertifikat::hitungPenerimaKey($user->id, null, null),
            'nama_penerima' => $user->name,
            'jenis' => JenisSertifikat::Peserta,
            'status' => StatusSertifikat::Aktif,
            'file_pdf_path' => 'sample.pdf',
            'document_hash' => 'hash1',
        ]);

        // Cabut sertifikat
        $sertifikat->update([
            'status' => StatusSertifikat::Dicabut,
            'alasan_pencabutan' => 'Pelanggaran integritas lomba',
            'dicabut_pada' => now(),
            'dicabut_oleh' => $admin->id,
        ]);

        $this->assertDatabaseHas('sertifikat', [
            'id' => $sertifikat->id,
            'status' => 'dicabut',
            'alasan_pencabutan' => 'Pelanggaran integritas lomba',
            'dicabut_oleh' => $admin->id,
        ]);
        $this->assertTrue($sertifikat->fresh()->isDicabut());
    }

    public function test_riwayat_perubahan_data_tampilan_tercatat_di_tabel_terpisah(): void
    {
        $kegiatan = $this->buatKegiatan();
        $admin = User::factory()->superAdmin()->create();

        $sertifikat = Sertifikat::create([
            'nomor_sertifikat' => 'CERT-SMD-2026-0006',
            'kegiatan_id' => $kegiatan->id,
            'nama_penerima' => 'Ahmad Dani',
            'asal_institusi' => 'UNUSA',
            'jenis' => JenisSertifikat::Peserta,
            'status' => StatusSertifikat::Aktif,
            'file_pdf_path' => 'sample.pdf',
            'document_hash' => 'hash1',
        ]);

        SertifikatRiwayatPerubahan::create([
            'sertifikat_id' => $sertifikat->id,
            'user_id' => $admin->id,
            'ip_address' => '127.0.0.1',
            'kolom_diubah' => 'nama_penerima',
            'nilai_lama' => 'Ahmad Dani',
            'nilai_baru' => 'Ahmad Dani Pratama, S.Kom.',
            'alasan' => 'Penambahan gelar resmi sesuai ijazah',
        ]);

        $this->assertCount(1, $sertifikat->riwayatPerubahan);
        $this->assertSame('Ahmad Dani', $sertifikat->riwayatPerubahan->first()->nilai_lama);
        $this->assertSame('Ahmad Dani Pratama, S.Kom.', $sertifikat->riwayatPerubahan->first()->nilai_baru);
    }

    public function test_penomoran_seri_atomik_per_kegiatan(): void
    {
        $kegiatan = $this->buatKegiatan();

        $hasil1 = Sertifikat::generateNomorSertifikatAtomik($kegiatan, 2026);
        $this->assertSame(1, $hasil1['nomor_urut']);
        $this->assertStringContainsString('2026-0001', $hasil1['nomor_sertifikat']);

        Sertifikat::create([
            'nomor_sertifikat' => $hasil1['nomor_sertifikat'],
            'nomor_urut' => $hasil1['nomor_urut'],
            'kegiatan_id' => $kegiatan->id,
            'nama_penerima' => 'Peserta 1',
            'jenis' => JenisSertifikat::Peserta,
            'file_pdf_path' => 'sample.pdf',
            'document_hash' => 'hash1',
        ]);

        $hasil2 = Sertifikat::generateNomorSertifikatAtomik($kegiatan, 2026);
        $this->assertSame(2, $hasil2['nomor_urut']);
        $this->assertStringContainsString('2026-0002', $hasil2['nomor_sertifikat']);
    }
}
