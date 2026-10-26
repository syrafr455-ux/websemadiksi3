<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\PeranSertifikat;
use App\Models\Kegiatan;
use App\Models\KegiatanPresensi;
use App\Models\KegiatanRegistration;
use App\Models\Sertifikat;
use App\Services\CertificateGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PresensiController extends Controller
{
    /**
     * Phase 1: E-Ticket Check-In (Awal)
     * Digunakan oleh panitia/admin atau scanner untuk validasi kehadiran tiket di awal kegiatan.
     */
    public function checkInTicket(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'ticket_code' => ['nullable', 'string', 'max:100'],
            'registration_id' => ['nullable', 'integer'],
        ]);

        $ticketCode = $validated['ticket_code'] ?? null;
        $regId = $validated['registration_id'] ?? null;

        if (! $ticketCode && ! $regId) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Kode tiket atau ID registrasi wajib diisi.'], 422);
            }

            return back()->with('error', 'Kode tiket atau ID registrasi wajib diisi.');
        }

        $query = KegiatanRegistration::with(['kegiatan', 'seat', 'participantUser', 'bookerUser']);
        if ($ticketCode) {
            $query->where('nomor_tiket', trim($ticketCode));
        } else {
            $query->where('id', $regId);
        }

        $registration = $query->first();

        if (! $registration) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'E-Tiket tidak ditemukan dalam basis data.'], 404);
            }

            return back()->with('error', 'E-Tiket tidak ditemukan dalam basis data.');
        }

        // Tandai status kehadiran
        $alreadyCheckedIn = ($registration->status_kehadiran === AttendanceStatus::CheckedIn || $registration->status_kehadiran?->value === 'checked_in');
        if (! $alreadyCheckedIn) {
            $registration->status_kehadiran = AttendanceStatus::CheckedIn;
            $registration->checked_in_at = now();
            $registration->save();
        }

        $participantName = $registration->participant_name ?: ($registration->participantUser?->name ?? 'Peserta');
        $seatCode = $registration->seat?->seat_code ?? 'Tanpa Kursi Tetap';
        $kegiatanTitle = $registration->kegiatan?->nama_kegiatan ?? 'Kegiatan';

        $data = [
            'success' => true,
            'message' => $alreadyCheckedIn ? 'E-Tiket sudah pernah check-in sebelumnya.' : 'Check-In E-Tiket Berhasil!',
            'already_checked_in' => $alreadyCheckedIn,
            'ticket_code' => $registration->nomor_tiket,
            'participant_name' => $participantName,
            'seat_code' => $seatCode,
            'kegiatan_nama' => $kegiatanTitle,
            'checked_in_at' => now()->toIso8601String(),
        ];

        if ($request->wantsJson()) {
            return response()->json($data);
        }

        return back()->with('success', "Check-In berhasil: {$participantName} ({$seatCode})");
    }

    /**
     * Memverifikasi syarat presensi akhir fase 2:
     * (a) Sesi presensi akhir terbuka dan dalam jendela waktu.
     * (b) Peserta terdaftar dengan tiket sah dan sudah check-in fase 1.
     * (c) Ada izin sisi server yang hanya dibuat oleh pendaratan QR dengan token valid (kedaluwarsa 15 menit).
     *
     * @return array{0: bool, 1: JsonResponse|RedirectResponse|null, 2: ?KegiatanRegistration}
     */
    protected function verifikasiAksesPresensiAkhir(Request $request, Kegiatan $kegiatan): array
    {
        // (a) Sesi presensi akhir terbuka dan sedang dalam jendela waktunya
        if (! $kegiatan->isPresensiAkhirOpen()) {
            $msg = 'Sesi presensi akhir belum dibuka atau sudah ditutup oleh panitia.';
            if ($request->wantsJson()) {
                return [false, response()->json(['success' => false, 'message' => $msg], 422), null];
            }

            return [false, redirect()->route('dashboard.kegiatan.saya')->with('error', $msg), null];
        }

        // (b) Peserta terdaftar dengan tiket sah
        $user = Auth::user();
        if (! $user) {
            $msg = 'Pindai QR di layar acara untuk presensi akhir.';
            if ($request->wantsJson()) {
                return [false, response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401), null];
            }

            return [false, redirect()->route('dashboard.kegiatan.saya')->with('error', $msg), null];
        }

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
            $msg = 'Anda tidak terdaftar dalam kegiatan ini dengan tiket yang sah.';
            if ($request->wantsJson()) {
                return [false, response()->json(['success' => false, 'message' => $msg], 422), null];
            }

            return [false, redirect()->route('dashboard.kegiatan.saya')->with('error', $msg), null];
        }

        // (b) Syarat mutlak: Sudah absen awal (offline: check-in; daring: gabung; hybrid: check-in atau gabung)
        $isCheckedInFase1 = ($registration->status_kehadiran === AttendanceStatus::CheckedIn
            || $registration->status_kehadiran?->value === AttendanceStatus::CheckedIn->value
            || $registration->checked_in_at !== null);
        $sudahGabung = $kegiatan->gabungs()->where('registration_id', $registration->id)->exists();

        if ($kegiatan->isOnline() && ! $sudahGabung) {
            $msg = 'Kehadiran awal belum tercatat. Anda belum tercatat bergabung ke ruang pertemuan daring.';
            if ($request->wantsJson()) {
                return [false, response()->json(['success' => false, 'message' => $msg], 422), null];
            }

            return [false, redirect()->route('dashboard.kegiatan.saya')->with('error', $msg), null];
        }

        if ($kegiatan->isHybrid() && ! $isCheckedInFase1 && ! $sudahGabung) {
            $msg = 'Kehadiran awal belum tercatat (belum check-in tiket fisik maupun bergabung ruang daring).';
            if ($request->wantsJson()) {
                return [false, response()->json(['success' => false, 'message' => $msg], 422), null];
            }

            return [false, redirect()->route('dashboard.kegiatan.saya')->with('error', $msg), null];
        }

        if (! $kegiatan->isDaringOrHybrid() && ! $isCheckedInFase1) {
            $msg = 'Check-in awal belum tercatat. Hubungi panitia.';
            if ($request->wantsJson()) {
                return [false, response()->json(['success' => false, 'message' => $msg], 422), null];
            }

            return [false, redirect()->route('dashboard.kegiatan.saya')->with('error', $msg), null];
        }

        // (c) Ada izin sisi server yang hanya dibuat oleh pendaratan QR dengan token valid
        $permissionKey = "presensi_akhir_unlocked_{$kegiatan->id}";
        $permission = session($permissionKey);

        $hasValidPermission = false;
        if (is_array($permission)) {
            $isCorrectKegiatan = isset($permission['kegiatan_id']) && (int) $permission['kegiatan_id'] === (int) $kegiatan->id;
            $notExpired = isset($permission['expires_at']) && now()->timestamp <= (int) $permission['expires_at'];
            $hasValidPermission = $isCorrectKegiatan && $notExpired;
        } elseif ($permission === true) {
            $hasValidPermission = true;
        }

        if (! $hasValidPermission) {
            session()->forget($permissionKey);
            $msg = 'Pindai QR di layar acara untuk presensi akhir.';
            if ($request->wantsJson()) {
                return [false, response()->json(['success' => false, 'message' => $msg], 403), null];
            }

            return [false, redirect()->route('dashboard.kegiatan.saya')->with('error', $msg), null];
        }

        return [true, null, $registration];
    }

    /**
     * Phase 2: Form Presensi Mandiri dengan Touchscreen Signature Canvas (Selesai)
     */
    public function showSelfAttendance(Request $request, int $kegiatanId): View|RedirectResponse
    {
        $kegiatan = Kegiatan::findOrFail($kegiatanId);

        [$allowed, $response, $registration] = $this->verifikasiAksesPresensiAkhir($request, $kegiatan);
        if (! $allowed) {
            return $response;
        }

        $user = Auth::user();

        // Cari apakah user sudah pernah presensi sebelumnya
        $existingPresensi = null;
        if ($user) {
            $existingPresensi = KegiatanPresensi::with('sertifikat')
                ->where('kegiatan_id', $kegiatan->id)
                ->where(function ($q) use ($user, $registration) {
                    $q->where('user_id', $user->id)
                        ->orWhere('email', $user->email);
                    if ($user->is_unusa && $user->nim) {
                        $q->orWhere('nim', $user->nim);
                    }
                    if ($registration?->participant_identity) {
                        $q->orWhere('nim', $registration->participant_identity);
                    }
                })
                ->latest()
                ->first();
        }

        return view('presensi.self-attendance', [
            'kegiatan' => $kegiatan,
            'user' => $user,
            'existingPresensi' => $existingPresensi,
            'title' => "Presensi Mandiri & Tanda Tangan: {$kegiatan->nama_kegiatan}",
        ]);
    }

    /**
     * Phase 2: Submit Presensi Mandiri + Simpan Signature Canvas PNG + Auto Generate Sertifikat
     */
    public function submitSelfAttendance(
        Request $request,
        int $kegiatanId,
        CertificateGeneratorService $certificateGenerator
    ): JsonResponse|RedirectResponse {
        $kegiatan = Kegiatan::findOrFail($kegiatanId);

        [$allowed, $response, $registration] = $this->verifikasiAksesPresensiAkhir($request, $kegiatan);
        if (! $allowed) {
            return $response;
        }

        $rules = [
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'nim' => ['required', 'string', 'max:50'],
            'prodi' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255'],
            'nomor_telepon' => ['required', 'string', 'max:30'],
            'signature' => ['required', 'string'], // base64 PNG dari signature canvas
        ];

        if ($kegiatan->wajib_tangkapan_layar) {
            $rules['tangkapan_layar'] = [
                'required',
                'file',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:3072',
            ];
        } else {
            $rules['tangkapan_layar'] = [
                'nullable',
                'file',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:3072',
            ];
        }

        $validated = $request->validate($rules, [
            'tangkapan_layar.required' => 'Bukti tangkapan layar (screenshot) kehadiran wajib diunggah.',
            'tangkapan_layar.image' => 'Bukti kehadiran harus berupa gambar (JPG, PNG, atau WebP).',
            'tangkapan_layar.mimes' => 'Format gambar yang didukung hanya JPG, JPEG, PNG, dan WebP.',
            'tangkapan_layar.max' => 'Ukuran gambar tangkapan layar maksimal 3 MB.',
        ]);

        $signatureData = $validated['signature'];

        // Validasi dan decode base64 PNG tanda tangan
        if (! preg_match('/^data:image\/(\w+);base64,/', $signatureData)) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Format tanda tangan digital tidak valid (harus data URL PNG).'], 422);
            }

            return back()->with('error', 'Format tanda tangan digital tidak valid. Silakan tanda tangani ulang canvas.');
        }

        $imageBinary = base64_decode(substr($signatureData, strpos($signatureData, ',') + 1));
        if ($imageBinary === false || strlen($imageBinary) < 10) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Tanda tangan digital tidak terbaca atau kosong.'], 422);
            }

            return back()->with('error', 'Tanda tangan digital kosong. Silakan bubuhkan tanda tangan Anda pada canvas.');
        }

        // Simpan PNG ke storage public
        $filename = 'sig_'.Str::uuid().'.png';
        $storageDir = "signatures/{$kegiatan->id}";
        $signaturePath = "{$storageDir}/{$filename}";
        Storage::disk('public')->put($signaturePath, $imageBinary);

        // Simpan tangkapan layar ke storage private (disk local)
        $tangkapanLayarPath = null;
        if ($request->hasFile('tangkapan_layar')) {
            $file = $request->file('tangkapan_layar');
            $ssName = 'ss_'.Str::random(40).'.'.$file->getClientOriginalExtension();
            $tangkapanLayarPath = $file->storeAs("presensi/screenshots/{$kegiatan->id}", $ssName, 'local');
        }

        $user = Auth::user();
        $nim = trim($validated['nim']);
        $email = trim($validated['email']);

        // Update status kehadiran registrasi jika belum
        if ($registration) {
            $registration->status_kehadiran = AttendanceStatus::CheckedIn;
            $registration->checked_in_at = $registration->checked_in_at ?? now();
            $registration->save();
        }

        // Simpan ke kegiatan_presensi
        $presensi = KegiatanPresensi::updateOrCreate(
            [
                'kegiatan_id' => $kegiatan->id,
                'nim' => $nim,
            ],
            [
                'user_id' => $user?->id ?? ($registration?->participant_user_id ?: null),
                'registration_id' => $registration?->id,
                'nama_lengkap' => trim($validated['nama_lengkap']),
                'prodi' => trim($validated['prodi']),
                'email' => $email,
                'nomor_telepon' => trim($validated['nomor_telepon']),
                'signature_path' => $signaturePath,
                'tangkapan_layar_path' => $tangkapanLayarPath,
                'check_in_at' => now(),
            ]
        );

        // Generate sertifikat resmi dinamis langsung
        $sertifikat = $certificateGenerator->generate(
            $kegiatan,
            $presensi,
            PeranSertifikat::Peserta,
            $presensi->id
        );

        // Hapus izin sisi server setelah presensi tersimpan agar tidak bisa dipakai dua kali
        session()->forget("presensi_akhir_unlocked_{$kegiatan->id}");

        $responseData = [
            'success' => true,
            'message' => 'Presensi mandiri berhasil dicatat dan Sertifikat Resmi Anda telah diterbitkan.',
            'presensi_id' => $presensi->id,
            'nomor_sertifikat' => $sertifikat->nomor_sertifikat,
            'certificate_url' => $sertifikat->file_url,
            'verification_url' => $sertifikat->verification_url,
            'document_hash' => $sertifikat->document_hash,
        ];

        if ($request->wantsJson()) {
            return response()->json($responseData);
        }

        return redirect()->route('dashboard.kegiatan.saya')
            ->with('success', 'Presensi mandiri berhasil! Sertifikat Anda telah selesai diterbitkan.')
            ->with('sertifikat', $sertifikat);
    }

    /**
     * Tampilkan berkas tangkapan layar presensi yang tersimpan di disk privat (khusus super admin).
     */
    public function tangkapanLayarPrivat(int $id)
    {
        $presensi = KegiatanPresensi::findOrFail($id);
        Gate::authorize('managePresensiAkhir', KegiatanPresensi::class);

        if (! $presensi->tangkapan_layar_path || ! Storage::disk('local')->exists($presensi->tangkapan_layar_path)) {
            abort(404, 'Bukti tangkapan layar tidak ditemukan.');
        }

        return Storage::disk('local')->response($presensi->tangkapan_layar_path);
    }
}
