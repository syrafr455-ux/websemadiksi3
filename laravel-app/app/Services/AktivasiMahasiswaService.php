<?php

namespace App\Services;

use App\Models\AktivasiMahasiswa;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Token aktivasi akun mahasiswa: acak 64 karakter, disimpan sebagai hash SHA-256, kedaluwarsa,
 * sekali pakai, terikat ke NIM, dan dikirim sebagai URL bertanda tangan.
 */
class AktivasiMahasiswaService
{
    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Cek jeda kirim ulang dan batas harian per NIM.
     *
     * @return array{alasan: 'jeda'|'harian', detik: int}|null null bila boleh mengirim
     */
    public function hambatanKirim(string $nim): ?array
    {
        $terakhir = AktivasiMahasiswa::where('nim', $nim)->latest('created_at')->first();
        $jeda = (int) config('unusa.aktivasi_jeda_detik', 60);

        if ($terakhir && $terakhir->created_at->addSeconds($jeda)->isFuture()) {
            return ['alasan' => 'jeda', 'detik' => (int) ceil(now()->diffInSeconds($terakhir->created_at->addSeconds($jeda)))];
        }

        $hariIni = AktivasiMahasiswa::where('nim', $nim)->where('created_at', '>', now()->subDay())->orderBy('created_at')->get();
        if ($hariIni->count() >= (int) config('unusa.aktivasi_maks_per_hari', 3)) {
            return ['alasan' => 'harian', 'detik' => (int) ceil(now()->diffInSeconds($hariIni->first()->created_at->addDay()))];
        }

        return null;
    }

    /**
     * Buat token baru (token lama untuk NIM yang sama dibatalkan) dan kembalikan URL bertanda tangan.
     */
    public function buatLink(string $nim): string
    {
        $menit = (int) config('unusa.aktivasi_berlaku_menit', 60);
        $token = Str::random(64);

        AktivasiMahasiswa::where('nim', $nim)->whereNull('dipakai_pada')->update(['dipakai_pada' => now()]);
        AktivasiMahasiswa::create([
            'nim' => $nim,
            'token_hash' => self::hash($token),
            'kedaluwarsa_pada' => now()->addMinutes($menit),
        ]);

        return URL::temporarySignedRoute('daftar.unusa.aktivasi', now()->addMinutes($menit), ['token' => $token]);
    }

    /**
     * Token yang masih berlaku (belum dipakai, belum kedaluwarsa), atau null.
     */
    public function temukan(string $token): ?AktivasiMahasiswa
    {
        $baris = AktivasiMahasiswa::where('token_hash', self::hash($token))->first();

        return $baris && $baris->masihBerlaku() ? $baris : null;
    }
}
