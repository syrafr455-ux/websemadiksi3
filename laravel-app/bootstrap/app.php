<?php

use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureEmailVerifiedForJalurUmum;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureTwoFactorAuthenticated;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PreventBackHistoryCache;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

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
            PreventBackHistoryCache::class,
            EnsureActiveAccount::class,
            EnsureTwoFactorAuthenticated::class,
            HandleInertiaRequests::class,
        ]);

        $middleware->append(SecurityHeaders::class);

        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'role' => EnsureRole::class,
            'email.terverifikasi' => EnsureEmailVerifiedForJalurUmum::class,
            '2fa' => EnsureTwoFactorAuthenticated::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Nomor pendaftaran SIM KIP Kuliah (data sensitif) tidak boleh ikut tersimpan sebagai "old input" di session.
        $exceptions->dontFlash([
            'nomor_pendaftaran',
            'nomor_pendaftaran_kip',
        ]);

        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if ($request->header('X-Inertia') && in_array($response->getStatusCode(), [403, 404, 419, 429, 500])) {
                return Inertia::render('Error', [
                    'status' => $response->getStatusCode(),
                ])
                    ->toResponse($request)
                    ->setStatusCode($response->getStatusCode());
            }

            return $response;
        });
    })->create();
