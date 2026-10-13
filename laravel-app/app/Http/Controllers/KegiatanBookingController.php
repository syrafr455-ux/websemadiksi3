<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\KegiatanStatus;
use App\Enums\RegistrationPaymentStatus;
use App\Enums\SeatStatus;
use App\Enums\UserRole;
use App\Mail\TicketIssuedMail;
use App\Models\AuditLog;
use App\Models\Kegiatan;
use App\Models\KegiatanRegistration;
use App\Models\KegiatanSeat;
use App\Models\User;
use App\Services\PaymentGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class KegiatanBookingController extends Controller
{
    public function __construct(
        protected PaymentGatewayService $paymentService
    ) {}

    /**
     * Tampilkan detail kegiatan dan peta denah kursi interaktif untuk peserta.
     */
    public function show(string $slugOrId): Response
    {
        $kegiatan = Kegiatan::with(['seats' => function ($q) {
            $q->orderBy('row_label')->orderBy('col_number');
        }])
        ->where('slug', $slugOrId)
        ->orWhere('id', is_numeric($slugOrId) ? (int) $slugOrId : -1)
        ->firstOrFail();

        // Check if current user is already registered
        $myRegistration = null;
        if (auth()->check()) {
            $myRegistration = KegiatanRegistration::with('seat')
                ->where('kegiatan_id', $kegiatan->id)
                ->where(function ($q) {
                    $q->where('participant_user_id', auth()->id())
                      ->orWhere('booker_user_id', auth()->id());
                })
                ->first();
        }

        $paymentMethods = config('payment.methods', []);

        return Inertia::render('Kegiatan/Show', [
            'kegiatan' => $kegiatan,
            'myRegistration' => $myRegistration,
            'paymentMethods' => $paymentMethods,
        ]);
    }

    /**
     * Checkout pendaftaran kegiatan (mendukung single atau multi-delegation booking)
     * Menggunakan Pessimistic Locking (lockForUpdate) untuk mencegah race condition.
     */
    public function checkout(Request $request, int $id)
    {
        $user = auth()->user();
        if (! $user) {
            return redirect()->route('login')->with('error', 'Silakan masuk terlebih dahulu untuk mendaftar kegiatan.');
        }

        $kegiatan = Kegiatan::findOrFail($id);

        // Validasi status kegiatan & batas waktu registrasi
        if (! $kegiatan->isRegistrationOpen()) {
            return back()->with('error', 'Pendaftaran untuk kegiatan ini telah ditutup.');
        }

        // Validasi target peserta
        if ($kegiatan->target_peserta === 'kipk_only' && $user->role !== UserRole::MahasiswaKipk) {
            return back()->with('error', 'Kegiatan ini khusus diperuntukkan bagi mahasiswa penerima beasiswa KIP-K UNUSA.');
        }

        $validated = $request->validate([
            'payment_method' => ['nullable', 'string'],
            'delegates' => ['required', 'array', 'min:1', 'max:10'],
            'delegates.*.name' => ['required', 'string', 'max:255'],
            'delegates.*.email' => ['nullable', 'email', 'max:255'],
            'delegates.*.identity' => ['nullable', 'string', 'max:50'],
            'delegates.*.phone' => ['nullable', 'string', 'max:20'],
            'delegates.*.seat_id' => ['nullable', 'integer'],
        ]);

        $delegates = $validated['delegates'];
        $count = count($delegates);

        // Periksa kuota tersisa
        if ($kegiatan->kuota_terisi + $count > $kegiatan->kuota_total) {
            return back()->with('error', "Kuota kegiatan tidak mencukupi untuk {$count} peserta.");
        }

        $isFree = ((int) $kegiatan->biaya === 0);
        $paymentMethod = $validated['payment_method'] ?? 'QRIS';

        DB::beginTransaction();
        try {
            // Ambil dan kunci kursi dengan pessimistic locking jika event memiliki pemilihan kursi
            $seatIds = array_filter(array_column($delegates, 'seat_id'));
            $lockedSeats = [];

            if (! empty($seatIds)) {
                // Pessimistic locking: lockForUpdate()
                $lockedSeats = KegiatanSeat::where('kegiatan_id', $kegiatan->id)
                    ->whereIn('id', $seatIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                foreach ($delegates as $delegate) {
                    $sId = $delegate['seat_id'] ?? null;
                    if (! $sId) {
                        continue;
                    }

                    if (! isset($lockedSeats[$sId])) {
                        DB::rollBack();
                        return back()->with('error', 'Kursi yang dipilih tidak valid.');
                    }

                    $seat = $lockedSeats[$sId];
                    if ($seat->status !== SeatStatus::Available) {
                        DB::rollBack();
                        return back()->with('error', "Kursi {$seat->seat_code} baru saja diambil atau tidak tersedia. Silakan pilih kursi lain.");
                    }
                }
            }

            $createdRegistrations = [];

            foreach ($delegates as $index => $delegate) {
                $seatId = $delegate['seat_id'] ?? null;
                $seat = $seatId ? ($lockedSeats[$seatId] ?? null) : null;
                $seatCode = $seat ? $seat->seat_code : 'GEN';

                // Tentukan participant user jika email sama dengan pemesan
                $participantUserId = ($index === 0 && strtolower(trim($delegate['email'] ?? '')) === strtolower($user->email))
                    ? $user->id
                    : null;

                // Cek apakah email delegasi sudah terdaftar sebagai user di SEMADIKSI
                if (! $participantUserId && ! empty($delegate['email'])) {
                    $foundUser = User::where('email', strtolower(trim($delegate['email'])))->first();
                    if ($foundUser) {
                        $participantUserId = $foundUser->id;
                    }
                }

                // Jika delegasi pertama tanpa email, kaitkan ke user yang sedang login
                if ($index === 0 && ! $participantUserId) {
                    $participantUserId = $user->id;
                }

                // Cegah double registration untuk user yang sama pada kegiatan yang sama
                if ($participantUserId) {
                    $alreadyReg = KegiatanRegistration::where('kegiatan_id', $kegiatan->id)
                        ->where('participant_user_id', $participantUserId)
                        ->whereIn('status_pembayaran', [
                            RegistrationPaymentStatus::Free->value,
                            RegistrationPaymentStatus::Paid->value,
                            RegistrationPaymentStatus::Pending->value,
                        ])
                        ->exists();

                    if ($alreadyReg) {
                        DB::rollBack();
                        return back()->with('error', "Peserta {$delegate['name']} sudah terdaftar pada kegiatan ini.");
                    }
                }

                $nomorTiket = KegiatanRegistration::generateNomorTiket($kegiatan->id, $seatCode);

                $reg = KegiatanRegistration::create([
                    'kegiatan_id' => $kegiatan->id,
                    'booker_user_id' => $user->id,
                    'participant_user_id' => $participantUserId,
                    'seat_id' => $seatId,
                    'nomor_tiket' => $nomorTiket,
                    'participant_name' => trim($delegate['name']),
                    'participant_identity' => $delegate['identity'] ?? ($participantUserId === $user->id ? $user->nim : null),
                    'participant_phone' => $delegate['phone'] ?? ($participantUserId === $user->id ? $user->phone : null),
                    'amount_paid' => (float) $kegiatan->biaya,
                    'status_pembayaran' => $isFree ? RegistrationPaymentStatus::Free : RegistrationPaymentStatus::Pending,
                    'status_kehadiran' => AttendanceStatus::Registered,
                    'delegation_data' => [
                        'email' => $delegate['email'] ?? null,
                        'is_booker' => ($participantUserId === $user->id),
                    ],
                ]);

                // Kunci status kursi
                if ($seat) {
                    $seat->update([
                        'status' => SeatStatus::Booked,
                    ]);
                }

                $createdRegistrations[] = $reg;
            }

            // Tambah kuota terisi
            $kegiatan->increment('kuota_terisi', $count);

            // Audit log
            AuditLog::record(
                $user->id,
                'checkout_kegiatan',
                "Pemesanan {$count} tiket kegiatan '{$kegiatan->nama_kegiatan}' (ID: {$kegiatan->id})",
                $kegiatan
            );

            // Jika gratis, langsung kirim tiket
            if ($isFree) {
                DB::commit();

                foreach ($createdRegistrations as $reg) {
                    try {
                        $targetEmail = $reg->participantUser?->email ?? $reg->delegation_data['email'] ?? $user->email;
                        if ($targetEmail) {
                            Mail::to($targetEmail)->send(new TicketIssuedMail($reg));
                        }
                    } catch (\Throwable $e) {
                        // ignore mail failure
                    }
                }

                return redirect()->route('dashboard.kegiatan.saya')->with('success', 'Pendaftaran berhasil! E-Tiket Anda telah terbit.');
            }

            // Jika berbayar, inisialisasi pembayaran gateway (Tripay)
            $payment = $this->paymentService->createPaymentTransaction($user, $createdRegistrations, $paymentMethod);

            DB::commit();

            return redirect()->route('dashboard.kegiatan.saya')->with('success', 'Pemesanan berhasil dibuat! Silakan selesaikan pembayaran sebelum batas waktu.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan saat memproses pendaftaran: '.$e->getMessage())->withInput();
        }
    }
}
