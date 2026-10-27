<?php

namespace App\Support\Sertifikat;

/** Pengecilan font otomatis agar teks panjang tetap muat di kotaknya (perkiraan lebar karakter rata-rata). */
final class FitTeks
{
    private const MM_PER_PT = 0.3528;

    private const LEBAR_KARAKTER = 0.58; // fraksi dari ukuran font; konservatif untuk DejaVu Sans

    private const TINGGI_BARIS = 1.25;

    /** Jumlah baris setelah pembungkusan kata pada lebar tertentu. */
    public static function jumlahBaris(string $teks, float $lebarMm, float $ukuranPt, bool $tebal = false): int
    {
        $lebarKarakter = $ukuranPt * self::MM_PER_PT * self::LEBAR_KARAKTER * ($tebal ? 1.08 : 1.0);
        $perBaris = max(1, (int) floor($lebarMm / $lebarKarakter));
        $baris = 0;

        foreach (preg_split('/\R/u', trim($teks)) ?: [''] as $paragraf) {
            $saatIni = 0;
            $baris++;
            foreach (preg_split('/\s+/u', $paragraf, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $kata) {
                $panjang = mb_strlen($kata);
                // Kata lebih panjang dari satu baris dipecah paksa.
                while ($panjang > $perBaris) {
                    if ($saatIni > 0) {
                        $baris++;
                        $saatIni = 0;
                    }
                    $baris++;
                    $panjang -= $perBaris;
                }
                if ($saatIni > 0 && $saatIni + 1 + $panjang > $perBaris) {
                    $baris++;
                    $saatIni = 0;
                }
                $saatIni += ($saatIni > 0 ? 1 : 0) + $panjang;
            }
        }

        return $baris;
    }

    /** Ukuran font terbesar (<= $ukuranPt, >= $minPt) agar teks muat; $minPt bila tetap tidak muat. */
    public static function ukuranFont(string $teks, float $lebarMm, float $tinggiMm, float $ukuranPt, float $minPt = 7.0, bool $tebal = false): float
    {
        for ($pt = $ukuranPt; $pt > $minPt; $pt -= 0.5) {
            if (self::jumlahBaris($teks, $lebarMm, $pt, $tebal) * $pt * self::MM_PER_PT * self::TINGGI_BARIS <= $tinggiMm) {
                return $pt;
            }
        }

        return $minPt;
    }
}
