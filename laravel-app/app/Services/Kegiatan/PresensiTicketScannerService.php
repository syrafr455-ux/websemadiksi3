<?php

namespace App\Services\Kegiatan;

use App\Enums\AttendanceStatus;
use App\Enums\KegiatanStatus;
use App\Models\AuditLog;
use App\Models\Kegiatan;
use App\Models\KegiatanRegistration;
use App\Models\User;
use App\Support\Wib;
use Illuminate\Support\Facades\DB;

class PresensiTicketScannerService
{
    /**
     * Ekstrak kode tiket dari string mentah atau URL.
     */
    public function extractTicketCode(string $rawInput): string
    {
        $trimmed = trim($rawInput);

        // Jika URL, periksa query parameter seperti ticket= atau code=
        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
            $parsed = parse_url($trimmed);
            if (! empty($parsed['query'])) {
                parse_str($parsed['query'], $queryParams);
                if (! empty($queryParams['ticket'])) {
                    return trim((string) $queryParams['ticket']);
                }
                if (! empty($queryParams['code'])) {
                    return trim((string) $queryParams['code']);
                }
                if (! empty($queryParams['nomor_tiket'])) {
                    return trim((string) $queryParams['nomor_tiket']);
                }
            }
        }

        // Cari pola TKT-{kegiatan}-{random}-{seat} atau TKT-...
        if (preg_match('/(TKT-\d+-[A-Za-z0-9]+-[A-Za-z0-9]+|TKT-[A-Za-z0-9-]+)/i', $trimmed, $matches)) {
            return strtoupper(trim($matches[1]));
        }

        return strtoupper($trimmed);
    }

    /**
     * Samarkan NIM peserta sebagian (mis. 2240021099 -> 2240***099).
     */
    public function maskNim(?string $nim): string
    {
        if (! $nim) {
            return '-';
        }

        $len = strlen($nim);
        if ($len <= 4) {
            return $nim;
        }

        if ($len <= 7) {
            return substr($nim, 0, 2).'***'.substr($nim, -2);
        }

        return substr($nim, 0, 4).'***'.substr($nim, -3);
    }

    /**
     * Pindai dan validasi tiket dalam transaksi atomik.
     *
     * @return array{
     *     status: 'DITERIMA'|'SUDAH_CHECK_IN'|'DITOLAK',
     *     success: bool,
     *     message: string,
     *     ticket_code?: string,
     *     participant_name?: string,
     *     participant_identity_masked?: string,
     *     seat_code?: string,
     *     kegiatan_nama?: string,
     *     checked_in_at?: string,
     *     officer_name?: string,
     *     registration_id?: int,
     * }
     */
    public function scan(
        Kegiatan $kegiatan,
        string $rawInput,
        User $officer,
        string $method = 'kamera',
        ?string $alasan = null,
        ?string $ip = null
    ): array {
        $ticketCode = $this->extractTicketCode($rawInput);

        if ($ticketCode === '') {
            return [
                'status' => 'DITOLAK',
                'success' => false,
                'message' => 'Kode tiket kosong atau format tidak dikenali.',
            ];
        }

        return DB::transaction(function () use ($kegiatan, $ticketCode, $officer, $method, $alasan, $ip) {
            $registration = KegiatanRegistration::with(['kegiatan', 'seat', 'participantUser', 'bookerUser'])
                ->where('nomor_tiket', $ticketCode)
                ->first();

            // 1. Tiket ada
            if (! $registration) {
                return [
                    'status' => 'DITOLAK',
                    'success' => false,
                    'message' => "E-Tiket {$ticketCode} tidak ditemukan dalam basis data.",
                ];
            }

            // 2. Milik kegiatan ini
            if ((int) $registration->kegiatan_id !== (int) $kegiatan->id) {
                $namaLain = $registration->kegiatan?->nama_kegiatan ?? 'Lain';

                return [
                    'status' => 'DITOLAK',
                    'success' => false,
                    'message' => "Tiket ini milik kegiatan lain: {$namaLain}.",
                ];
            }

            // 3. Status tiket sah (lunas atau gratis, tidak dibatalkan / ditolak)
            if (! $registration->isConfirmed()) {
                $statusLabel = $registration->status_pembayaran?->label() ?? 'Belum Lunas';

                return [
                    'status' => 'DITOLAK',
                    'success' => false,
                    'message' => "Tiket belum lunas atau tidak sah (status pembayaran: {$statusLabel}).",
                ];
            }

            // 4. Kegiatan sudah terbit
            $kegiatanStatus = $kegiatan->status instanceof KegiatanStatus ? $kegiatan->status : KegiatanStatus::tryFrom((string) $kegiatan->status);
            if (in_array($kegiatanStatus, [KegiatanStatus::Draft, KegiatanStatus::Cancelled], true)) {
                return [
                    'status' => 'DITOLAK',
                    'success' => false,
                    'message' => 'Kegiatan belum dipublikasikan atau telah dibatalkan oleh pengelola.',
                ];
            }

            // 5. Jendela check-in dari config (bawaan: 2 jam sebelum mulai sampai selesai)
            $earlyHours = (int) config('kegiatan.checkin_early_hours', 2);
            $windowStart = $kegiatan->tanggal_mulai ? $kegiatan->tanggal_mulai->copy()->subHours($earlyHours) : null;
            $windowEnd = $kegiatan->tanggal_selesai
                ? $kegiatan->tanggal_selesai->copy()
                : ($kegiatan->tanggal_mulai ? $kegiatan->tanggal_mulai->copy()->addHours(6) : null);

            $now = now();
            if ($windowStart && $now->lt($windowStart)) {
                $bukaWib = Wib::dari($windowStart)->translatedFormat('d F Y, H:i');

                return [
                    'status' => 'DITOLAK',
                    'success' => false,
                    'message' => "Jendela waktu check-in belum dibuka (dibuka mulai {$bukaWib} WIB).",
                ];
            }

            if ($windowEnd && $now->gt($windowEnd)) {
                $tutupWib = Wib::dari($windowEnd)->translatedFormat('d F Y, H:i');

                return [
                    'status' => 'DITOLAK',
                    'success' => false,
                    'message' => "Jendela waktu check-in telah berakhir pada {$tutupWib} WIB.",
                ];
            }

            $seatCode = $registration->seat?->seat_code ?? 'Tanpa Kursi Tetap';
            $participantName = $registration->participant_name ?: ($registration->participantUser?->name ?? 'Peserta');
            $identity = $registration->participant_identity ?: ($registration->participantUser?->nim ?? null);
            $maskedNim = $this->maskNim($identity);

            // 6. Cek apakah sudah check-in sebelumnya
            $isAlreadyCheckedIn = ($registration->status_kehadiran === AttendanceStatus::CheckedIn
                || $registration->status_kehadiran?->value === AttendanceStatus::CheckedIn->value
                || $registration->checked_in_at !== null);

            if ($isAlreadyCheckedIn) {
                // Cari nama petugas dari riwayat audit log
                $lastLog = AuditLog::whereIn('action', ['ticket_checkin', 'ticket_checkin_manual'])
                    ->where('subject_type', KegiatanRegistration::class)
                    ->where('subject_id', $registration->id)
                    ->latest()
                    ->with('user')
                    ->first();

                $petugasNama = $lastLog?->user?->name ?? 'Petugas';
                $waktuWib = $registration->checked_in_at
                    ? Wib::dari($registration->checked_in_at)->translatedFormat('d M Y, H:i').' WIB'
                    : '-';

                return [
                    'status' => 'SUDAH_CHECK_IN',
                    'success' => true,
                    'message' => "E-Tiket sudah pernah check-in sebelumnya pada {$waktuWib} oleh {$petugasNama}.",
                    'ticket_code' => $registration->nomor_tiket,
                    'participant_name' => $participantName,
                    'participant_identity_masked' => $maskedNim,
                    'seat_code' => $seatCode,
                    'kegiatan_nama' => $kegiatan->nama_kegiatan,
                    'checked_in_at' => $registration->checked_in_at?->toIso8601String(),
                    'officer_name' => $petugasNama,
                    'registration_id' => $registration->id,
                ];
            }

            // 7. Pencatatan atomik (UPDATE ... WHERE belum check-in) untuk mencegah double-checkin serentak
            $affected = KegiatanRegistration::where('id', $registration->id)
                ->where(function ($q) {
                    $q->whereNull('status_kehadiran')
                        ->orWhere('status_kehadiran', '!=', AttendanceStatus::CheckedIn->value);
                })
                ->update([
                    'status_kehadiran' => AttendanceStatus::CheckedIn->value,
                    'checked_in_at' => $now,
                ]);

            if ($affected === 0) {
                // Terjadi balapan (race condition) dan sudah tercatat di thread lain
                $registration->refresh();
                $waktuWib = $registration->checked_in_at
                    ? Wib::dari($registration->checked_in_at)->translatedFormat('d M Y, H:i').' WIB'
                    : '-';

                return [
                    'status' => 'SUDAH_CHECK_IN',
                    'success' => true,
                    'message' => "E-Tiket sudah pernah check-in sebelumnya pada {$waktuWib}.",
                    'ticket_code' => $registration->nomor_tiket,
                    'participant_name' => $participantName,
                    'participant_identity_masked' => $maskedNim,
                    'seat_code' => $seatCode,
                    'kegiatan_nama' => $kegiatan->nama_kegiatan,
                    'registration_id' => $registration->id,
                ];
            }

            // 8. Catat waktu (UTC), petugas, metode (kamera, keyboard, manual), dan IP ke audit log
            AuditLog::record(
                userId: $officer->id,
                action: $method === 'manual' ? 'ticket_checkin_manual' : 'ticket_checkin',
                description: "Check-in awal tiket {$registration->nomor_tiket} untuk kegiatan {$kegiatan->nama_kegiatan} via {$method}".($alasan ? ". Alasan: {$alasan}" : ''),
                subject: $registration,
                metadata: [
                    'kegiatan_id' => $kegiatan->id,
                    'ticket_code' => $registration->nomor_tiket,
                    'method' => $method,
                    'alasan' => $alasan,
                    'ip' => $ip ?? request()?->ip(),
                ]
            );

            return [
                'status' => 'DITERIMA',
                'success' => true,
                'message' => "Check-in berhasil diterima: {$participantName} ({$seatCode}).",
                'ticket_code' => $registration->nomor_tiket,
                'participant_name' => $participantName,
                'participant_identity_masked' => $maskedNim,
                'seat_code' => $seatCode,
                'kegiatan_nama' => $kegiatan->nama_kegiatan,
                'checked_in_at' => $now->toIso8601String(),
                'officer_name' => $officer->name,
                'registration_id' => $registration->id,
            ];
        });
    }

    /**
     * Check-in manual dengan alasan wajib.
     */
    public function manualCheckIn(
        KegiatanRegistration $registration,
        User $officer,
        string $alasan,
        ?string $ip = null
    ): array {
        $trimmedAlasan = trim($alasan);
        if ($trimmedAlasan === '') {
            throw new \InvalidArgumentException('Alasan check-in manual wajib diisi.');
        }

        $kegiatan = $registration->kegiatan;

        return $this->scan(
            kegiatan: $kegiatan,
            rawInput: $registration->nomor_tiket,
            officer: $officer,
            method: 'manual',
            alasan: $trimmedAlasan,
            ip: $ip
        );
    }

    /**
     * Batalkan check-in tiket dengan alasan wajib dan dicatat ke audit log.
     */
    public function cancelCheckIn(
        KegiatanRegistration $registration,
        User $officer,
        string $alasan,
        ?string $ip = null
    ): array {
        $trimmedAlasan = trim($alasan);
        if ($trimmedAlasan === '') {
            throw new \InvalidArgumentException('Alasan pembatalan check-in wajib diisi.');
        }

        return DB::transaction(function () use ($registration, $officer, $trimmedAlasan, $ip) {
            $registration->status_kehadiran = AttendanceStatus::Registered;
            $registration->checked_in_at = null;
            $registration->save();

            AuditLog::record(
                userId: $officer->id,
                action: 'ticket_checkin_cancelled',
                description: "Pembatalan status check-in tiket {$registration->nomor_tiket}. Alasan: {$trimmedAlasan}",
                subject: $registration,
                metadata: [
                    'kegiatan_id' => $registration->kegiatan_id,
                    'ticket_code' => $registration->nomor_tiket,
                    'alasan' => $trimmedAlasan,
                    'ip' => $ip ?? request()?->ip(),
                ]
            );

            return [
                'success' => true,
                'message' => "Check-in tiket {$registration->nomor_tiket} berhasil dibatalkan.",
                'registration_id' => $registration->id,
            ];
        });
    }
}
