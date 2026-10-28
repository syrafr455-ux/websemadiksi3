<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\GatewayPaymentStatus;
use App\Enums\KegiatanStatus;
use App\Enums\RegistrationPaymentStatus;
use App\Mail\TicketIssuedMail;
use App\Models\Kegiatan;
use App\Models\KegiatanRegistration;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Tes karakterisasi modul pembayaran (Tripay). Memanggil jalur kode yang ADA apa adanya; kode modul pembayaran
 * tidak diubah. Tes yang menegaskan perilaku yang seharusnya tetapi gagal karena kekurangan modul diberi
 * markTestSkipped "menunggu keputusan pemilik proyek"; isi tesnya tetap ada sebagai bukti dan bahan perbaikan.
 */
class KarakterisasiModulPembayaranTest extends TestCase
{
    use RefreshDatabase;

    private const KUNCI = 'kunci-privat-uji-karakterisasi';

    private User $peserta;

    private Kegiatan $kegiatan;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        // Tanpa kredensial Tripay: jalur simulasi (tanpa HTTP). Kunci privat uji dipasang agar tanda tangan callback dapat dibuat.
        config([
            'services.tripay.merchant_code' => null, 'services.tripay.api_key' => null, 'services.tripay.private_key' => self::KUNCI,
            'payment.expiry_minutes' => 60,
        ]);

        $admin = User::factory()->superAdmin()->create();
        $this->peserta = User::factory()->userUmum()->create(['phone' => '081234567890']);
        $this->kegiatan = Kegiatan::create([
            'created_by' => $admin->id, 'nama_kegiatan' => 'Workshop Berbayar', 'kategori' => 'workshop', 'metode_pelaksanaan' => 'offline',
            'sasaran_peserta' => 'umum', 'tanggal_mulai' => now()->addDays(10), 'tanggal_selesai' => now()->addDays(10)->addHours(3),
            'lokasi_type' => 'unusa_campus', 'lokasi_nama' => 'Auditorium', 'kuota' => 10, 'biaya' => 50000, 'file_panduan_pdf' => 'x.pdf',
            'status' => KegiatanStatus::Published,
        ]);
    }

    private function checkout(array $tambahan = []): TestResponse
    {
        return $this->actingAs($this->peserta)->post(route('kegiatan.checkout', $this->kegiatan->id), array_merge([
            'payment_method' => 'QRIS',
            'delegates' => [['name' => 'Budi Peserta', 'email' => 'budi@example.com'], ['name' => 'Rani Peserta', 'email' => 'rani@example.com']],
        ], $tambahan));
    }

    /** Pembayaran pending dengan dua registrasi, dibuat langsung agar tes callback tidak bergantung pada checkout. */
    private function pembayaranPending(float $jumlah = 100000): array
    {
        $payment = Payment::create([
            'user_id' => $this->peserta->id, 'order_id' => 'PAY-UJI-'.uniqid(), 'payment_method' => 'QRIS', 'gross_amount' => $jumlah,
            'payment_status' => GatewayPaymentStatus::Pending, 'expired_at' => now()->addHour(),
        ]);
        DB::table('kegiatan')->where('id', $this->kegiatan->id)->update(['kuota_terisi' => 2]);
        $regs = [];
        foreach (['Budi', 'Rani'] as $i => $nama) {
            $regs[] = KegiatanRegistration::create([
                'kegiatan_id' => $this->kegiatan->id, 'booker_user_id' => $this->peserta->id, 'participant_user_id' => null,
                'nomor_tiket' => "TKT-UJI-{$payment->id}-{$i}", 'participant_name' => $nama, 'amount_paid' => 50000,
                'status_pembayaran' => RegistrationPaymentStatus::Pending, 'status_kehadiran' => AttendanceStatus::Registered, 'payment_id' => $payment->id,
            ]);
        }

        return [$payment, $regs];
    }

    /** Nilai kolom kegiatan.kuota_terisi; $kegiatan->kuota_terisi memakai accessor yang menghitung baris registrasi. */
    private function kolomKuota(): int
    {
        return (int) DB::table('kegiatan')->where('id', $this->kegiatan->id)->value('kuota_terisi');
    }

    private function kirimCallback(array $payload, ?string $kunci = self::KUNCI): TestResponse
    {
        $mentah = json_encode($payload);

        return $this->call('POST', '/api/payment/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_CALLBACK_SIGNATURE' => hash_hmac('sha256', $mentah, (string) $kunci),
        ], $mentah);
    }

    // ---------- (a)(b)(c) pembuatan pembayaran: jalur hidup, atribut yang ditulis, tempat data tersimpan ----------

    public function test_checkout_berbayar_membuat_payment_dengan_kolom_nyata_dan_menautkan_registrasi(): void
    {
        $this->checkout()->assertSessionHasNoErrors()->assertRedirect(route('dashboard.kegiatan.saya'));

        $payment = Payment::firstOrFail();
        $this->assertStringStartsWith('PAY-', $payment->order_id);
        $this->assertSame($this->peserta->id, (int) $payment->user_id);
        $this->assertEquals(100000.0, (float) $payment->gross_amount, 'Harga dihitung server: 2 tiket x biaya kegiatan, bukan dari klien.');
        $this->assertSame(GatewayPaymentStatus::Pending, $payment->payment_status);
        $this->assertSame('QRIS', $payment->payment_method);
        $this->assertTrue($payment->payload_response['simulated'] ?? false);
        $this->assertNotNull($payment->expired_at);
        $this->assertNull($payment->paid_at);

        $regs = KegiatanRegistration::where('payment_id', $payment->id)->get();
        $this->assertCount(2, $regs);
        foreach ($regs as $reg) {
            $this->assertEquals(50000.0, (float) $reg->amount_paid);
            $this->assertSame(RegistrationPaymentStatus::Pending, $reg->status_pembayaran);
        }
        $this->assertSame(2, $this->kolomKuota(), 'Kolom kegiatan.kuota_terisi naik sebanyak tiket.');
    }

    public function test_harga_checkout_tidak_bisa_diubah_dari_klien(): void
    {
        $this->checkout(['amount' => 1, 'biaya' => 1, 'gross_amount' => 1, 'delegates' => [['name' => 'Budi', 'email' => 'budi@example.com', 'amount_paid' => 1]]])->assertSessionHasNoErrors();

        $this->assertEquals(50000.0, (float) Payment::firstOrFail()->gross_amount);
        $this->assertEquals(50000.0, (float) KegiatanRegistration::firstOrFail()->amount_paid);
    }

    // ---------- Callback: tanda tangan, status, pesanan tak dikenal ----------

    public function test_callback_paid_bertanda_tangan_sah_melunasi_pembayaran_dan_registrasi(): void
    {
        [$payment, $regs] = $this->pembayaranPending();

        $this->kirimCallback(['merchant_ref' => $payment->order_id, 'status' => 'PAID', 'total_amount' => 100000, 'reference' => 'T1'])
            ->assertOk()->assertJson(['success' => true]);

        $payment->refresh();
        $this->assertSame(GatewayPaymentStatus::Settlement, $payment->payment_status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame('T1', $payment->payload_response['reference'], 'Payload callback digabung ke payload_response (jsonb).');
        foreach ($regs as $reg) {
            $this->assertSame(RegistrationPaymentStatus::Paid, $reg->fresh()->status_pembayaran);
        }
    }

    public function test_callback_tanpa_atau_dengan_tanda_tangan_salah_ditolak_403_dan_tidak_mengubah_data(): void
    {
        [$payment, $regs] = $this->pembayaranPending();
        $badan = json_encode(['merchant_ref' => $payment->order_id, 'status' => 'PAID']);

        $this->call('POST', '/api/payment/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json'], $badan)->assertStatus(403);
        $this->kirimCallback(['merchant_ref' => $payment->order_id, 'status' => 'PAID'], 'kunci-salah')->assertStatus(403);

        $this->assertSame(GatewayPaymentStatus::Pending, $payment->fresh()->payment_status);
        $this->assertSame(RegistrationPaymentStatus::Pending, $regs[0]->fresh()->status_pembayaran);
    }

    public function test_callback_pesanan_tak_dikenal_dijawab_json_tanpa_error_500(): void
    {
        $this->kirimCallback(['merchant_ref' => 'PAY-TIDAK-ADA', 'status' => 'PAID'])->assertOk()->assertJson(['success' => false]);
        $this->kirimCallback(['status' => 'PAID'])->assertOk()->assertJson(['success' => false]);
        $this->kirimCallback(['merchant_ref' => str_repeat('X', 400), 'status' => 'PAID'])->assertOk()->assertJson(['success' => false]);
    }

    public function test_callback_expired_melepas_kuota_dan_menandai_registrasi_kedaluwarsa(): void
    {
        [$payment, $regs] = $this->pembayaranPending();

        $this->kirimCallback(['merchant_ref' => $payment->order_id, 'status' => 'EXPIRED'])->assertOk()->assertJson(['success' => true]);

        $this->assertSame(GatewayPaymentStatus::Expired, $payment->fresh()->payment_status);
        $this->assertSame(RegistrationPaymentStatus::Expired, $regs[0]->fresh()->status_pembayaran);
        $this->assertSame(0, $this->kolomKuota(), 'Kolom kuota_terisi dikurangi sebanyak registrasi.');
    }

    /** Lewati kecuali UJI_BUKTI_PEMBAYARAN=1, supaya pemilik bisa melihat kegagalan aslinya. */
    private function lewatiMenungguPemilik(): void
    {
        if (getenv('UJI_BUKTI_PEMBAYARAN') !== '1') {
            $this->markTestSkipped('menunggu keputusan pemilik proyek: perbaikan modul pembayaran');
        }
    }

    // ---------- Perilaku yang seharusnya, tetapi belum dipenuhi modul (menunggu keputusan pemilik) ----------

    public function test_callback_expired_ganda_tidak_mengurangi_kuota_dua_kali(): void
    {
        $this->lewatiMenungguPemilik();

        [$payment] = $this->pembayaranPending();
        DB::table('kegiatan')->where('id', $this->kegiatan->id)->update(['kuota_terisi' => 5]); // 2 milik pembayaran ini, 3 milik pendaftar lain

        $this->kirimCallback(['merchant_ref' => $payment->order_id, 'status' => 'EXPIRED'])->assertOk();
        $this->kirimCallback(['merchant_ref' => $payment->order_id, 'status' => 'EXPIRED'])->assertOk();

        $this->assertSame(3, $this->kolomKuota(), 'Callback ganda harus idempoten.');
    }

    public function test_kuota_yang_dibaca_checkout_turun_setelah_pembayaran_kedaluwarsa(): void
    {
        $this->lewatiMenungguPemilik();

        [$payment] = $this->pembayaranPending();
        $this->kirimCallback(['merchant_ref' => $payment->order_id, 'status' => 'EXPIRED'])->assertOk();

        // checkout membaca $kegiatan->kuota_terisi (accessor getKuotaTerisiAttribute = jumlah SEMUA baris registrasi, termasuk expired/failed).
        $this->assertSame(0, $this->kegiatan->fresh()->kuota_terisi, 'Kuota yang dilepas callback harus terlihat oleh pemeriksaan kuota checkout.');
    }

    public function test_callback_paid_ganda_mengirim_e_tiket_sekali(): void
    {
        $this->lewatiMenungguPemilik();

        [$payment] = $this->pembayaranPending();
        $this->peserta->update(['email' => 'peserta@example.com']);

        $this->kirimCallback(['merchant_ref' => $payment->order_id, 'status' => 'PAID', 'total_amount' => 100000])->assertOk();
        $this->kirimCallback(['merchant_ref' => $payment->order_id, 'status' => 'PAID', 'total_amount' => 100000])->assertOk();

        Mail::assertSent(TicketIssuedMail::class, 2); // satu per registrasi, bukan empat
    }

    public function test_callback_paid_dengan_jumlah_berbeda_tidak_melunasi(): void
    {
        $this->lewatiMenungguPemilik();

        [$payment, $regs] = $this->pembayaranPending(100000);

        $this->kirimCallback(['merchant_ref' => $payment->order_id, 'status' => 'PAID', 'total_amount' => 1000]);

        $this->assertSame(GatewayPaymentStatus::Pending, $payment->fresh()->payment_status);
        $this->assertSame(RegistrationPaymentStatus::Pending, $regs[0]->fresh()->status_pembayaran);
    }

    public function test_tanpa_kunci_privat_terkonfigurasi_callback_bertanda_tangan_kunci_bawaan_ditolak(): void
    {
        $this->lewatiMenungguPemilik();

        config(['services.tripay.private_key' => null]);
        [$payment] = $this->pembayaranPending();

        // Kunci bawaan 'sandbox_private_key_semadiksi' tertulis di kode (PaymentGatewayService baris 33 dan 160).
        $this->kirimCallback(['merchant_ref' => $payment->order_id, 'status' => 'PAID'], 'sandbox_private_key_semadiksi')->assertStatus(403);
        $this->assertSame(GatewayPaymentStatus::Pending, $payment->fresh()->payment_status);
    }

    public function test_ada_tugas_terjadwal_yang_menutup_pembayaran_pending_melewati_expired_at(): void
    {
        $this->lewatiMenungguPemilik();

        $this->assertNotEmpty(app(Schedule::class)->events(), 'routes/console.php tidak menjadwalkan apa pun; kedaluwarsa hanya terjadi bila Tripay mengirim callback.');
    }

    public function test_metode_pembayaran_di_luar_daftar_ditolak_dengan_galat_validasi_bukan_pesan_sql(): void
    {
        $this->lewatiMenungguPemilik();

        $this->checkout(['payment_method' => str_repeat('Q', 300)])->assertSessionHasErrors('payment_method');
        $this->assertSame(0, Payment::count());
        $this->assertSame(0, $this->kolomKuota());
    }
}
