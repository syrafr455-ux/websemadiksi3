<?php

namespace App\Services;

use App\Enums\GatewayPaymentStatus;
use App\Enums\RegistrationPaymentStatus;
use App\Enums\SeatStatus;
use App\Mail\TicketIssuedMail;
use App\Models\KegiatanRegistration;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentGatewayService
{
    protected string $merchantCode;

    protected string $apiKey;

    protected string $privateKey;

    protected bool $isSandbox;

    protected string $baseUrl;

    public function __construct()
    {
        $this->merchantCode = (string) config('services.tripay.merchant_code', '');
        $this->apiKey = (string) config('services.tripay.api_key', '');
        $this->privateKey = (string) (config('services.tripay.private_key') ?: env('TRIPAY_PRIVATE_KEY', 'sandbox_private_key_semadiksi'));
        $this->isSandbox = (bool) config('services.tripay.sandbox', true);
        $this->baseUrl = $this->isSandbox
            ? 'https://tripay.co.id/api-sandbox'
            : 'https://tripay.co.id/api';
    }

    /**
     * Membuat closed transaction di Tripay untuk pendaftaran berbayar.
     *
     * @param  array<int, KegiatanRegistration>  $registrations
     * @param  string  $methodCode  Kode channel Tripay (e.g. QRIS, BRIVA, BNIVA, BCAVA)
     */
    public function createPaymentTransaction(User $payer, array $registrations, string $methodCode = 'QRIS'): Payment
    {
        if (empty($registrations)) {
            throw new \InvalidArgumentException('Daftar pendaftaran tidak boleh kosong.');
        }

        $firstReg = $registrations[0];
        $kegiatan = $firstReg->kegiatan;
        $totalAmount = 0;

        foreach ($registrations as $reg) {
            $totalAmount += (int) $reg->amount_paid;
        }

        $merchantRef = 'PAY-'.date('Ymd').'-'.strtoupper(Str::random(8));
        $expiryMinutes = config('payment.expiry_minutes', 60);
        $expiryTime = now()->addMinutes($expiryMinutes);

        // Jika credentials Tripay belum diset (misal di local dev), gunakan simulasi mockup
        if (empty($this->apiKey) || empty($this->privateKey) || empty($this->merchantCode)) {
            $payment = Payment::create([
                'user_id' => $payer->id,
                'order_id' => $merchantRef,
                'payment_method' => $methodCode,
                'gross_amount' => $totalAmount,
                'payment_status' => GatewayPaymentStatus::Pending,
                'expired_at' => $expiryTime,
                'payload_response' => [
                    'simulated' => true,
                    'pay_code' => $methodCode === 'QRIS' ? null : '88019'.rand(10000000, 99999999),
                    'qr_url' => $methodCode === 'QRIS' ? 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data='.$merchantRef : null,
                ],
            ]);

            foreach ($registrations as $reg) {
                $reg->update([
                    'payment_id' => $payment->id,
                    'status_pembayaran' => RegistrationPaymentStatus::Pending,
                ]);
            }

            return $payment;
        }

        $signature = hash_hmac('sha256', $this->merchantCode.$merchantRef.$totalAmount, $this->privateKey);

        $orderItems = [];
        foreach ($registrations as $reg) {
            $orderItems[] = [
                'sku' => 'TKT-'.$reg->kegiatan_id.'-'.$reg->id,
                'name' => 'Tiket '.$kegiatan->nama_kegiatan.' ('.$reg->participant_name.')',
                'price' => (int) $reg->amount_paid,
                'quantity' => 1,
            ];
        }

        $customerPhone = $payer->phone ?: config('payment.fallback_customer_phone', '081234567890');

        $body = [
            'method' => $methodCode,
            'merchant_ref' => $merchantRef,
            'amount' => $totalAmount,
            'customer_name' => $payer->name,
            'customer_email' => $payer->email,
            'customer_phone' => $customerPhone,
            'order_items' => $orderItems,
            'return_url' => config('services.tripay.return_url', url('/dashboard/kegiatan-saya')),
            'expired_time' => $expiryTime->timestamp,
            'signature' => $signature,
        ];

        try {
            $response = Http::withToken($this->apiKey)
                ->acceptJson()
                ->post($this->baseUrl.'/transaction/create', $body);

            $data = $response->json();

            if (! $response->successful() || empty($data['success'])) {
                Log::error('Tripay Transaction Creation Failed', ['response' => $data, 'body' => $body]);
                throw new \RuntimeException($data['message'] ?? 'Gagal membuat transaksi pembayaran Tripay.');
            }

            $txData = $data['data'] ?? [];

            $payment = Payment::create([
                'user_id' => $payer->id,
                'order_id' => $merchantRef,
                'payment_method' => $methodCode,
                'gross_amount' => $totalAmount,
                'payment_status' => GatewayPaymentStatus::Pending,
                'expired_at' => isset($txData['expired_time']) ? date('Y-m-d H:i:s', $txData['expired_time']) : $expiryTime,
                'payload_response' => $txData,
            ]);

            foreach ($registrations as $reg) {
                $reg->update([
                    'payment_id' => $payment->id,
                    'status_pembayaran' => RegistrationPaymentStatus::Pending,
                ]);
            }

            return $payment;
        } catch (\Throwable $e) {
            Log::error('Tripay Exception: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * Verifikasi signature webhook HMAC-SHA256 dari Tripay.
     */
    public function verifyWebhookSignature(string $rawContent, string $incomingSignature): bool
    {
        $key = $this->privateKey ?: (string) config('services.tripay.private_key', 'sandbox_private_key_semadiksi');
        if (empty($incomingSignature)) {
            return false;
        }

        $calculated = hash_hmac('sha256', $rawContent, $key);

        return hash_equals($calculated, $incomingSignature);
    }

    /**
     * Memproses notifikasi callback webhook Tripay.
     *
     * @return array{success: bool, message: string}
     */
    public function processWebhook(array $payload): array
    {
        $merchantRef = $payload['merchant_ref'] ?? null;
        $status = strtoupper((string) ($payload['status'] ?? ''));

        if (! $merchantRef) {
            return ['success' => false, 'message' => 'merchant_ref tidak ditemukan.'];
        }

        $payment = Payment::where('order_id', $merchantRef)->first();

        if (! $payment) {
            return ['success' => false, 'message' => "Payment dengan ref {$merchantRef} tidak ditemukan."];
        }

        return DB::transaction(function () use ($payment, $status, $payload) {
            if ($status === 'PAID') {
                $payment->update([
                    'payment_status' => GatewayPaymentStatus::Settlement,
                    'paid_at' => now(),
                    'payload_response' => array_merge($payment->payload_response ?? [], $payload),
                ]);

                // Update semua registrasi yang terikat dengan pembayaran ini
                $registrations = KegiatanRegistration::where('payment_id', $payment->id)->get();

                foreach ($registrations as $reg) {
                    $reg->update([
                        'status_pembayaran' => RegistrationPaymentStatus::Paid,
                    ]);

                    // Pastikan kursi terkunci definitif
                    if ($reg->seat_id) {
                        $reg->seat()->update([
                            'status' => SeatStatus::Booked,
                        ]);
                    }

                    // Kirim E-Tiket via email otomatis
                    try {
                        $recipientEmail = $reg->participantUser?->email ?? $reg->bookerUser?->email;
                        if ($recipientEmail) {
                            app(EmailProtectionService::class)->kirim($recipientEmail, new TicketIssuedMail($reg));
                        }
                    } catch (\Throwable $mailError) {
                        Log::error('Gagal mengirim E-Tiket setelah pembayaran: '.$mailError->getMessage());
                    }
                }

                return ['success' => true, 'message' => 'Pembayaran berhasil diselesaikan & E-Tiket diterbitkan.'];
            }

            if (in_array($status, ['EXPIRED', 'FAILED', 'REFUND'], true)) {
                $newStatus = $status === 'EXPIRED' ? GatewayPaymentStatus::Expired : GatewayPaymentStatus::Failed;
                $newRegStatus = $status === 'EXPIRED' ? RegistrationPaymentStatus::Expired : RegistrationPaymentStatus::Failed;

                $payment->update([
                    'payment_status' => $newStatus,
                    'payload_response' => array_merge($payment->payload_response ?? [], $payload),
                ]);

                $registrations = KegiatanRegistration::where('payment_id', $payment->id)->get();

                foreach ($registrations as $reg) {
                    $reg->update([
                        'status_pembayaran' => $newRegStatus,
                    ]);

                    // Lepaskan kursi kembali ke status available jika expired / failed
                    if ($reg->seat_id && $reg->seat && $reg->seat->status === SeatStatus::Booked) {
                        $reg->seat()->update([
                            'status' => SeatStatus::Available,
                        ]);
                    }

                    // Kembalikan kuota kegiatan
                    if ($reg->kegiatan) {
                        $reg->kegiatan->decrement('kuota_terisi');
                    }
                }

                return ['success' => true, 'message' => "Status pembayaran diubah menjadi {$status}."];
            }

            return ['success' => true, 'message' => "Status {$status} dicatat."];
        });
    }
}
