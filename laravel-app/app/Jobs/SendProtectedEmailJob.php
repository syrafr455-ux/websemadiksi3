<?php

namespace App\Jobs;

use App\Services\EmailProtectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Job antrean pengiriman email dengan pembatas laju, percobaan ulang bertahap,
 * proteksi kuota harian, dan pencatatan kegagalan akhir tanpa data pribadi.
 */
class SendProtectedEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public bool $isCritical = false;

    public function __construct(
        public string $recipient,
        public Mailable $mailable,
        ?bool $isCritical = null
    ) {
        $protection = app(EmailProtectionService::class);
        $this->isCritical = $isCritical ?? $protection->isCritical($mailable);
    }

    /**
     * Waktu tunda percobaan ulang bertahap (1 menit, 5 menit, 15 menit).
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return config('unusa.email_protection.retry_backoff', [60, 300, 900]);
    }

    public function handle(EmailProtectionService $protection): void
    {
        $isSync = $this->job instanceof SyncJob || config('queue.default') === 'sync';

        // 1. Pembatas Laju (Rate Limiter bawaan 20/menit)
        $rateLimit = (int) config('unusa.email_protection.rate_limit_per_minute', 20);
        if ($rateLimit > 0 && ! $isSync) {
            if (RateLimiter::tooManyAttempts('email_protection_rate_limit', $rateLimit)) {
                $delay = max(5, RateLimiter::availableIn('email_protection_rate_limit'));
                $this->release($delay);

                return;
            }
            RateLimiter::hit('email_protection_rate_limit', 60);
        }

        // 2. Kuota Harian (Bawaan 400)
        if ($protection->isDailyQuotaExceeded()) {
            if (! $this->isCritical) {
                // Email massal ditunda ke hari berikutnya
                $delaySeconds = $protection->secondsUntilTomorrow();
                if (! $isSync) {
                    $this->release($delaySeconds);
                }
                Log::info('Email massal ditunda ke hari berikutnya karena kuota harian tercapai.', [
                    'mailable' => get_class($this->mailable),
                    'delay_seconds' => $delaySeconds,
                ]);

                return;
            }
            // Email kritis (aktivasi, reset kata sandi, tiket) tetap dikirim
        }

        // 3. Pengiriman Email
        Mail::to($this->recipient)->send($this->mailable);
        $protection->recordSent($this->isCritical);
    }

    /**
     * Kegagalan akhir tercatat tanpa data pribadi (tanpa email penerima atau nama).
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error('Pengiriman email antrean gagal permanen setelah batas percobaan ulang.', [
            'job' => static::class,
            'mailable' => get_class($this->mailable),
            'pesan_galat' => $exception?->getMessage(),
        ]);
    }
}
