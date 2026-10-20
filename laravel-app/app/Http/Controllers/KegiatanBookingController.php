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
use App\Services\Kegiatan\PetaKursiService;
use App\Services\PaymentGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class KegiatanBookingController extends Controller
{
    public function __construct(
        protected PaymentGatewayService $paymentService,
        protected PetaKursiService $peta
    ) {}

    /**
     * Detail kegiatan, peta kursi, dan alur pilih/pilih ulang kursi untuk peserta.
     * ?pilih_ulang=1 (tautan dari email pilih ulang kursi) langsung membuka alur pilih ulang;
     * tamu diarahkan masuk dulu lalu kembali ke halaman ini.
     */
    public function show(Request $request, string $slugOrId): Response|RedirectResponse
    {
        // Tabel kegiatan tidak punya kolom slug (query "where slug" gagal di PostgreSQL): cari berdasarkan ID saja.
        abort_unless(ctype_digit($slugOrId), 404);
        $kegiatan = Kegiatan::findOrFail((int) $slugOrId);
        $user = $request->user();
        $isSuperAdmin = $user && $user->role === UserRole::SuperAdmin;

        if ($kegiatan->status === KegiatanStatus::Cancelled) {
            abort(404);
        }

        if ($kegiatan->status === KegiatanStatus::Draft) {
            abort_unless($isSuperAdmin, 404);
        }

        if ($request->boolean('pilih_ulang') && ! $user) {
            return redirect()->guest(route('login'))->with('success', 'Silakan masuk untuk memilih ulang kursi Anda.');
        }

        return Inertia::render('Kegiatan/Show', [
            // toArray(): tanggal terserialisasi ISO-8601 UTC seperti props Inertia lainnya.
            'kegiatan' => Arr::only($kegiatan->toArray(), [
                'id', 'nama_kegiatan', 'deskripsi', 'kategori', 'metode_pelaksanaan', 'sasaran_peserta',
                'tanggal_mulai', 'tanggal_selesai', 'registration_deadline', 'status',
                'lokasi_type', 'lokasi_nama', 'lokasi_detail', 'narasumber', 'biaya', 'kuota', 'file_panduan_url',
            ]),
            'isDraftPreview' => $kegiatan->status === KegiatanStatus::Draft,
            'pendaftaran' => [
                'dibuka' => $kegiatan->isRegistrationOpen(),
                'kuota_tersisa' => max(0, (int) $kegiatan->kuota - (int) $kegiatan->kuota_terisi),
            ],
            'booking' => $this->statusBooking($kegiatan),
            'kursi' => $this->peta->untukPeserta($kegiatan, $user),
            'registrasiSaya' => $this->registrasiSaya($kegiatan, $user),
            'bukaPilihUlang' => $request->boolean('pilih_ulang'),
            'paymentMethods' => config('payment.methods', []),
        ]);
    }

    /**
     * Status peta kursi untuk pembaruan berkala (tiap ~30 detik) di halaman peserta.
     * Hanya id & status kursi (nama hanya untuk kursi milik pengguna sendiri) dan status jendela booking.
     */
    public function statusKursi(Request $request, int $id): JsonResponse
    {
        $kegiatan = Kegiatan::findOrFail($id);
        $user = $request->user();
        $isSuperAdmin = $user && $user->role === UserRole::SuperAdmin;

        if ($kegiatan->status === KegiatanStatus::Cancelled) {
            abort(404);
        }

        if ($kegiatan->status === KegiatanStatus::Draft) {
            abort_unless($isSuperAdmin, 404);
        }

        return response()->json([
            'booking' => $this->statusBooking($kegiatan),
            'kursi' => $this->peta->statusRingkas($kegiatan, $request->user()),
        ]);
    }

    /**
     * @return array{memakai_denah: bool, status: string, buka: mixed, tutup: mixed, bisa_pilih: bool}
     */
    protected function statusBooking(Kegiatan $kegiatan): array
    {
        return [
            'memakai_denah' => $kegiatan->hasSeatLayout(),
            'status' => $kegiatan->statusBookingKursi(),
            'buka' => $kegiatan->booking_opens_at?->toJSON(),
            'tutup' => $kegiatan->booking_closes_at?->toJSON(),
            'bisa_pilih' => $kegiatan->isSeatBookingOpen(),
        ];
    }

    /**
     * Registrasi aktif yang dipesan atau diikuti pengguna di kegiatan ini. Hanya pemesan yang bisa memilih ulang kursi.
     *
     * @return list<array<string, mixed>>
     */
    protected function registrasiSaya(Kegiatan $kegiatan, ?User $user): array
    {
        if (! $user) {
            return [];
        }

        return KegiatanRegistration::with(['seat:id,seat_code', 'participantUser:id,name', 'bookerUser:id,name'])
            ->where('kegiatan_id', $kegiatan->id)
            ->active()
            ->where(fn ($q) => $q->where('booker_user_id', $user->id)->orWhere('participant_user_id', $user->id))
            ->orderBy('id')
            ->get()
            ->map(fn (KegiatanRegistration $reg) => [
                'id' => $reg->id,
                'nama_peserta' => $reg->ticket_holder_name,
                'nomor_tiket' => $reg->nomor_tiket,
                'kursi' => $reg->seat?->seat_code,
                'seat_id' => $reg->seat_id,
                'perlu_pilih_ulang' => (bool) $reg->needs_seat_reselection,
                'saya_pemesan' => (int) $reg->booker_user_id === (int) $user->id,
                'saya_peserta' => (int) $reg->participant_user_id === (int) $user->id,
                'nama_pemesan' => $reg->bookerUser?->name,
            ])
            ->values()
            ->all();
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

        // Tolak pendaftaran kegiatan draft atau dibatalkan
        if ($kegiatan->status === KegiatanStatus::Draft) {
            return back()->with('error', 'Kegiatan ini masih berstatus draft dan belum dibuka untuk pendaftaran.');
        }

        if ($kegiatan->status === KegiatanStatus::Cancelled) {
            return back()->with('error', 'Kegiatan ini telah dibatalkan.');
        }

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

            // Gate isSeatBookingOpen() HANYA untuk kegiatan yang memakai denah kursi
            if ($kegiatan->hasSeatLayout()) {
                if (! $kegiatan->isSeatBookingOpen()) {
                    DB::rollBack();
                    return back()->with('error', 'Pemilihan kursi untuk kegiatan ini sedang ditutup atau berada di luar jadwal booking.');
                }

                // Kegiatan berdenah: setiap peserta wajib punya kursi, dan satu kursi untuk satu peserta.
                $errorKursi = [];
                $dipakai = [];
                foreach ($delegates as $i => $delegate) {
                    $sId = $delegate['seat_id'] ?? null;
                    if (! $sId) {
                        $errorKursi["delegates.{$i}.seat_id"] = 'Pilih kursi untuk peserta ke-'.($i + 1).'.';
                    } elseif (in_array($sId, $dipakai, true)) {
                        $errorKursi["delegates.{$i}.seat_id"] = 'Satu kursi hanya untuk satu peserta.';
                    }
                    $dipakai[] = $sId;
                }
                if ($errorKursi !== []) {
                    DB::rollBack();
                    return back()->withErrors($errorKursi)->with('error', reset($errorKursi));
                }
            }

            if (! empty($seatIds)) {
                // Pessimistic locking: lockForUpdate()
                $lockedSeats = KegiatanSeat::where('kegiatan_id', $kegiatan->id)
                    ->whereIn('id', $seatIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                // Kumpulkan SEMUA kursi yang gagal (per peserta) agar pilihan lain yang masih valid bisa dipertahankan.
                $bentrok = [];
                foreach ($delegates as $i => $delegate) {
                    $sId = $delegate['seat_id'] ?? null;
                    if (! $sId) {
                        continue;
                    }

                    $seat = $lockedSeats[$sId] ?? null;
                    if (! $seat) {
                        $bentrok["delegates.{$i}.seat_id"] = 'Kursi yang dipilih tidak valid.';
                    } elseif ($seat->status !== SeatStatus::Available) {
                        $bentrok["delegates.{$i}.seat_id"] = "Kursi {$seat->seat_code} baru saja diambil atau tidak tersedia. Silakan pilih kursi lain.";
                    }
                }

                if ($bentrok !== []) {
                    DB::rollBack();
                    return back()->withErrors($bentrok)->with('error', implode(' ', array_unique($bentrok)));
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

    /**
     * Memungkinkan pemesan (booker) untuk memilih ulang kursi (baik untuk diri sendiri maupun delegasinya)
     * akibat pemindahan ruangan dan reset denah.
     */
    public function reselectSeat(Request $request, int $id)
    {
        $user = auth()->user();
        if (! $user) {
            return redirect()->route('login')->with('error', 'Silakan masuk terlebih dahulu.');
        }

        $kegiatan = Kegiatan::findOrFail($id);

        if ($kegiatan->status === KegiatanStatus::Draft) {
            return back()->with('error', 'Kegiatan ini masih berstatus draft.');
        }

        if ($kegiatan->status === KegiatanStatus::Cancelled) {
            return back()->with('error', 'Kegiatan ini telah dibatalkan.');
        }

        // Gate: Pemilihan kursi harus sedang terbuka
        if (! $kegiatan->isSeatBookingOpen()) {
            return back()->with('error', 'Pemilihan kursi untuk kegiatan ini sedang ditutup atau berada di luar jadwal booking.');
        }

        $validated = $request->validate([
            'registration_id' => ['required', 'integer'],
            'seat_id' => ['required', 'integer'],
        ]);

        DB::beginTransaction();
        try {
            // 1. Revisi 4 & 5: Validasi registrasi harus milik kegiatan ini dan booker-nya adalah user yang login
            $registration = KegiatanRegistration::where('id', $validated['registration_id'])
                ->where('kegiatan_id', $kegiatan->id)
                ->lockForUpdate()
                ->first();

            if (! $registration || (int) $registration->booker_user_id !== (int) $user->id) {
                DB::rollBack();
                return back()->with('error', 'Registrasi tidak valid atau Anda bukan pemesan tiket/delegasi ini.');
            }

            if (! $registration->needs_seat_reselection || ! in_array($registration->status_pembayaran?->value, RegistrationPaymentStatus::activeValues(), true)) {
                DB::rollBack();
                return back()->with('error', 'Registrasi ini tidak sedang menunggu pemilihan ulang kursi.');
            }

            // 2. Revisi 5: Validasi kursi harus berasal dari kegiatan ini (tolak kursi dari kegiatan lain)
            $seat = KegiatanSeat::where('id', $validated['seat_id'])
                ->lockForUpdate()
                ->first();

            if (! $seat || (int) $seat->kegiatan_id !== (int) $kegiatan->id) {
                DB::rollBack();
                return back()->with('error', 'Kursi yang dipilih tidak valid atau berasal dari kegiatan lain.');
            }

            // 3. Revisi 5: Validasi status kursi harus Available (tolak kursi tidak available)
            if ($seat->status !== SeatStatus::Available) {
                DB::rollBack();
                $pesan = "Kursi {$seat->seat_code} tidak tersedia atau baru saja diambil. Silakan pilih kursi lain.";

                return back()->withErrors(['seat_id' => $pesan])->with('error', $pesan);
            }

            // Kursi lama (bila masih tercatat) dilepas agar tidak tertahan.
            if ($registration->seat_id && (int) $registration->seat_id !== (int) $seat->id) {
                KegiatanSeat::whereKey($registration->seat_id)->where('status', SeatStatus::Booked->value)
                    ->update(['status' => SeatStatus::Available->value]);
            }

            // Kunci status kursi baru
            $seat->update(['status' => SeatStatus::Booked]);

            // Hubungkan registrasi ke kursi baru dan kembalikan flag needs_seat_reselection ke false
            $registration->update([
                'seat_id' => $seat->id,
                'needs_seat_reselection' => false,
            ]);

            DB::commit();

            // Masih ada peserta (pesanan pengguna ini) yang menunggu kursi baru: kembali ke alur pilih ulang.
            $sisa = KegiatanRegistration::where('kegiatan_id', $kegiatan->id)
                ->where('booker_user_id', $user->id)
                ->active()
                ->where('needs_seat_reselection', true)
                ->count();

            if ($sisa > 0) {
                return redirect()->route('kegiatan.detail', ['slugOrId' => $kegiatan->id, 'pilih_ulang' => 1])
                    ->with('success', "Kursi {$seat->seat_code} tersimpan untuk {$registration->participant_name}. Masih ada {$sisa} peserta yang perlu kursi baru.");
            }

            return redirect()->route('dashboard.kegiatan.saya')->with('success', 'Kursi berhasil dipilih kembali. Selamat menikmati kegiatan!');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal memilih ulang kursi: '.$e->getMessage());
        }
    }
}
