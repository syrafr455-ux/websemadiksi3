<?php

namespace App\Services;

use App\Jobs\SendProtectedEmailJob;
use App\Mail\ActivationLinkMail;
use App\Mail\ResetKataSandiMail;
use App\Mail\TicketIssuedMail;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Layanan pengamanan pengiriman email SEMADIKSI.
 *
 * Mengelola kuota harian (default 400), membedakan email kritis vs email massal,
 * serta menjadi satu pintu terpusat pengiriman seluruh email aplikasi.
 */
class EmailProtectionService
{
    private const KUNCI_PREFIX = 'email_daily_sent_count:';

    /**
     * Kategori kritis: aktivasi, reset kata sandi, tiket.
     * Kategori massal: pengingat, pengumuman, pindah kursi.
     */
    public function isCritical(object $mailable): bool
    {
        if ($mailable instanceof ActivationLinkMail
            || $mailable instanceof ResetKataSandiMail
            || $mailable instanceof TicketIssuedMail) {
            return true;
        }

        if (property_exists($mailable, 'isCritical') && (bool) $mailable->isCritical) {
            return true;
        }

        return false;
    }

    /**
     * Satu pintu terpusat yang dipakai SEMUA email di aplikasi SEMADIKSI.
     */
    public function kirim(string $recipient, Mailable $mailable, ?bool $isCritical = null): void
    {
        $critical = $isCritical ?? $this->isCritical($mailable);
        SendProtectedEmailJob::dispatch($recipient, $mailable, $critical);
    }

    /**
     * Alias statis untuk mempermudah pemanggilan satu pintu terpusat.
     */
    public static function send(string $recipient, Mailable $mailable, ?bool $isCritical = null): void
    {
        app(self::class)->kirim($recipient, $mailable, $isCritical);
    }

    public function getDailyCount(?Carbon $date = null): int
    {
        $key = $this->cacheKey($date);

        return (int) Cache::get($key, 0);
    }

    public function recordSent(bool $isCritical = false, ?Carbon $date = null): int
    {
        $key = $this->cacheKey($date);

        if (! Cache::has($key)) {
            Cache::put($key, 1, 172800); // 48 jam

            return 1;
        }

        return (int) Cache::increment($key);
    }

    public function getDailyQuota(): int
    {
        return (int) config('unusa.email_protection.daily_quota', 400);
    }

    public function isDailyQuotaExceeded(?Carbon $date = null): bool
    {
        return $this->getDailyCount($date) >= $this->getDailyQuota();
    }

    public function isNearDailyQuota(?Carbon $date = null): bool
    {
        $quota = $this->getDailyQuota();
        if ($quota <= 0) {
            return false;
        }

        return $this->getDailyCount($date) >= (int) ceil($quota * 0.8);
    }

    /**
     * @return array{used: int, quota: int, percentage: float, is_near_limit: bool, is_exceeded: bool}
     */
    public function getQuotaStatus(?Carbon $date = null): array
    {
        $used = $this->getDailyCount($date);
        $quota = $this->getDailyQuota();
        $pct = $quota > 0 ? round(($used / $quota) * 100, 1) : 0.0;

        return [
            'used' => $used,
            'quota' => $quota,
            'percentage' => $pct,
            'is_near_limit' => $this->isNearDailyQuota($date),
            'is_exceeded' => $this->isDailyQuotaExceeded($date),
        ];
    }

    public function secondsUntilTomorrow(): int
    {
        $now = now();
        $tomorrow = $now->copy()->addDay()->startOfDay()->addMinute();

        return max(60, (int) $now->diffInSeconds($tomorrow));
    }

    private function cacheKey(?Carbon $date = null): string
    {
        $d = $date ? $date->copy() : now();

        return self::KUNCI_PREFIX.$d->format('Y-m-d');
    }
}
