<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AttendanceStatus;
use App\Enums\PeranSertifikat;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Kegiatan;
use App\Models\KegiatanPresensi;
use App\Models\KegiatanRegistration;
use App\Services\CertificateGeneratorService;
use App\Services\Kegiatan\PresensiAkhirTokenService;
use App\Services\Kegiatan\PresensiTicketScannerService;
use App\Support\Wib;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class PresensiScannerController extends Controller
{
    /**
     * Halaman antarmuka pemindai tiket (presensi awal) untuk satu kegiatan.
     */
    public function scanner(Request $request, Kegiatan $kegiatan): View
    {
        Gate::authorize('scanTicket', KegiatanPresensi::class);

        $totalQuota = (int) $kegiatan->kuota;
        $totalRegistered = $kegiatan->registrations()->confirmed()->count();
        $totalCheckedIn = $kegiatan->registrations()->where('status_kehadiran', AttendanceStatus::CheckedIn->value)->count();

        // 10 riwayat pemindaian terakhir untuk kegiatan ini
        $recentLogs = AuditLog::whereIn('action', ['ticket_checkin', 'ticket_checkin_manual', 'ticket_checkin_cancelled'])
            ->where('metadata->kegiatan_id', $kegiatan->id)
            ->latest('id')
            ->take(10)
            ->with(['user'])
            ->get();

        // Daftar pendaftar untuk tabel check-in manual cadangan
        $query = $kegiatan->registrations()->with(['seat', 'participantUser'])->confirmed();
        if ($request->filled('q')) {
            $q = trim((string) $request->input('q'));
            $query->where(function ($w) use ($q) {
                $w->where('participant_name', 'ilike', "%{$q}%")
                    ->orWhere('participant_identity', 'ilike', "%{$q}%")
                    ->orWhere('nomor_tiket', 'ilike', "%{$q}%");
            });
        }
        $participants = $query->orderBy('nomor_tiket')->paginate(20)->withQueryString();

        return view('admin.attendances.scanner', [
            'kegiatan' => $kegiatan,
            'totalQuota' => $totalQuota,
            'totalRegistered' => $totalRegistered,
            'totalCheckedIn' => $totalCheckedIn,
            'recentLogs' => $recentLogs,
            'participants' => $participants,
            'title' => "Pemindai Tiket: {$kegiatan->nama_kegiatan}",
        ]);
    }

    /**
     * Endpoint API pemindaian tiket (kamera / keyboard USB / input manual).
     */
    public function scan(
        Request $request,
        Kegiatan $kegiatan,
        PresensiTicketScannerService $scannerService
    ): JsonResponse {
        Gate::authorize('scanTicket', KegiatanPresensi::class);

        $validated = $request->validate([
            'ticket_code' => ['nullable', 'string', 'max:500'],
            'raw_input' => ['nullable', 'string', 'max:500'],
            'method' => ['nullable', 'string', 'in:kamera,keyboard,manual'],
        ]);

        $rawInput = (string) ($validated['ticket_code'] ?? $validated['raw_input'] ?? '');
        $method = (string) ($validated['method'] ?? 'kamera');

        $result = $scannerService->scan(
            kegiatan: $kegiatan,
            rawInput: $rawInput,
            officer: $request->user(),
            method: $method,
            alasan: null,
            ip: $request->ip()
        );

        $statusCode = match ($result['status']) {
            'DITERIMA' => 200,
            'SUDAH_CHECK_IN' => 200,
            default => 200, // Kembalikan 200 agar UI scanner langsung merender status penolakan tanpa exception HTTP
        };

        return response()->json($result, $statusCode);
    }

    /**
     * Check-in manual per peserta di tabel cadangan dengan alasan wajib.
     */
    public function manualCheckIn(
        Request $request,
        Kegiatan $kegiatan,
        PresensiTicketScannerService $scannerService
    ): JsonResponse|RedirectResponse {
        Gate::authorize('scanTicket', KegiatanPresensi::class);

        $validated = $request->validate([
            'registration_id' => ['required', 'integer'],
            'alasan' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'alasan.required' => 'Alasan check-in manual wajib diisi.',
            'alasan.min' => 'Alasan check-in manual minimal 3 karakter.',
        ]);

        $registration = KegiatanRegistration::where('id', $validated['registration_id'])
            ->where('kegiatan_id', $kegiatan->id)
            ->firstOrFail();

        $result = $scannerService->manualCheckIn(
            registration: $registration,
            officer: $request->user(),
            alasan: $validated['alasan'],
            ip: $request->ip()
        );

        if ($request->wantsJson()) {
            return response()->json($result);
        }

        return back()->with('success', $result['message']);
    }

    /**
     * Batalkan check-in peserta dengan alasan wajib.
     */
    public function cancelCheckIn(
        Request $request,
        Kegiatan $kegiatan,
        PresensiTicketScannerService $scannerService
    ): JsonResponse|RedirectResponse {
        Gate::authorize('scanTicket', KegiatanPresensi::class);

        $validated = $request->validate([
            'registration_id' => ['required', 'integer'],
            'alasan' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'alasan.required' => 'Alasan pembatalan check-in wajib diisi.',
            'alasan.min' => 'Alasan pembatalan check-in minimal 3 karakter.',
        ]);

        $registration = KegiatanRegistration::where('id', $validated['registration_id'])
            ->where('kegiatan_id', $kegiatan->id)
            ->firstOrFail();

        $result = $scannerService->cancelCheckIn(
            registration: $registration,
            officer: $request->user(),
            alasan: $validated['alasan'],
            ip: $request->ip()
        );

        if ($request->wantsJson()) {
            return response()->json($result);
        }

        return back()->with('success', $result['message']);
    }

    /**
     * Buka sesi presensi akhir untuk satu kegiatan.
     */
    public function bukaPresensiAkhir(Request $request, Kegiatan $kegiatan): JsonResponse|RedirectResponse
    {
        Gate::authorize('managePresensiAkhir', KegiatanPresensi::class);

        $validated = $request->validate([
            'waktu_mulai' => ['nullable', 'string'],
            'waktu_selesai' => ['nullable', 'string'],
            'presensi_akhir_mulai' => ['nullable', 'string'],
            'presensi_akhir_selesai' => ['nullable', 'string'],
        ]);

        $inputMulai = $validated['waktu_mulai'] ?? $validated['presensi_akhir_mulai'] ?? null;
        $inputSelesai = $validated['waktu_selesai'] ?? $validated['presensi_akhir_selesai'] ?? null;

        $mulai = null;
        if (! empty($inputMulai)) {
            $mulai = Wib::keUtc($inputMulai);
        } else {
            $mulai = $kegiatan->tanggal_selesai
                ? $kegiatan->tanggal_selesai->copy()->subMinutes(15)
                : now();
        }

        $selesai = null;
        if (! empty($inputSelesai)) {
            $selesai = Wib::keUtc($inputSelesai);
        } else {
            $selesai = $kegiatan->tanggal_selesai
                ? $kegiatan->tanggal_selesai->copy()->addMinutes(60)
                : now()->addMinutes(60);
        }

        $kegiatan->update([
            'presensi_akhir_dibuka' => true,
            'presensi_akhir_mulai' => $mulai,
            'presensi_akhir_selesai' => $selesai,
        ]);

        AuditLog::record(
            userId: $request->user()->id,
            action: 'presensi_akhir_buka',
            description: "Membuka sesi presensi akhir untuk kegiatan {$kegiatan->nama_kegiatan}",
            subject: $kegiatan,
            metadata: [
                'waktu_mulai_utc' => $mulai?->toIso8601String(),
                'waktu_selesai_utc' => $selesai?->toIso8601String(),
            ]
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Sesi presensi akhir berhasil dibuka.',
                'status' => 'dibuka',
            ]);
        }

        return back()->with('success', 'Sesi presensi akhir berhasil dibuka.');
    }

    /**
     * Tutup sesi presensi akhir sekarang juga.
     */
    public function tutupPresensiAkhir(Request $request, Kegiatan $kegiatan): JsonResponse|RedirectResponse
    {
        Gate::authorize('managePresensiAkhir', KegiatanPresensi::class);

        $kegiatan->update([
            'presensi_akhir_dibuka' => false,
            'presensi_akhir_selesai' => now(),
        ]);

        AuditLog::record(
            userId: $request->user()->id,
            action: 'presensi_akhir_tutup',
            description: "Menutup sesi presensi akhir untuk kegiatan {$kegiatan->nama_kegiatan}",
            subject: $kegiatan
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Sesi presensi akhir telah ditutup.',
                'status' => 'ditutup',
            ]);
        }

        return back()->with('success', 'Sesi presensi akhir telah ditutup.');
    }

    /**
     * Perpanjang waktu presensi akhir.
     */
    public function perpanjangPresensiAkhir(Request $request, Kegiatan $kegiatan): JsonResponse|RedirectResponse
    {
        Gate::authorize('managePresensiAkhir', KegiatanPresensi::class);

        $validated = $request->validate([
            'menit' => ['nullable', 'integer', 'min:5', 'max:240'],
            'waktu_selesai' => ['nullable', 'string'],
        ]);

        if (! empty($validated['waktu_selesai'])) {
            $selesaiBaru = Wib::keUtc($validated['waktu_selesai']);
        } else {
            $menit = (int) ($validated['menit'] ?? 30);
            $base = ($kegiatan->presensi_akhir_selesai && $kegiatan->presensi_akhir_selesai->gt(now()))
                ? $kegiatan->presensi_akhir_selesai
                : now();
            $selesaiBaru = $base->copy()->addMinutes($menit);
        }

        $kegiatan->update([
            'presensi_akhir_dibuka' => true,
            'presensi_akhir_selesai' => $selesaiBaru,
        ]);

        AuditLog::record(
            userId: $request->user()->id,
            action: 'presensi_akhir_perpanjang',
            description: "Memperpanjang sesi presensi akhir kegiatan {$kegiatan->nama_kegiatan}",
            subject: $kegiatan,
            metadata: [
                'waktu_selesai_baru_utc' => $selesaiBaru->toIso8601String(),
            ]
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Sesi presensi akhir berhasil diperpanjang.',
                'waktu_selesai' => $selesaiBaru->toIso8601String(),
            ]);
        }

        return back()->with('success', 'Sesi presensi akhir berhasil diperpanjang.');
    }

    /**
     * Tandai presensi akhir manual oleh admin dengan alasan wajib.
     */
    public function manualPresensiAkhir(
        Request $request,
        Kegiatan $kegiatan,
        CertificateGeneratorService $certService
    ): JsonResponse|RedirectResponse {
        Gate::authorize('managePresensiAkhir', KegiatanPresensi::class);

        $validated = $request->validate([
            'registration_id' => ['required', 'integer'],
            'alasan' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'alasan.required' => 'Alasan presensi akhir manual wajib diisi.',
            'alasan.min' => 'Alasan presensi akhir manual minimal 3 karakter.',
        ]);

        $registration = KegiatanRegistration::where('id', $validated['registration_id'])
            ->where('kegiatan_id', $kegiatan->id)
            ->firstOrFail();

        $participantUser = $registration->participantUser;
        $nim = $registration->participant_identity ?: ($participantUser?->nim ?? 'MANUAL-'.$registration->id);
        $nama = $registration->participant_name ?: ($participantUser?->name ?? 'Peserta');
        $prodi = $participantUser?->prodi ?? 'Umum';
        $email = $participantUser?->email ?? ($registration->bookerUser?->email ?? 'peserta@unusa.ac.id');
        $phone = $registration->participant_phone ?: ($participantUser?->phone ?? '-');

        // Placeholder signature untuk manual attendance
        $sigDir = "signatures/{$kegiatan->id}";
        $sigFilename = 'sig_manual_'.Str::uuid().'.png';
        $sigPath = "{$sigDir}/{$sigFilename}";

        // 1x1 transparent PNG jika belum ada
        if (! Storage::disk('public')->exists($sigPath)) {
            $pngBinary = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
            Storage::disk('public')->put($sigPath, $pngBinary);
        }

        // Tandai status registrasi
        $registration->update([
            'status_kehadiran' => AttendanceStatus::CheckedIn->value,
            'checked_in_at' => $registration->checked_in_at ?? now(),
        ]);

        // Simpan / update presensi akhir
        $presensi = KegiatanPresensi::updateOrCreate(
            [
                'kegiatan_id' => $kegiatan->id,
                'nim' => $nim,
            ],
            [
                'user_id' => $registration->participant_user_id ?: null,
                'registration_id' => $registration->id,
                'nama_lengkap' => $nama,
                'prodi' => $prodi,
                'email' => $email,
                'nomor_telepon' => $phone,
                'signature_path' => $sigPath,
                'check_in_at' => now(),
            ]
        );

        // Auto generate sertifikat
        $sertifikat = null;
        try {
            $sertifikat = $certService->generate($kegiatan, $presensi, PeranSertifikat::Peserta, $presensi->id);
        } catch (\Throwable) {
            // Abaikan jika template belum disetel
        }

        AuditLog::record(
            userId: $request->user()->id,
            action: 'presensi_akhir_manual',
            description: "Tandai presensi akhir manual untuk {$nama} ({$registration->nomor_tiket}). Alasan: {$validated['alasan']}",
            subject: $presensi,
            metadata: [
                'kegiatan_id' => $kegiatan->id,
                'registration_id' => $registration->id,
                'alasan' => $validated['alasan'],
            ]
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Presensi akhir manual untuk {$nama} berhasil dicatat.",
                'presensi_id' => $presensi->id,
                'nomor_sertifikat' => $sertifikat?->nomor_sertifikat,
            ]);
        }

        return back()->with('success', "Presensi akhir manual untuk {$nama} berhasil dicatat.");
    }

    /**
     * Halaman layar penuh proyektor: menampilkan QR dinamis yang berganti tiap 30 detik.
     */
    public function proyektor(
        Request $request,
        Kegiatan $kegiatan,
        PresensiAkhirTokenService $tokenService
    ): View {
        Gate::authorize('managePresensiAkhir', KegiatanPresensi::class);

        $token = $tokenService->generateToken($kegiatan->id);
        $secondsRemaining = $tokenService->secondsRemainingInSlot();

        $totalCheckedInFase1 = $kegiatan->registrations()
            ->where('status_kehadiran', AttendanceStatus::CheckedIn->value)
            ->count();
        $totalPresensiFase2 = $kegiatan->presensis()->count();

        $tautanPresensiAkhir = null;
        if ($kegiatan->isDaringOrHybrid() && $kegiatan->izin_tautan_presensi_akhir) {
            $tautanPresensiAkhir = URL::temporarySignedRoute(
                'kegiatan.presensi-akhir.tautan',
                $kegiatan->presensi_akhir_selesai && $kegiatan->presensi_akhir_selesai->gt(now()) ? $kegiatan->presensi_akhir_selesai : now()->addHours(2),
                ['kegiatan' => $kegiatan->id]
            );
        }

        return view('admin.attendances.proyektor', [
            'kegiatan' => $kegiatan,
            'initialToken' => $token,
            'initialSeconds' => $secondsRemaining,
            'totalCheckedInFase1' => $totalCheckedInFase1,
            'totalPresensiFase2' => $totalPresensiFase2,
            'tautanPresensiAkhir' => $tautanPresensiAkhir,
            'title' => "Layar Presensi Akhir: {$kegiatan->nama_kegiatan}",
        ]);
    }

    /**
     * Endpoint API JSON untuk live reload token QR dan live counter proyektor.
     */
    public function proyektorToken(
        Request $request,
        Kegiatan $kegiatan,
        PresensiAkhirTokenService $tokenService
    ): JsonResponse {
        Gate::authorize('managePresensiAkhir', KegiatanPresensi::class);

        $token = $tokenService->generateToken($kegiatan->id);
        $secondsRemaining = $tokenService->secondsRemainingInSlot();

        $scanUrl = route('kegiatan.presensi-akhir.scan', [
            'kegiatan' => $kegiatan->id,
            'token' => $token,
        ]);

        $totalCheckedInFase1 = $kegiatan->registrations()
            ->where('status_kehadiran', AttendanceStatus::CheckedIn->value)
            ->count();
        $totalPresensiFase2 = $kegiatan->presensis()->count();

        $qrSvg = class_exists(QrCode::class)
            ? (string) QrCode::format('svg')->size(380)->margin(1)->errorCorrection('M')->generate($scanUrl)
            : null;

        $tautanPresensiAkhir = null;
        if ($kegiatan->isDaringOrHybrid() && $kegiatan->izin_tautan_presensi_akhir) {
            $tautanPresensiAkhir = URL::temporarySignedRoute(
                'kegiatan.presensi-akhir.tautan',
                $kegiatan->presensi_akhir_selesai && $kegiatan->presensi_akhir_selesai->gt(now()) ? $kegiatan->presensi_akhir_selesai : now()->addHours(2),
                ['kegiatan' => $kegiatan->id]
            );
        }

        return response()->json([
            'token' => $token,
            'seconds_remaining' => $secondsRemaining,
            'scan_url' => $scanUrl,
            'qr_svg' => $qrSvg,
            'fase1_count' => $totalCheckedInFase1,
            'fase2_count' => $totalPresensiFase2,
            'session_status' => $kegiatan->statusPresensiAkhir(),
            'tautan_presensi_akhir' => $tautanPresensiAkhir,
        ]);
    }

    /**
     * Endpoint alur peserta memindai QR presensi akhir dari layar proyektor.
     */
    public function scanPresensiAkhir(
        Request $request,
        Kegiatan $kegiatan,
        PresensiAkhirTokenService $tokenService
    ): RedirectResponse {
        $token = (string) $request->input('token');

        // 1. Verifikasi token HMAC
        if (! $tokenService->verifyToken($kegiatan->id, $token)) {
            return redirect()->route('dashboard.index')
                ->with('error', 'Token QR presensi akhir telah kedaluwarsa atau tidak valid. Silakan pindai ulang dari layar proyektor.');
        }

        // 2. Cek apakah sesi presensi akhir dibuka
        if (! $kegiatan->isPresensiAkhirOpen()) {
            return redirect()->route('dashboard.index')
                ->with('error', 'Sesi presensi akhir belum dibuka atau sudah ditutup oleh panitia.');
        }

        $user = Auth::user();

        // 3. Cek pendaftaran sah peserta
        $registration = KegiatanRegistration::where('kegiatan_id', $kegiatan->id)
            ->where(function ($q) use ($user) {
                if ($user) {
                    $q->where('participant_user_id', $user->id)
                        ->orWhere('booker_user_id', $user->id);
                    if ($user->is_unusa && $user->nim) {
                        $q->orWhere('participant_identity', $user->nim);
                    }
                }
            })
            ->confirmed()
            ->first();

        if (! $registration) {
            return redirect()->route('dashboard.index')
                ->with('error', 'Anda tidak terdaftar dalam kegiatan ini dengan tiket yang sah.');
        }

        // 4. Syarat mutlak: SUDAH absen awal (offline: check-in; daring: gabung; hybrid: check-in atau gabung)
        $isCheckedInFase1 = ($registration->status_kehadiran === AttendanceStatus::CheckedIn
            || $registration->status_kehadiran?->value === AttendanceStatus::CheckedIn->value
            || $registration->checked_in_at !== null);
        $sudahGabung = $kegiatan->gabungs()->where('registration_id', $registration->id)->exists();

        if ($kegiatan->isOnline() && ! $sudahGabung) {
            return redirect()->route('dashboard.index')
                ->with('error', 'Kehadiran awal belum tercatat. Anda belum tercatat bergabung ke ruang pertemuan daring.');
        }

        if ($kegiatan->isHybrid() && ! $isCheckedInFase1 && ! $sudahGabung) {
            return redirect()->route('dashboard.index')
                ->with('error', 'Kehadiran awal belum tercatat (belum check-in tiket fisik maupun bergabung ruang daring).');
        }

        if (! $kegiatan->isDaringOrHybrid() && ! $isCheckedInFase1) {
            return redirect()->route('dashboard.index')
                ->with('error', 'Check-in awal belum tercatat. Hubungi panitia.');
        }

        // 5. Cek apakah sudah pernah menyelesaikan presensi fase 2
        $alreadyPresensi = KegiatanPresensi::where('kegiatan_id', $kegiatan->id)
            ->where(function ($q) use ($user, $registration) {
                if ($user) {
                    $q->where('user_id', $user->id);
                    if ($user->is_unusa && $user->nim) {
                        $q->orWhere('nim', $user->nim);
                    }
                }
                if ($registration->participant_identity) {
                    $q->orWhere('nim', $registration->participant_identity);
                }
            })
            ->exists();

        if ($alreadyPresensi) {
            session()->put("presensi_akhir_unlocked_{$kegiatan->id}", [
                'kegiatan_id' => $kegiatan->id,
                'expires_at' => now()->addMinutes(15)->timestamp,
            ]);

            return redirect()->route('kegiatan.presensi.show', $kegiatan->id)
                ->with('info', 'Anda sudah melakukan presensi akhir untuk kegiatan ini.');
        }

        // Set kunci buka kunci presensi akhir di sesi (berlaku 15 menit)
        session()->put("presensi_akhir_unlocked_{$kegiatan->id}", [
            'kegiatan_id' => $kegiatan->id,
            'expires_at' => now()->addMinutes(15)->timestamp,
        ]);

        return redirect()->route('kegiatan.presensi.show', $kegiatan->id)
            ->with('success', 'Verifikasi QR presensi akhir berhasil! Silakan bubuhkan tanda tangan digital Anda di bawah ini.');
    }

    /**
     * Endpoint alur peserta mendarat melalui tautan presensi akhir ber-HMAC untuk daring/hybrid.
     */
    public function presensiAkhirTautan(
        Request $request,
        Kegiatan $kegiatan
    ): RedirectResponse {
        if (! $request->hasValidSignature()) {
            return redirect()->route('dashboard.kegiatan.saya')
                ->with('error', 'Tautan presensi akhir tidak valid atau telah kedaluwarsa.');
        }

        if (! $kegiatan->isDaringOrHybrid()) {
            return redirect()->route('dashboard.kegiatan.saya')
                ->with('error', 'Tautan presensi akhir mandiri hanya berlaku untuk kegiatan daring atau hybrid.');
        }

        if (! $kegiatan->izin_tautan_presensi_akhir) {
            return redirect()->route('dashboard.kegiatan.saya')
                ->with('error', 'Presensi akhir melalui tautan langsung tidak diaktifkan oleh panitia.');
        }

        if (! $kegiatan->isPresensiAkhirOpen()) {
            return redirect()->route('dashboard.kegiatan.saya')
                ->with('error', 'Sesi presensi akhir belum dibuka atau sudah ditutup oleh panitia.');
        }

        $user = Auth::user();
        if (! $user) {
            return redirect()->route('login')
                ->with('error', 'Silakan masuk ke akun Anda terlebih dahulu untuk melakukan presensi akhir.');
        }

        if ($user->is_blocked) {
            return redirect()->route('dashboard.kegiatan.saya')
                ->with('error', 'Akun Anda sedang dibekukan.');
        }

        $registration = KegiatanRegistration::where('kegiatan_id', $kegiatan->id)
            ->where(function ($q) use ($user) {
                $q->where('participant_user_id', $user->id)
                    ->orWhere('booker_user_id', $user->id);
                if ($user->is_unusa && $user->nim) {
                    $q->orWhere('participant_identity', $user->nim);
                }
            })
            ->confirmed()
            ->first();

        if (! $registration) {
            return redirect()->route('dashboard.kegiatan.saya')
                ->with('error', 'Anda tidak terdaftar dalam kegiatan ini dengan tiket yang sah.');
        }

        // Syarat absen awal tercatat
        $isCheckedInFase1 = ($registration->status_kehadiran === AttendanceStatus::CheckedIn
            || $registration->status_kehadiran?->value === AttendanceStatus::CheckedIn->value
            || $registration->checked_in_at !== null);
        $sudahGabung = $kegiatan->gabungs()->where('registration_id', $registration->id)->exists();

        if ($kegiatan->isOnline() && ! $sudahGabung) {
            return redirect()->route('dashboard.kegiatan.saya')
                ->with('error', 'Kehadiran awal belum tercatat. Anda belum tercatat bergabung ke ruang pertemuan daring.');
        }

        if ($kegiatan->isHybrid() && ! $isCheckedInFase1 && ! $sudahGabung) {
            return redirect()->route('dashboard.kegiatan.saya')
                ->with('error', 'Kehadiran awal belum tercatat (belum check-in tiket fisik maupun bergabung ruang daring).');
        }

        $alreadyPresensi = KegiatanPresensi::where('kegiatan_id', $kegiatan->id)
            ->where(function ($q) use ($user, $registration) {
                if ($user) {
                    $q->where('user_id', $user->id);
                    if ($user->is_unusa && $user->nim) {
                        $q->orWhere('nim', $user->nim);
                    }
                }
                if ($registration->participant_identity) {
                    $q->orWhere('nim', $registration->participant_identity);
                }
            })
            ->exists();

        session()->put("presensi_akhir_unlocked_{$kegiatan->id}", [
            'kegiatan_id' => $kegiatan->id,
            'expires_at' => now()->addMinutes(15)->timestamp,
        ]);

        if ($alreadyPresensi) {
            return redirect()->route('kegiatan.presensi.show', $kegiatan->id)
                ->with('info', 'Anda sudah melakukan presensi akhir untuk kegiatan ini.');
        }

        return redirect()->route('kegiatan.presensi.show', $kegiatan->id)
            ->with('success', 'Verifikasi kehadiran daring berhasil! Silakan bubuhkan tanda tangan digital Anda di bawah ini.');
    }

    /**
     * Endpoint penyedia QR code SVG lokal/offline untuk tiket peserta.
     */
    public function renderTicketQr(string $nomorTiket): Response
    {
        $nomorTiket = trim($nomorTiket);

        if (class_exists(QrCode::class)) {
            $svg = QrCode::format('svg')
                ->size(250)
                ->margin(1)
                ->errorCorrection('M')
                ->generate($nomorTiket);
        } else {
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="250" height="250"><rect width="100%" height="100%" fill="#eee"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-family="monospace" font-size="12">QR Code</text></svg>';
        }

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
