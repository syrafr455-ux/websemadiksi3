<?php

namespace App\Jobs\Middleware;

use App\Services\EmailProtectionService;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class EmailProtectionMiddleware
{
    public function handle(object $job, $next): mixed
    {
        $protection = app(EmailProtectionService::class);
        $mailable = property_exists($job, 'mailable') ? $job->mailable : null;
        $isCritical = $mailable ? $protection->isCritical($mailable) : false;

        $isSync = (property_exists($job, 'job') && $job->job instanceof SyncJob)
            || config('queue.default') === 'sync';

        // 1. Pembatas Laju (Rate Limiter bawaan 20/menit)
        $rateLimit = (int) config('unusa.email_protection.rate_limit_per_minute', 20);
        if ($rateLimit > 0 && ! $isSync) {
            if (RateLimiter::tooManyAttempts('email_protection_rate_limit', $rateLimit)) {
                $delay = max(5, RateLimiter::availableIn('email_protection_rate_limit'));
                if (method_exists($job, 'release')) {
                    $job->release($delay);

                    return null;
                }
            }
            RateLimiter::hit('email_protection_rate_limit', 60);
        }

        // 2. Kuota Harian (Bawaan 400)
        if ($protection->isDailyQuotaExceeded()) {
            if (! $isCritical) {
                $delaySeconds = $protection->secondsUntilTomorrow();
                if (! $isSync && method_exists($job, 'release')) {
                    $job->release($delaySeconds);
                }
                Log::info('Email massal antrean ditunda ke hari berikutnya karena kuota harian tercapai.', [
                    'mailable' => $mailable ? get_class($mailable) : get_class($job),
                    'delay_seconds' => $delaySeconds,
                ]);

                return null;
            }
        }

        $result = $next($job);
        $protection->recordSent($isCritical);

        return $result;
    }
}
