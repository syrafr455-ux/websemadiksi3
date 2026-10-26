<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && Auth::user()->is_blocked) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akun Anda sedang dibekukan. Silakan hubungi admin.',
                ], 403);
            }

            return redirect()->route('login')->with('error', 'Akun Anda dinonaktifkan oleh administrator. Hubungi admin SEMADIKSI.');
        }

        return $next($request);
    }
}
