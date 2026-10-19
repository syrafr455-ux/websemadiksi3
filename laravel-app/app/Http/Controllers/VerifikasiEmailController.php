<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Verifikasi email standar Laravel untuk pendaftar jalur Peserta Umum.
 */
class VerifikasiEmailController extends Controller
{
    public function pemberitahuan(Request $request): View|RedirectResponse
    {
        return $request->user()->hasVerifiedEmail()
            ? redirect()->route('dashboard.index')
            : view('auth.verifikasi-email');
    }

    public function verifikasi(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        return redirect()->route('dashboard.index')->with('success', 'Email terverifikasi. Akun Anda sudah aktif.');
    }

    public function kirimUlang(Request $request): RedirectResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return back()->with('success', 'Link verifikasi baru telah dikirim ke email Anda.');
    }
}
