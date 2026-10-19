<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'api/payment/webhook',
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\EnsureActiveAccount::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
            'role' => \App\Http\Middleware\EnsureRole::class,
            'email.terverifikasi' => \App\Http\Middleware\EnsureEmailVerifiedForJalurUmum::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Nomor pendaftaran SIM KIP Kuliah (data sensitif) tidak boleh ikut tersimpan sebagai "old input" di session.
        $exceptions->dontFlash([
            'nomor_pendaftaran',
            'nomor_pendaftaran_kip',
        ]);
    })->create();
