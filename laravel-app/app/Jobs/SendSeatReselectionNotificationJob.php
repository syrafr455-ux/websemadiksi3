<?php

namespace App\Jobs;

use App\Mail\SeatReselectionMail;
use App\Models\Kegiatan;
use App\Models\KegiatanRegistration;
use App\Support\Wib;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendSeatReselectionNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Kegiatan $kegiatan
    ) {}

    public function handle(): void
    {
        // Revisi 3: Hanya ambil registrasi aktif (free, pending, paid) yang membutuhkan pemilihan ulang kursi.
        // Registrasi batal/expired dikecualikan dari pengiriman email.
        $registrations = KegiatanRegistration::with(['participantUser', 'bookerUser'])
            ->where('kegiatan_id', $this->kegiatan->id)
            ->active()
            ->where('needs_seat_reselection', true)
            ->get();

        if ($registrations->isEmpty()) {
            return;
        }

        // Siapkan batas waktu dalam format zona waktu Asia/Jakarta
        $deadlineWib = $this->kegiatan->booking_closes_at
            ? Wib::dari($this->kegiatan->booking_closes_at)->translatedFormat('d F Y H:i').' WIB'
            : null;

        // Tautan pemilihan ulang untuk pemesan (booker)
        $reselectionUrl = route('kegiatan.detail', $this->kegiatan->id);

        $sentBookerEmails = [];
        $sentDelegateEmails = [];

        // 1. Kirim email pemilihan ulang ke Booker (berisi tautan pilih kursi)
        foreach ($registrations as $reg) {
            $booker = $reg->bookerUser;
            if (! $booker || empty($booker->email)) {
                continue;
            }

            $bookerEmail = strtolower(trim($booker->email));
            if (in_array($bookerEmail, $sentBookerEmails, true)) {
                continue;
            }

            $sentBookerEmails[] = $bookerEmail;

            try {
                Mail::to($bookerEmail)->send(new SeatReselectionMail(
                    kegiatan: $this->kegiatan,
                    recipientName: $booker->name ?: 'Pemesan Tiket',
                    deadlineWib: $deadlineWib,
                    reselectionUrl: $reselectionUrl,
                    isBooker: true
                ));
            } catch (\Throwable $e) {
                Log::error("Gagal mengirim email pemilihan ulang kursi ke booker {$bookerEmail}: ".$e->getMessage());
            }
        }

        // 2. Revisi 4: Kirim email pemberitahuan ke delegasi yang memiliki email (TANPA tautan pilih kursi)
        foreach ($registrations as $reg) {
            // Cek apakah registrasi ini adalah delegasi (bukan booker sendiri)
            $isSelfBooking = ($reg->participant_user_id === $reg->booker_user_id)
                || (! empty($reg->delegation_data['is_booker']));

            if ($isSelfBooking) {
                continue;
            }

            $delegateEmail = null;
            $delegateName = $reg->participant_name;

            if ($reg->participantUser && ! empty($reg->participantUser->email)) {
                $delegateEmail = strtolower(trim($reg->participantUser->email));
                $delegateName = $reg->participantUser->name ?: $delegateName;
            } elseif (! empty($reg->delegation_data['email'])) {
                $delegateEmail = strtolower(trim($reg->delegation_data['email']));
            }

            $bookerEmail = $reg->bookerUser ? strtolower(trim($reg->bookerUser->email)) : null;

            // Jika tidak ada email, atau sama dengan email booker, atau sudah dikirimi email delegasi
            if (! $delegateEmail || $delegateEmail === $bookerEmail || in_array($delegateEmail, $sentDelegateEmails, true)) {
                continue;
            }

            $sentDelegateEmails[] = $delegateEmail;

            try {
                Mail::to($delegateEmail)->send(new SeatReselectionMail(
                    kegiatan: $this->kegiatan,
                    recipientName: $delegateName ?: 'Peserta Delegasi',
                    deadlineWib: $deadlineWib,
                    reselectionUrl: null, // Tanpa tautan pilih kursi
                    isBooker: false
                ));
            } catch (\Throwable $e) {
                Log::error("Gagal mengirim email pemberitahuan perubahan ruangan ke delegasi {$delegateEmail}: ".$e->getMessage());
            }
        }
    }
}
