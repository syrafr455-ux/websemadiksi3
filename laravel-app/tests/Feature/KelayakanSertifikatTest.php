<?php

namespace Tests\Feature;

use App\Enums\JenisSertifikat;
use App\Enums\KegiatanStatus;
use App\Enums\RegistrationPaymentStatus;
use App\Enums\StatusSertifikat;
use App\Models\Kegiatan;
use App\Models\KegiatanGabung;
use App\Models\KegiatanPresensi;
use App\Models\KegiatanRegistration;
use App\Models\KegiatanSubmission;
use App\Models\Sertifikat;
use App\Models\User;
use App\Services\Kegiatan\KelayakanSertifikatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KelayakanSertifikatTest extends TestCase
{
    use RefreshDatabase;

    protected KelayakanSertifikatService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(KelayakanSertifikatService::class);
    }

    protected function buatKegiatan(string $metode = 'offline', bool $isLomba = false): Kegiatan
    {
        $creator = User::factory()->superAdmin()->create();

        return Kegiatan::create([
            'created_by' => $creator->id,
            'nama_kegiatan' => 'Kegiatan Uji Kelayakan '.strtoupper($metode),
            'deskripsi' => 'Deskripsi',
            'kategori' => $isLomba ? 'lomba' : 'seminar',
            'metode_pelaksanaan' => $metode,
            'sasaran_peserta' => 'umum',
            'tanggal_mulai' => now()->subHours(4),
            'tanggal_selesai' => now()->subHours(1),
            'lokasi_type' => $metode === 'online' ? 'online' : 'unusa_campus',
            'lokasi_nama' => 'Ruang 1',
            'file_panduan_pdf' => 'panduan.pdf',
            'biaya' => 0,
            'kuota' => 100,
            'status' => KegiatanStatus::Published,
            'is_seat_booking_active' => false,
            'is_submission_active' => $isLomba,
            'presensi_akhir_dibuka' => false,
        ]);
    }

    protected function buatRegistrasi(Kegiatan $kegiatan, ?User $user = null, string $status = 'free'): KegiatanRegistration
    {
        $user = $user ?: User::factory()->create();

        return KegiatanRegistration::create([
            'kegiatan_id' => $kegiatan->id,
            'booker_user_id' => $user->id,
            'participant_user_id' => $user->id,
            'participant_name' => $user->name,
            'status_pembayaran' => $status,
            'payment_status' => $status,
            'status' => $status,
            'ticket_code' => 'TIK-'.rand(1000, 9999),
            'qr_code_hash' => 'hash-'.rand(1000, 9999),
        ]);
    }

    protected function buatPresensi(KegiatanRegistration $reg, array $attr = []): KegiatanPresensi
    {
        return KegiatanPresensi::create(array_merge([
            'kegiatan_id' => $reg->kegiatan_id,
            'registration_id' => $reg->id,
            'user_id' => $reg->participant_user_id,
            'nama_lengkap' => $reg->participant_name,
            'nim' => '2241010001',
            'prodi' => 'S1 Sistem Informasi',
            'email' => 'peserta@unusa.ac.id',
            'nomor_telepon' => '081234567890',
            'signature_path' => 'signatures/sig_test.png',
            'check_in_at' => now()->subHours(2),
        ], $attr));
    }

    public function test_peserta_offline_harus_check_in_awal_dan_presensi_akhir(): void
    {
        $kegiatan = $this->buatKegiatan('offline');
        $reg = $this->buatRegistrasi($kegiatan);

        // Kasus 1: Belum check-in tiket awal
        $eval1 = $this->service->evaluasiPeserta($kegiatan, $reg);
        $this->assertFalse($eval1['layak']);
        $this->assertSame('Belum check-in awal', $eval1['alasan']);

        // Kasus 2: Sudah check-in awal, belum presensi akhir
        $reg->update(['checked_in_at' => now()->subHours(3)]);
        $eval2 = $this->service->evaluasiPeserta($kegiatan, $reg->fresh());
        $this->assertFalse($eval2['layak']);
        $this->assertSame('Belum presensi akhir', $eval2['alasan']);

        // Kasus 3: Presensi akhir lengkap dengan tanda tangan -> Layak
        $this->buatPresensi($reg, ['signature_path' => 'signatures/sig_test.png']);

        $eval3 = $this->service->evaluasiPeserta($kegiatan, $reg->fresh());
        $this->assertTrue($eval3['layak']);
        $this->assertSame('Layak', $eval3['alasan']);
        $this->assertSame('qr', $eval3['detail']['sumber_presensi']);

        // Kasus 4: Presensi manual admin ditandai sumber 'manual'
        KegiatanPresensi::where('registration_id', $reg->id)->update([
            'signature_path' => 'signatures/sig_manual_123.png',
        ]);
        $eval4 = $this->service->evaluasiPeserta($kegiatan, $reg->fresh());
        $this->assertTrue($eval4['layak']);
        $this->assertSame('manual', $eval4['detail']['sumber_presensi']);
    }

    public function test_peserta_daring_memakai_kegiatan_gabung_sebagai_absen_awal(): void
    {
        $kegiatan = $this->buatKegiatan('online');
        $reg = $this->buatRegistrasi($kegiatan);

        // Belum klik gabung
        $eval1 = $this->service->evaluasiPeserta($kegiatan, $reg);
        $this->assertFalse($eval1['layak']);
        $this->assertSame('Belum gabung', $eval1['alasan']);

        // Klik gabung dicatat di kegiatan_gabung
        KegiatanGabung::create([
            'kegiatan_id' => $kegiatan->id,
            'registration_id' => $reg->id,
            'user_id' => $reg->participant_user_id,
            'pertama_pada' => now()->subHours(3),
            'terakhir_pada' => now()->subHours(2),
            'jumlah' => 1,
        ]);

        // Sudah gabung tapi belum presensi akhir
        $eval2 = $this->service->evaluasiPeserta($kegiatan, $reg->fresh());
        $this->assertFalse($eval2['layak']);
        $this->assertSame('Belum presensi akhir', $eval2['alasan']);

        // Isi presensi akhir -> Layak
        $this->buatPresensi($reg, ['signature_path' => 'signatures/sig_online.png']);

        $eval3 = $this->service->evaluasiPeserta($kegiatan, $reg->fresh());
        $this->assertTrue($eval3['layak']);
        $this->assertSame('gabung', $eval3['detail']['sumber_check_in']);
    }

    public function test_peserta_hybrid_mengakui_check_in_tiket_atau_klik_gabung(): void
    {
        $kegiatan = $this->buatKegiatan('hybrid');

        // Peserta 1: Hadir langsung lewat tiket
        $reg1 = $this->buatRegistrasi($kegiatan);
        $reg1->update(['checked_in_at' => now()->subHours(3)]);
        $this->buatPresensi($reg1, ['signature_path' => 'signatures/sig1.png']);

        $eval1 = $this->service->evaluasiPeserta($kegiatan, $reg1);
        $this->assertTrue($eval1['layak']);
        $this->assertSame('tiket', $eval1['detail']['sumber_check_in']);

        // Peserta 2: Hadir online lewat gabung
        $reg2 = $this->buatRegistrasi($kegiatan);
        KegiatanGabung::create([
            'kegiatan_id' => $kegiatan->id,
            'registration_id' => $reg2->id,
            'user_id' => $reg2->participant_user_id,
            'pertama_pada' => now()->subHours(3),
            'terakhir_pada' => now()->subHours(2),
            'jumlah' => 1,
        ]);
        $this->buatPresensi($reg2, ['signature_path' => 'signatures/sig2.png']);

        $eval2 = $this->service->evaluasiPeserta($kegiatan, $reg2);
        $this->assertTrue($eval2['layak']);
        $this->assertSame('gabung', $eval2['detail']['sumber_check_in']);
    }

    public function test_peserta_lomba_wajib_mengumpulkan_karya(): void
    {
        $kegiatan = $this->buatKegiatan('offline', isLomba: true);
        $reg = $this->buatRegistrasi($kegiatan);
        $reg->update(['checked_in_at' => now()->subHours(3)]);
        $this->buatPresensi($reg, ['signature_path' => 'signatures/sig_lomba.png']);

        // Belum kumpul karya
        $eval1 = $this->service->evaluasiPeserta($kegiatan, $reg->fresh());
        $this->assertFalse($eval1['layak']);
        $this->assertSame('Karya belum dikumpulkan', $eval1['alasan']);

        // Kumpul karya
        KegiatanSubmission::create([
            'kegiatan_id' => $kegiatan->id,
            'registration_id' => $reg->id,
            'nama_kelompok_peserta' => 'Tim Alpha',
            'title' => 'Inovasi AI',
            'file_karya_url' => 'submissions/karya.pdf',
        ]);

        $eval2 = $this->service->evaluasiPeserta($kegiatan, $reg->fresh());
        $this->assertTrue($eval2['layak']);
        $this->assertSame('Layak', $eval2['alasan']);
    }

    public function test_pembayaran_belum_lunas_atau_batal_atau_blokir_tidak_layak(): void
    {
        $kegiatan = $this->buatKegiatan('offline');

        // 1. Pending (belum bayar)
        $regPending = $this->buatRegistrasi($kegiatan, status: RegistrationPaymentStatus::Pending->value);
        $evalPending = $this->service->evaluasiPeserta($kegiatan, $regPending);
        $this->assertFalse($evalPending['layak']);
        $this->assertSame('Pembayaran belum lunas', $evalPending['alasan']);

        // 2. Dibatalkan / Expired
        $regCancelled = $this->buatRegistrasi($kegiatan, status: RegistrationPaymentStatus::Expired->value);
        $evalCancelled = $this->service->evaluasiPeserta($kegiatan, $regCancelled);
        $this->assertFalse($evalCancelled['layak']);
        $this->assertSame('Registrasi dibatalkan', $evalCancelled['alasan']);

        // 3. Akun diblokir
        $userBlocked = User::factory()->create(['is_blocked' => true]);
        $regBlocked = $this->buatRegistrasi($kegiatan, user: $userBlocked, status: RegistrationPaymentStatus::Free->value);
        $evalBlocked = $this->service->evaluasiPeserta($kegiatan, $regBlocked);
        $this->assertFalse($evalBlocked['layak']);
        $this->assertSame('Akun peserta diblokir', $evalBlocked['alasan']);
    }

    public function test_evaluasi_juri_menghitung_karya_yang_telah_dinilai(): void
    {
        $kegiatan = $this->buatKegiatan('offline', isLomba: true);

        $juri1 = User::factory()->create(['name' => 'Juri 1']);
        $juri2 = User::factory()->create(['name' => 'Juri 2']);
        $kegiatan->penilai()->attach([$juri1->id, $juri2->id]);

        $reg1 = $this->buatRegistrasi($kegiatan);
        $reg2 = $this->buatRegistrasi($kegiatan);

        KegiatanSubmission::create([
            'kegiatan_id' => $kegiatan->id,
            'registration_id' => $reg1->id,
            'title' => 'Karya 1',
            'file_karya_url' => 'karya1.pdf',
            'nilai' => 88.5,
            'judged_by' => $juri1->id,
            'judged_at' => now(),
        ]);

        KegiatanSubmission::create([
            'kegiatan_id' => $kegiatan->id,
            'registration_id' => $reg2->id,
            'title' => 'Karya 2',
            'file_karya_url' => 'karya2.pdf',
        ]);

        $evalJuri = $this->service->evaluasiJuri($kegiatan)->keyBy('user_id');

        $this->assertTrue($evalJuri[$juri1->id]['telah_menilai']);
        $this->assertSame(1, $evalJuri[$juri1->id]['karya_dinilai']);
        $this->assertSame(2, $evalJuri[$juri1->id]['total_karya']);
        $this->assertTrue($evalJuri[$juri1->id]['default_checked']);

        $this->assertFalse($evalJuri[$juri2->id]['telah_menilai']);
        $this->assertSame(0, $evalJuri[$juri2->id]['karya_dinilai']);
        $this->assertFalse($evalJuri[$juri2->id]['default_checked']);
        $this->assertSame('Belum menilai karya', $evalJuri[$juri2->id]['alasan']);
    }

    public function test_evaluasi_juara_mengurutkan_nilai_dan_memberi_peringatan_karya_belum_dinilai(): void
    {
        $kegiatan = $this->buatKegiatan('offline', isLomba: true);

        $user1 = User::factory()->create(['name' => 'Juara Pertama']);
        $user2 = User::factory()->create(['name' => 'Juara Kedua']);
        $user3 = User::factory()->create(['name' => 'Belum Dinilai']);

        $reg1 = $this->buatRegistrasi($kegiatan, user: $user1);
        $reg2 = $this->buatRegistrasi($kegiatan, user: $user2);
        $reg3 = $this->buatRegistrasi($kegiatan, user: $user3);

        KegiatanSubmission::create([
            'kegiatan_id' => $kegiatan->id,
            'registration_id' => $reg1->id,
            'title' => 'Karya Juara 1',
            'file_karya_url' => 'karya1.pdf',
            'nilai' => 95.0,
        ]);

        KegiatanSubmission::create([
            'kegiatan_id' => $kegiatan->id,
            'registration_id' => $reg2->id,
            'title' => 'Karya Juara 2',
            'file_karya_url' => 'karya2.pdf',
            'nilai' => 89.0,
        ]);

        KegiatanSubmission::create([
            'kegiatan_id' => $kegiatan->id,
            'registration_id' => $reg3->id,
            'title' => 'Karya Belum Nilai',
            'file_karya_url' => 'karya3.pdf',
            'nilai' => null,
        ]);

        $evalJuara = $this->service->evaluasiJuara($kegiatan);

        $this->assertTrue($evalJuara['ada_karya_belum_dinilai']);
        $this->assertSame(1, $evalJuara['jumlah_belum_dinilai']);

        $usulan = $evalJuara['usulan'];
        $this->assertCount(2, $usulan);
        $this->assertSame('Juara Pertama', $usulan[0]['nama']);
        $this->assertSame('Juara 1', $usulan[0]['predikat_usulan']);
        $this->assertSame(95.0, $usulan[0]['nilai']);

        $this->assertSame('Juara Kedua', $usulan[1]['nama']);
        $this->assertSame('Juara 2', $usulan[1]['predikat_usulan']);
        $this->assertSame(89.0, $usulan[1]['nilai']);
    }

    public function test_peraih_sertifikat_juara_tidak_tercentang_bawaan_di_daftar_peserta(): void
    {
        $kegiatan = $this->buatKegiatan('offline');
        $user = User::factory()->create(['name' => 'Sang Juara']);
        $reg = $this->buatRegistrasi($kegiatan, user: $user);
        $reg->update(['checked_in_at' => now()->subHours(3)]);
        $this->buatPresensi($reg, ['signature_path' => 'signatures/sig_test.png']);

        $penerimaKey = Sertifikat::hitungPenerimaKey($user->id, $user->name, $user->institution);

        // Sebelum terbit juara: default_checked peserta = true
        $peserta1 = $this->service->evaluasiSemuaPeserta($kegiatan)->first();
        $this->assertTrue($peserta1['default_checked']);

        // Terbitkan sertifikat juara
        Sertifikat::create([
            'nomor_sertifikat' => 'CERT-WIN-2026-001',
            'kegiatan_id' => $kegiatan->id,
            'user_id' => $user->id,
            'penerima_key' => $penerimaKey,
            'nama_penerima' => $user->name,
            'jenis' => JenisSertifikat::Juara,
            'predikat' => 'Juara 1',
            'status' => StatusSertifikat::Aktif,
            'file_pdf_path' => 'win.pdf',
            'document_hash' => 'hash',
        ]);

        // Setelah terbit juara: default_checked peserta = false
        $peserta2 = $this->service->evaluasiSemuaPeserta($kegiatan)->first();
        $this->assertTrue($peserta2['sudah_juara']);
        $this->assertFalse($peserta2['default_checked']);
    }

    public function test_waktu_penerbitan_menolak_sebelum_kegiatan_selesai_untuk_peserta(): void
    {
        $kegiatan = $this->buatKegiatan('offline');

        // 1. Kegiatan masih berlangsung -> tolak
        $kegiatan->update([
            'tanggal_mulai' => now()->subHour(),
            'tanggal_selesai' => now()->addHour(),
        ]);

        $cek1 = $this->service->cekWaktuPenerbitan($kegiatan, JenisSertifikat::Peserta);
        $this->assertFalse($cek1['bisa']);
        $this->assertStringContainsString('setelah kegiatan selesai', $cek1['alasan']);

        // 2. Kegiatan selesai tapi presensi akhir masih dibuka -> tolak
        $kegiatan->update([
            'tanggal_selesai' => now()->subMinutes(10),
            'presensi_akhir_dibuka' => true,
        ]);
        $cek2 = $this->service->cekWaktuPenerbitan($kegiatan, JenisSertifikat::Peserta);
        $this->assertFalse($cek2['bisa']);
        $this->assertStringContainsString('presensi akhir masih terbuka', $cek2['alasan']);

        // 3. Kegiatan selesai dan presensi akhir ditutup -> lolos
        $kegiatan->update([
            'presensi_akhir_dibuka' => false,
        ]);
        $cek3 = $this->service->cekWaktuPenerbitan($kegiatan, JenisSertifikat::Peserta);
        $this->assertTrue($cek3['bisa']);

        // 4. Juri boleh setelah kegiatan dimulai (walau belum selesai)
        $kegiatanSedangBerjalan = $this->buatKegiatan('offline');
        $kegiatanSedangBerjalan->update([
            'tanggal_mulai' => now()->subHour(),
            'tanggal_selesai' => now()->addHour(),
        ]);
        $cekJuri = $this->service->cekWaktuPenerbitan($kegiatanSedangBerjalan, JenisSertifikat::Juri);
        $this->assertTrue($cekJuri['bisa']);
    }
}
