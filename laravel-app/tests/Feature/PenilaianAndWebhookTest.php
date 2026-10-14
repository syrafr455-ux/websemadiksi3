<?php

namespace Tests\Feature;

use App\Enums\GatewayPaymentStatus;
use App\Enums\KegiatanStatus;
use App\Enums\RegistrationPaymentStatus;
use App\Enums\SeatStatus;
use App\Enums\SeatTier;
use App\Enums\UserRole;
use App\Models\Kegiatan;
use App\Models\KegiatanRegistration;
use App\Models\KegiatanSeat;
use App\Models\KegiatanSubmission;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PenilaianAndWebhookTest extends TestCase
{
    use RefreshDatabase;
    protected function getSuperAdmin(): User
    {
        return User::where('role', UserRole::SuperAdmin->value)->first()
            ?? User::factory()->create(['role' => UserRole::SuperAdmin]);
    }

    protected function getPenilaiKarya(): User
    {
        return User::where('role', UserRole::PenilaiKarya->value)->first()
            ?? User::create([
                'name' => 'Juri Penilai Satu',
                'email' => 'juri1@semadiksi.ac.id',
                'password' => bcrypt('password'),
                'role' => UserRole::PenilaiKarya->value,
                'is_blocked' => false,
            ]);
    }

    protected function getMahasiswaKipk(): User
    {
        return User::where('role', UserRole::MahasiswaKipk->value)->first()
            ?? User::factory()->create(['role' => UserRole::MahasiswaKipk, 'is_blocked' => false]);
    }

    protected function getOrCreateTestKegiatan(): Kegiatan
    {
        return Kegiatan::firstOrCreate(
            ['nama_kegiatan' => 'Lomba Karya Inovasi Digital 2026'],
            [
                'kategori' => 'lomba',
                'metode_pelaksanaan' => 'online',
                'sasaran_peserta' => 'umum',
                'tanggal_mulai' => now()->addDays(10),
                'lokasi_type' => 'online',
                'lokasi_nama' => 'Zoom Meeting',
                'biaya' => 0,
                'kuota' => 100,
                'kuota_terisi' => 1,
                'file_panduan_pdf' => 'kegiatan/panduan/panduan_lomba.pdf',
                'is_submission_active' => true,
                'status' => KegiatanStatus::Published,
            ]
        );
    }

    public function test_penilai_karya_can_access_penilaian_index(): void
    {
        $juri = $this->getPenilaiKarya();
        $response = $this->actingAs($juri)->get(route('admin.penilaian'));
        $response->assertStatus(200);
        $response->assertSee('Admin\/Penilaian\/Index', false);
    }

    public function test_mahasiswa_cannot_access_penilaian_index(): void
    {
        $student = $this->getMahasiswaKipk();
        $response = $this->actingAs($student)->get(route('admin.penilaian'));
        $response->assertRedirect(route('admin.login'));
    }

    public function test_penilai_can_update_score_and_feedback(): void
    {
        $juri = $this->getPenilaiKarya();
        $admin = $this->getSuperAdmin();
        $kegiatan = $this->getOrCreateTestKegiatan();

        $reg = KegiatanRegistration::create([
            'kegiatan_id' => $kegiatan->id,
            'booker_user_id' => $admin->id,
            'participant_user_id' => null,
            'nomor_tiket' => KegiatanRegistration::generateTicketNumber($kegiatan->id, 'GEN'),
            'participant_name' => 'Peserta Inovatif',
            'status_pembayaran' => RegistrationPaymentStatus::Free,
        ]);

        $submission = KegiatanSubmission::create([
            'registration_id' => $reg->id,
            'kegiatan_id' => $kegiatan->id,
            'nama_kelompok_peserta' => 'Aplikasi Pendeteksi Citra Medis AI',
            'file_karya_url' => 'https://example.com/submission.pdf',
        ]);

        $response = $this->actingAs($juri)->post(route('admin.penilaian.score', $submission->id), [
            'final_score' => 94.5,
            'judge_notes' => 'Karya sangat orisinal dengan implementasi arsitektur mutakhir.',
            'score_breakdown' => [
                'orisinilitas' => 95,
                'metodologi' => 92,
                'dampak' => 96,
            ],
        ]);

        $response->assertSessionHas('success');

        $submission->refresh();
        $this->assertEquals(94.5, (float) $submission->final_score);
        $this->assertSame('Karya sangat orisinal dengan implementasi arsitektur mutakhir.', $submission->judge_notes);
        $this->assertSame($juri->name, $submission->graded_by);
    }

    public function test_penilai_can_export_and_import_csv(): void
    {
        $juri = $this->getPenilaiKarya();
        $kegiatan = $this->getOrCreateTestKegiatan();

        $submission = KegiatanSubmission::where('kegiatan_id', $kegiatan->id)->latest()->first();
        if (! $submission) {
            $reg = KegiatanRegistration::create([
                'kegiatan_id' => $kegiatan->id,
                'booker_user_id' => $this->getSuperAdmin()->id,
                'participant_user_id' => null,
                'nomor_tiket' => KegiatanRegistration::generateTicketNumber($kegiatan->id, 'GEN'),
                'participant_name' => 'Peserta Inovatif',
                'status_pembayaran' => RegistrationPaymentStatus::Free,
            ]);

            $submission = KegiatanSubmission::create([
                'registration_id' => $reg->id,
                'kegiatan_id' => $kegiatan->id,
                'nama_kelompok_peserta' => 'Aplikasi Pendeteksi Citra Medis AI',
                'file_karya_url' => 'https://example.com/submission.pdf',
            ]);
        }
        $this->assertNotNull($submission);

        // Test Export
        $exportResponse = $this->actingAs($juri)->get(route('admin.penilaian.export', $kegiatan->id));
        $exportResponse->assertStatus(200);
        $this->assertStringContainsString('text/csv', $exportResponse->headers->get('Content-Type'));

        // Test Import
        $csvContent = "\xEF\xBB\xBFID_SUBMISSION,NOMOR_TIKET,NAMA_PESERTA,NIM_IDENTITAS,JUDUL_KARYA,URL_BERKAS,NILAI_AKHIR,CATATAN_JURI\n";
        $csvContent .= "{$submission->id},TKT-DEMO,Peserta Inovatif,224001,Aplikasi AI,http://url,98.5,\"Nilai revisi juri via CSV\"\n";

        $tempFile = UploadedFile::fake()->createWithContent('nilai.csv', $csvContent);

        $importResponse = $this->actingAs($juri)->post(route('admin.penilaian.import', $kegiatan->id), [
            'csv_file' => $tempFile,
        ]);

        $importResponse->assertSessionHas('success');

        $submission->refresh();
        $this->assertEquals(98.5, (float) $submission->final_score);
        $this->assertSame('Nilai revisi juri via CSV', $submission->judge_notes);
    }

    public function test_tripay_webhook_paid_status_issues_tickets(): void
    {
        Mail::fake();

        $admin = $this->getSuperAdmin();
        $kegiatan = $this->getOrCreateTestKegiatan();

        $seatCode = 'X'.rand(10, 99);
        $seat = KegiatanSeat::create([
            'kegiatan_id' => $kegiatan->id,
            'seat_code' => $seatCode,
            'row_label' => 'X',
            'seat_number' => 1,
            'col_number' => 1,
            'tier' => SeatTier::Vip,
            'status' => SeatStatus::Booked,
        ]);

        $orderRef = 'DEV-TRIPAY-'.time().'-'.rand(100, 999);
        $payment = Payment::create([
            'user_id' => $admin->id,
            'order_id' => $orderRef,
            'gross_amount' => 50000,
            'payment_status' => GatewayPaymentStatus::Pending,
            'payment_method' => 'QRIS',
        ]);

        $reg = KegiatanRegistration::create([
            'kegiatan_id' => $kegiatan->id,
            'booker_user_id' => $admin->id,
            'participant_user_id' => null,
            'seat_id' => $seat->id,
            'nomor_tiket' => KegiatanRegistration::generateTicketNumber($kegiatan->id, $seatCode),
            'participant_name' => 'Delegasi Berbayar Satu',
            'amount_paid' => 50000,
            'status_pembayaran' => RegistrationPaymentStatus::Pending,
            'payment_id' => $payment->id,
        ]);

        $payload = [
            'reference' => 'T123456789',
            'merchant_ref' => $orderRef,
            'payment_method' => 'QRIS',
            'payment_method_code' => 'QRIS',
            'total_amount' => 50000,
            'status' => 'PAID',
            'paid_at' => time(),
        ];

        $rawBody = json_encode($payload);
        $privateKey = config('services.tripay.private_key') ?: 'sandbox_private_key_semadiksi';
        $signature = hash_hmac('sha256', $rawBody, $privateKey);

        $response = $this->call(
            'POST',
            '/api/payment/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_CALLBACK_SIGNATURE' => $signature,
            ],
            $rawBody
        );

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $payment->refresh();
        $reg->refresh();
        $seat->refresh();

        $this->assertSame(GatewayPaymentStatus::Settlement, $payment->payment_status);
        $this->assertSame(RegistrationPaymentStatus::Paid, $reg->status_pembayaran);
        $this->assertSame(SeatStatus::Booked, $seat->status);
    }

    public function test_tripay_webhook_invalid_signature_is_rejected(): void
    {
        $payload = ['status' => 'PAID', 'merchant_ref' => 'FAKE-REF'];
        $rawBody = json_encode($payload);

        $response = $this->call(
            'POST',
            '/api/payment/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_CALLBACK_SIGNATURE' => 'invalid-hmac-sha256-signature',
            ],
            $rawBody
        );

        $response->assertStatus(403);
        $response->assertJson(['success' => false, 'message' => 'Invalid signature.']);
    }

    public function test_tripay_webhook_expired_releases_seat(): void
    {
        $admin = $this->getSuperAdmin();
        $kegiatan = $this->getOrCreateTestKegiatan();

        $seatCode = 'Y'.rand(10, 99);
        $seat = KegiatanSeat::create([
            'kegiatan_id' => $kegiatan->id,
            'seat_code' => $seatCode,
            'row_label' => 'Y',
            'seat_number' => 2,
            'col_number' => 2,
            'tier' => SeatTier::Reguler,
            'status' => SeatStatus::Booked,
        ]);

        $orderRef = 'DEV-EXPIRE-'.time().'-'.rand(100, 999);
        $payment = Payment::create([
            'user_id' => $admin->id,
            'order_id' => $orderRef,
            'gross_amount' => 50000,
            'payment_status' => GatewayPaymentStatus::Pending,
            'payment_method' => 'QRIS',
        ]);

        $reg = KegiatanRegistration::create([
            'kegiatan_id' => $kegiatan->id,
            'booker_user_id' => $admin->id,
            'participant_user_id' => null,
            'seat_id' => $seat->id,
            'nomor_tiket' => KegiatanRegistration::generateTicketNumber($kegiatan->id, $seatCode),
            'participant_name' => 'Delegasi Kedaluwarsa',
            'amount_paid' => 50000,
            'status_pembayaran' => RegistrationPaymentStatus::Pending,
            'payment_id' => $payment->id,
        ]);

        $payload = [
            'reference' => 'T987654321',
            'merchant_ref' => $orderRef,
            'status' => 'EXPIRED',
        ];

        $rawBody = json_encode($payload);
        $privateKey = config('services.tripay.private_key') ?: 'sandbox_private_key_semadiksi';
        $signature = hash_hmac('sha256', $rawBody, $privateKey);

        $response = $this->call(
            'POST',
            '/api/payment/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_CALLBACK_SIGNATURE' => $signature,
            ],
            $rawBody
        );

        $response->assertStatus(200);

        $payment->refresh();
        $reg->refresh();
        $seat->refresh();

        $this->assertSame(GatewayPaymentStatus::Expired, $payment->payment_status);
        $this->assertSame(RegistrationPaymentStatus::Expired, $reg->status_pembayaran);
        $this->assertSame(SeatStatus::Available, $seat->status);
    }
}
