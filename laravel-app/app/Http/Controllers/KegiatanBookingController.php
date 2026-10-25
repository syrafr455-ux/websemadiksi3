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
use Illuminate\Validation\ValidationException;
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

        if ($kegiatan->status === KegiatanStatus::Draft || $kegiatan->status === KegiatanStatus::Diajukan) {
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
                'is_seat_booking_active', 'seat_layout_config',
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
                    } else {
                        try {
                            KegiatanSeat::pastikanBisaDipesan($seat, false, "delegates.{$i}.seat_id");
                        } catch (ValidationException $e) {
                            $bentrok["delegates.{$i}.seat_id"] = $e->validator->errors()->first("delegates.{$i}.seat_id");
                        }
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
        if ($request->has('allocations')) {
            return $this->bookSeats($request, $id);
        }

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

            // 3. Revisi 5: Validasi status kursi (harus sah dan tersedia untuk peserta)
            try {
                KegiatanSeat::pastikanBisaDipesan($seat, false, 'seat_id');
            } catch (ValidationException $e) {
                DB::rollBack();
                $pesan = $e->validator->errors()->first('seat_id');

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

    /**
     * Endpoint pencarian peserta terdaftar pada kegiatan yang belum memiliki kursi
     * (atau perlu pilih ulang kursi). Hanya dapat diakses oleh user yang sudah
     * terautentikasi dan terdaftar di kegiatan ini.
     */
    public function pesertaTanpaKursi(Request $request, int $id): JsonResponse
    {
        $kegiatan = Kegiatan::findOrFail($id);
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        // Proteksi: Endpoint hanya dapat diakses oleh user yang sudah terautentikasi dan terdaftar di kegiatan tersebut
        $isRegistered = KegiatanRegistration::where('kegiatan_id', $kegiatan->id)
            ->where(fn ($q) => $q->where('participant_user_id', $user->id)->orWhere('booker_user_id', $user->id))
            ->active()
            ->exists();

        if (! $isRegistered && $user->role !== UserRole::SuperAdmin) {
            abort(403, 'Hanya peserta yang telah terdaftar resmi pada kegiatan ini yang dapat mencari peserta.');
        }

        $likeOp = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $query = KegiatanRegistration::with(['participantUser'])
            ->where('kegiatan_id', $kegiatan->id)
            ->whereIn('status_pembayaran', [
                RegistrationPaymentStatus::Free->value,
                RegistrationPaymentStatus::Paid->value,
            ])
            ->where(fn ($q) => $q->whereNull('seat_id')->orWhere('needs_seat_reselection', true))
            ->where(function ($q) use ($user) {
                // Jangan sertakan registrasi atas nama diri sendiri dalam daftar cari teman
                $q->where('participant_user_id', '!=', $user->id)
                    ->orWhereNull('participant_user_id');
            });

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($q) use ($search, $likeOp) {
                $q->where('participant_name', $likeOp, "%{$search}%")
                    ->orWhere('participant_identity', $likeOp, "%{$search}%")
                    ->orWhereHas('participantUser', function ($u) use ($search, $likeOp) {
                        $u->where('name', $likeOp, "%{$search}%")
                            ->orWhere('nim', $likeOp, "%{$search}%")
                            ->orWhere('email', $likeOp, "%{$search}%");
                    });
            });
        }

        $peserta = $query->limit(50)->get()->map(function (KegiatanRegistration $reg) {
            $email = $reg->participantUser?->email ?? ($reg->delegation_data['email'] ?? null);

            return [
                'id' => $reg->id,
                'user_id' => $reg->participant_user_id,
                'nama_lengkap' => $reg->ticket_holder_name,
                'nim' => $reg->participantUser?->nim ?? $reg->participant_identity ?? '-',
                'email' => $this->maskEmail($email),
            ];
        });

        return response()->json($peserta);
    }

    /**
     * Alur booking alokasi kursi multi-user (pemesan mandiri + teman terdaftar).
     * Dibungkus dalam DB::transaction() dengan lockForUpdate pada kegiatan_seats dan kegiatan_registrations.
     */
    public function bookSeats(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $kegiatan = Kegiatan::findOrFail($id);
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        // Syarat 1: Saklar manual Super Admin aktif (is_seat_booking_active = true)
        if (! (bool) $kegiatan->is_seat_booking_active) {
            abort(403, 'Pemilihan kursi untuk kegiatan ini sedang dinonaktifkan oleh panitia.');
        }

        if ($kegiatan->status === KegiatanStatus::Draft || $kegiatan->status === KegiatanStatus::Cancelled) {
            abort(404);
        }

        // Validasi jendela booking kursi jika ada batas waktu
        if (! $kegiatan->isSeatBookingOpen()) {
            abort(403, 'Pemilihan kursi untuk kegiatan ini sedang ditutup atau berada di luar jadwal booking.');
        }

        // User A (pemesan) harus terdaftar secara resmi pada kegiatan ini
        $userIsRegistered = KegiatanRegistration::where('kegiatan_id', $kegiatan->id)
            ->where(fn ($q) => $q->where('participant_user_id', $user->id)->orWhere('booker_user_id', $user->id))
            ->active()
            ->exists();

        if (! $userIsRegistered && $user->role !== UserRole::SuperAdmin) {
            throw ValidationException::withMessages([
                'allocations' => 'Anda harus terdaftar secara resmi pada kegiatan ini untuk dapat memilih kursi.',
            ]);
        }

        $validated = $request->validate([
            'allocations' => ['required', 'array', 'min:1', 'max:10'],
            'allocations.*.registration_id' => ['required', 'integer'],
            'allocations.*.seat_id' => ['required', 'integer'],
        ], [
            'allocations.required' => 'Daftar alokasi kursi wajib dikirim.',
            'allocations.*.registration_id.required' => 'Registrasi peserta wajib dipilih.',
            'allocations.*.seat_id.required' => 'Kursi wajib dipilih untuk setiap peserta.',
        ]);

        $allocations = $validated['allocations'];
        $regIds = array_column($allocations, 'registration_id');
        $seatIds = array_column($allocations, 'seat_id');

        if (count($regIds) !== count(array_unique($regIds))) {
            throw ValidationException::withMessages([
                'allocations' => 'Peserta tidak boleh dipilih lebih dari satu kali dalam alokasi kursi.',
            ]);
        }

        if (count($seatIds) !== count(array_unique($seatIds))) {
            throw ValidationException::withMessages([
                'allocations' => 'Satu kursi hanya dapat dialokasikan untuk satu peserta.',
            ]);
        }

        DB::beginTransaction();
        try {
            // Lock seats and registrations
            $lockedSeats = KegiatanSeat::where('kegiatan_id', $kegiatan->id)
                ->whereIn('id', $seatIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $lockedRegistrations = KegiatanRegistration::where('kegiatan_id', $kegiatan->id)
                ->whereIn('id', $regIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $errors = [];

            foreach ($allocations as $index => $item) {
                $regId = $item['registration_id'];
                $sId = $item['seat_id'];

                $reg = $lockedRegistrations->get($regId);
                if (! $reg) {
                    $errors["allocations.{$index}.registration_id"] = 'Peserta tidak ditemukan atau belum terdaftar pada kegiatan ini.';

                    continue;
                }

                // Syarat: User B sudah terdaftar secara resmi (status pembayaran valid/lunas/gratis)
                if (! in_array($reg->status_pembayaran?->value, [RegistrationPaymentStatus::Free->value, RegistrationPaymentStatus::Paid->value], true)) {
                    $errors["allocations.{$index}.registration_id"] = "Pendaftaran untuk peserta {$reg->ticket_holder_name} belum lunas atau tidak aktif.";

                    continue;
                }

                // Syarat: User B saat ini BELUM memiliki kursi (seat_id IS NULL atau needs_seat_reselection = true)
                if ($reg->seat_id !== null && ! $reg->needs_seat_reselection) {
                    $errors["allocations.{$index}.registration_id"] = "Peserta {$reg->ticket_holder_name} sudah memiliki kursi.";

                    continue;
                }

                $seat = $lockedSeats->get($sId);
                if (! $seat) {
                    $errors["allocations.{$index}.seat_id"] = 'Kursi yang dipilih tidak valid atau berada pada koordinat gang.';

                    continue;
                }

                // Pastikan kursi sah dan tersedia (peserta dilarang memesan VIP/Panitia/Diblokir)
                $isAdmin = ($user->role === UserRole::SuperAdmin);
                try {
                    KegiatanSeat::pastikanBisaDipesan($seat, $isAdmin, "allocations.{$index}.seat_id");
                } catch (ValidationException $e) {
                    $errors["allocations.{$index}.seat_id"] = $e->validator->errors()->first("allocations.{$index}.seat_id");

                    continue;
                }
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $updatedRegistrations = [];

            foreach ($allocations as $item) {
                $reg = $lockedRegistrations->get($item['registration_id']);
                $seat = $lockedSeats->get($item['seat_id']);

                // Lepas kursi lama jika ada
                if ($reg->seat_id && (int) $reg->seat_id !== (int) $seat->id) {
                    KegiatanSeat::whereKey($reg->seat_id)
                        ->where('status', SeatStatus::Booked->value)
                        ->update(['status' => SeatStatus::Available->value]);
                }

                // Kunci status kursi
                $seat->update(['status' => SeatStatus::Booked]);

                // Generate penomoran tiket definitif
                $nomorTiket = KegiatanRegistration::generateNomorTiket($kegiatan->id, $seat->seat_code);

                // Update baris registrasi
                $reg->update([
                    'seat_id' => $seat->id,
                    'nomor_tiket' => $nomorTiket,
                    'needs_seat_reselection' => false,
                ]);

                $updatedRegistrations[] = $reg;
            }

            DB::commit();

            // Memicu event pengiriman notifikasi/email tiket ke masing-masing peserta
            foreach ($updatedRegistrations as $reg) {
                try {
                    $targetEmail = $reg->participantUser?->email ?? ($reg->delegation_data['email'] ?? null);
                    if ($targetEmail) {
                        Mail::to($targetEmail)->send(new TicketIssuedMail($reg));
                    }
                } catch (\Throwable $e) {
                    // ignore mail failure
                }
            }

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Kursi berhasil dialokasikan! E-Tiket telah diterbitkan.',
                    'registrations' => $updatedRegistrations,
                ]);
            }

            return redirect()->route('dashboard.kegiatan.saya')
                ->with('success', 'Kursi berhasil dipilihkan untuk peserta! E-Tiket resmi telah terbit.');
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw ValidationException::withMessages([
                'allocations' => 'Terjadi kesalahan saat memproses alokasi kursi: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Samarkan email (misal: "bu***i@gmail.com") untuk menjaga privasi peserta.
     */
    protected function maskEmail(?string $email): string
    {
        if (! $email || ! str_contains($email, '@')) {
            return '***@***.com';
        }

        [$userPart, $domain] = explode('@', $email, 2);
        $len = strlen($userPart);

        if ($len <= 2) {
            $maskedUser = substr($userPart, 0, 1).str_repeat('*', 3);
        } else {
            $maskedUser = substr($userPart, 0, 2).str_repeat('*', min(4, max(1, $len - 3))).substr($userPart, -1);
        }

        return $maskedUser.'@'.$domain;
    }
}
