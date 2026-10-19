<?php

namespace App\Http\Controllers;

use App\Services\Kip\KlaimKipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Kartu "Saya penerima KIP Kuliah" di dashboard akun user_umum jalur UNUSA.
 */
class KlaimKipController extends Controller
{
    public function store(Request $request, KlaimKipService $klaim): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isUmumUnusa(), 403, 'Klaim status KIP-K hanya untuk akun Mahasiswa UNUSA non-penerima.');

        // Nomor tidak pernah dikembalikan sebagai "old input" (lihat dontFlash di bootstrap/app.php).
        $data = $request->validate(
            ['nomor_pendaftaran' => ['required', 'string', 'max:40']],
            ['nomor_pendaftaran.required' => 'Masukkan nomor pendaftaran SIM KIP Kuliah Anda.']
        );

        $hasil = $klaim->klaim($user, $data['nomor_pendaftaran'], (string) $request->ip());

        return redirect()->route('dashboard.index')
            ->with('klaim_status', $hasil['status'])
            ->with('klaim_pesan', $hasil['pesan']);
    }
}
