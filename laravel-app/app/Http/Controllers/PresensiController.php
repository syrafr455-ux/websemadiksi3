<?php

namespace App\Http\Controllers;

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
        $alreadyCheckedIn = ($registration->status_kehadiran === \App\Enums\AttendanceStatus::CheckedIn || $registration->status_kehadiran?->value === 'checked_in');
        if (! $alreadyCheckedIn) {
            $registration->status_kehadiran = \App\Enums\AttendanceStatus::CheckedIn;
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
     * Phase 2: Form Presensi Mandiri dengan Touchscreen Signature Canvas (Selesai)
     */
    public function showSelfAttendance(Request $request, int $kegiatanId): View
    {
        $kegiatan = Kegiatan::findOrFail($kegiatanId);
        $user = Auth::user();

        // Cari apakah user sudah pernah presensi sebelumnya
        $existingPresensi = null;
        if ($user) {
            $existingPresensi = KegiatanPresensi::with('sertifikat')
                ->where('kegiatan_id', $kegiatan->id)
                ->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                      ->orWhere('nim', $user->nim)
                      ->orWhere('email', $user->email);
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

        $validated = $request->validate([
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'nim' => ['required', 'string', 'max:50'],
            'prodi' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255'],
            'nomor_telepon' => ['required', 'string', 'max:30'],
            'signature' => ['required', 'string'], // base64 PNG dari signature canvas
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
        $filename = 'sig_' . Str::uuid() . '.png';
        $storageDir = "signatures/{$kegiatan->id}";
        $signaturePath = "{$storageDir}/{$filename}";
        Storage::disk('public')->put($signaturePath, $imageBinary);

        // Cari relasi registrasi tiket jika ada
        $user = Auth::user();
        $nim = trim($validated['nim']);
        $email = trim($validated['email']);

        $registration = KegiatanRegistration::where('kegiatan_id', $kegiatan->id)
            ->where(function ($q) use ($user, $nim) {
                if ($user) {
                    $q->where('participant_user_id', $user->id)
                      ->orWhere('booker_user_id', $user->id);
                }
                $q->orWhere('participant_identity', $nim);
            })
            ->first();

        // Update status kehadiran registrasi
        if ($registration) {
            $registration->status_kehadiran = \App\Enums\AttendanceStatus::CheckedIn;
            $registration->checked_in_at = now();
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

        return redirect()->route('kegiatan.presensi.show', $kegiatan->id)
            ->with('success', 'Presensi mandiri berhasil! Sertifikat Anda telah selesai diterbitkan.')
            ->with('sertifikat', $sertifikat);
    }
}
