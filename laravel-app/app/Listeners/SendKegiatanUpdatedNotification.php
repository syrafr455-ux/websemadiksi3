<?php

namespace App\Listeners;

use App\Events\KegiatanUpdatedEvent;
use App\Mail\KegiatanUpdatedMail;
use App\Services\EmailProtectionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendKegiatanUpdatedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    public function backoff(): array
    {
        return config('unusa.email_protection.retry_backoff', [60, 300, 900]);
    }

    public function handle(KegiatanUpdatedEvent $event): void
    {
        $protection = app(EmailProtectionService::class);
        $isSync = (isset($this->job) && $this->job instanceof SyncJob) || config('queue.default') === 'sync';

        // Jika kuota harian habis, tunda email massal ke hari berikutnya
        if ($protection->isDailyQuotaExceeded()) {
            $delay = $protection->secondsUntilTomorrow();
            if (! $isSync && isset($this->job)) {
                $this->release($delay);
            }
            Log::info('Email massal pemberitahuan kegiatan ditunda ke hari berikutnya karena kuota harian tercapai.', [
                'kegiatan_id' => $event->kegiatan->id,
                'delay_seconds' => $delay,
            ]);

            return;
        }

        $kegiatan = $event->kegiatan;
        $registrations = $kegiatan->registrations()->with('participantUser')->get();

        $sentEmails = [];
        foreach ($registrations as $reg) {
            $user = $reg->participantUser;
            if (! $user || ! $user->email) {
                continue;
            }

            if (in_array($user->email, $sentEmails, true)) {
                continue;
            }

            $sentEmails[] = $user->email;
            try {
                Mail::to($user->email)->send(new KegiatanUpdatedMail($kegiatan, $user, $event->changedAttributes));
                $protection->recordSent(isCritical: false);
            } catch (\Throwable $e) {
                Log::error('Gagal mengirim email pembaruan kegiatan: '.$e->getMessage());
            }
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('Pengiriman notifikasi pembaruan kegiatan gagal permanen setelah batas percobaan ulang.', [
            'pesan_galat' => $exception?->getMessage(),
        ]);
    }
}
