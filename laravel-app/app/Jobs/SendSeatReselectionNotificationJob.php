<?php

namespace App\Jobs;

use App\Mail\SeatReselectionMail;
use App\Models\Kegiatan;
use App\Models\KegiatanRegistration;
use App\Services\EmailProtectionService;
use App\Support\Wib;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendSeatReselectionNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function backoff(): array
    {
        return config('unusa.email_protection.retry_backoff', [60, 300, 900]);
    }

    public function __construct(
        public Kegiatan $kegiatan
    ) {}

    public function handle(): void
    {
        // Hanya registrasi aktif (free, pending, paid) yang membutuhkan pemilihan ulang kursi.
        $registrations = KegiatanRegistration::with(['participantUser', 'bookerUser'])
            ->where('kegiatan_id', $this->kegiatan->id)
            ->active()
            ->where('needs_seat_reselection', true)
            ->get();

        $penerima = self::penerima($registrations);
        if ($penerima === []) {
            return;
        }

        $deadlineWib = $this->kegiatan->booking_closes_at
            ? Wib::dari($this->kegiatan->booking_closes_at)->translatedFormat('d F Y H:i').' WIB'
            : null;

        // Tautan langsung membuka alur pilih ulang kursi di halaman kegiatan (hanya untuk pemesan).
        $reselectionUrl = self::urlPilihUlang($this->kegiatan);
        $protection = app(EmailProtectionService::class);

        foreach ($penerima as $p) {
            try {
                Mail::to($p['email'])->send(new SeatReselectionMail(
                    kegiatan: $this->kegiatan,
                    recipientName: $p['nama'],
                    deadlineWib: $deadlineWib,
                    reselectionUrl: $p['pemesan'] ? $reselectionUrl : null,
                    isBooker: $p['pemesan']
                ));
                $protection->recordSent(isCritical: true);
            } catch (\Throwable $e) {
                Log::error('Gagal mengirim email pemilihan ulang kursi (kegiatan '.$this->kegiatan->id.'): '.$e->getMessage());
            }
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('Job pengiriman email pemilihan ulang kursi gagal permanen setelah batas percobaan ulang.', [
            'kegiatan_id' => $this->kegiatan->id,
            'pesan_galat' => $exception?->getMessage(),
        ]);
    }

    public static function urlPilihUlang(Kegiatan $kegiatan): string
    {
        return route('kegiatan.detail', ['slugOrId' => $kegiatan->id, 'pilih_ulang' => 1]);
    }

    /**
     * Penerima email untuk registrasi yang perlu memilih ulang kursi:
     * - pemesan (booker), satu email per alamat, berisi tautan pilih ulang;
     * - delegasi yang punya email (bukan pemesan itu sendiri), satu email per alamat, tanpa tautan.
     * Dipakai juga oleh pratinjau di DenahKursiService agar jumlah email di dialog konfirmasi sama.
     *
     * @param  Collection<int, KegiatanRegistration>  $registrations  dengan relasi participantUser & bookerUser
     * @return list<array{email: string, nama: string, pemesan: bool}>
     */
    public static function penerima(Collection $registrations): array
    {
        $hasil = [];
        $emailPemesan = [];
        $emailDelegasi = [];

        foreach ($registrations as $reg) {
            $booker = $reg->bookerUser;
            if (! $booker || empty($booker->email)) {
                continue;
            }

            $email = strtolower(trim($booker->email));
            if (! in_array($email, $emailPemesan, true)) {
                $emailPemesan[] = $email;
                $hasil[] = ['email' => $email, 'nama' => $booker->name ?: 'Pemesan Tiket', 'pemesan' => true];
            }
        }

        foreach ($registrations as $reg) {
            $pesanSendiri = ($reg->participant_user_id === $reg->booker_user_id)
                || (! empty($reg->delegation_data['is_booker']));
            if ($pesanSendiri) {
                continue;
            }

            $email = null;
            $nama = $reg->participant_name;
            if ($reg->participantUser && ! empty($reg->participantUser->email)) {
                $email = strtolower(trim($reg->participantUser->email));
                $nama = $reg->participantUser->name ?: $nama;
            } elseif (! empty($reg->delegation_data['email'])) {
                $email = strtolower(trim($reg->delegation_data['email']));
            }

            $emailBooker = $reg->bookerUser ? strtolower(trim($reg->bookerUser->email)) : null;
            if (! $email || $email === $emailBooker || in_array($email, $emailDelegasi, true)) {
                continue;
            }

            $emailDelegasi[] = $email;
            $hasil[] = ['email' => $email, 'nama' => $nama ?: 'Peserta Delegasi', 'pemesan' => false];
        }

        return $hasil;
    }
}
