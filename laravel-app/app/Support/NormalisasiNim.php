<?php

namespace App\Support;

/**
 * Normalisasi NIM satu pintu untuk memastikan konsistensi format NIM
 * di pencocokan SSO, form Data Pengguna, pendaftaran publik, dan master data.
 */
final class NormalisasiNim
{
    /**
     * Bersihkan NIM: trim spasi, bersihkan karakter whitespace/kontrol tersembunyi,
     * dan format huruf besar (uppercase) bila memuat abjad.
     */
    public static function bersihkan(?string $nim): string
    {
        if ($nim === null) {
            return '';
        }

        // Hapus karakter whitespace di awal dan akhir serta karakter kontrol
        $bersih = trim(preg_replace('/[\x00-\x1F\x7F\s]+/u', '', $nim) ?? '');

        return strtoupper($bersih);
    }

    /**
     * Validasi format NIM standar UNUSA (alfanumerik, titik, atau strip).
     */
    public static function formatValid(string $nim): bool
    {
        $bersih = self::bersihkan($nim);

        return $bersih !== '' && preg_match('/^[A-Z0-9.\-]+$/', $bersih) === 1;
    }
}
