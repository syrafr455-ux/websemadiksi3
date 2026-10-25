<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PreventBackHistoryCache
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // 1. Jangan terapkan pada unduhan berkas atau response streamed
        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            return $response;
        }

        // 2. Jangan terapkan pada aset statis
        if ($request->is('build/*', 'assets/*', 'images/*', 'favicon.ico')) {
            return $response;
        }

        // 3. Jangan terapkan pada halaman publik (landing, /verifikasi, sertifikat publik, berita acara, dsb.)
        // kecuali bila berupa respons Inertia
        $isPublicPage = $request->is(
            '/',
            'verifikasi',
            'verifikasi/*',
            'verifikasi-sertifikat',
            'verifikasi-sertifikat/*',
            'sertifikat/*/download',
            'profil-semadiksi',
            'berita-acara',
            'berita-acara/*',
            'absensi',
            'kegiatan/*/presensi',
            'presensi/*'
        );

        $isInertia = (bool) ($request->header('X-Inertia') || $response->headers->has('X-Inertia'));

        if ($isPublicPage && ! $isInertia) {
            return $response;
        }

        $shouldPreventCache = false;

        // Semua halaman yang memerlukan login (peserta, admin, juri, verifikator)
        if ($request->user() !== null) {
            $shouldPreventCache = true;
        }

        // Semua respons Inertia
        if ($isInertia) {
            $shouldPreventCache = true;
        }

        // Halaman login, 2FA, dan form aktivasi/registrasi/lupa-kata-sandi (mencegah CSRF token basi dari cache)
        if ($request->is([
            'masuk',
            'login',
            'admin/masuk',
            'two-factor*',
            'daftar*',
            'register*',
            'registrasi*',
            'aktivasi*',
            'lupa-kata-sandi*',
            'atur-ulang-kata-sandi*',
        ])) {
            $shouldPreventCache = true;
        }

        if ($shouldPreventCache) {
            $existingCacheControl = (string) $response->headers->get('Cache-Control');
            if (! str_contains($existingCacheControl, 'no-store')) {
                $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            }
            if (! $response->headers->has('Pragma')) {
                $response->headers->set('Pragma', 'no-cache');
            }
            if (! $response->headers->has('Expires')) {
                $response->headers->set('Expires', 'Fri, 01 Jan 1990 00:00:00 GMT');
            }
        }

        return $response;
    }
}
