<?php

namespace App\Mail\Traits;

use App\Jobs\Middleware\EmailProtectionMiddleware;
use Illuminate\Support\Facades\Log;

/**
 * Trait untuk mailable dengan proteksi antrean: percobaan ulang bertahap (1, 5, 15 menit),
 * pembatas laju & kuota harian lewat middleware, serta pencatatan kegagalan akhir tanpa data pribadi.
 */
trait ProtectedEmail
{
    public int $tries = 3;

    public function backoff(): array
    {
        return config('unusa.email_protection.retry_backoff', [60, 300, 900]);
    }

    public function middleware(): array
    {
        return [
            app(EmailProtectionMiddleware::class),
        ];
    }

    /**
     * Kegagalan akhir dicatat tanpa memuat data pribadi (tanpa email atau nama).
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error('Pengiriman email mailable gagal permanen setelah batas percobaan ulang.', [
            'mailable' => static::class,
            'pesan_galat' => $exception?->getMessage(),
        ]);
    }
}
