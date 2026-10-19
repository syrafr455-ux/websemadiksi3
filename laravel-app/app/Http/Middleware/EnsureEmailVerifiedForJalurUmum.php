<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Akun Peserta Umum hasil pendaftaran mandiri baru aktif setelah email diverifikasi.
 */
class EnsureEmailVerifiedForJalurUmum
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && method_exists($user, 'wajibVerifikasiEmail') && $user->wajibVerifikasiEmail()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Verifikasi email Anda terlebih dahulu.'], 409)
                : redirect()->route('verification.notice');
        }

        return $next($request);
    }
}
