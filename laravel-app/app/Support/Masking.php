<?php

namespace App\Support;

/**
 * Penyamaran data pribadi sebelum dikirim ke browser. Huruf penyamar: "X".
 */
final class Masking
{
    private const X = 'X';

    /**
     * "4330024012" -> "4330XXXX12" (4 awal dan 2 akhir terlihat).
     */
    public static function nim(?string $nim): string
    {
        return self::sisakan((string) $nim, 4, 2);
    }

    /**
     * "Muhammad Hildan" -> "MuXXXXXX HiXXXX" (2 huruf awal tiap kata terlihat).
     */
    public static function nama(?string $nama): string
    {
        $kata = preg_split('/\s+/u', trim((string) $nama), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return $kata === [] ? '-' : implode(' ', array_map(
            fn (string $k): string => self::sisakan($k, mb_strlen($k) > 2 ? 2 : 1, 0),
            $kata
        ));
    }

    /**
     * "4330024012@student.unusa.ac.id" -> "43XXXXXX12@student.unusa.ac.id" (2 awal dan 2 akhir bagian lokal).
     */
    public static function email(?string $email): string
    {
        if (! $email || ! str_contains($email, '@')) {
            return '-';
        }

        [$lokal, $domain] = explode('@', $email, 2);

        return (mb_strlen($lokal) > 4 ? self::sisakan($lokal, 2, 2) : self::sisakan($lokal, 1, 0)).'@'.$domain;
    }

    /**
     * "081234567890" -> "0812XXXXXX90" (4 digit awal dan 2 akhir terlihat).
     */
    public static function telepon(?string $telepon): string
    {
        return self::sisakan((string) $telepon, 4, 2);
    }

    private static function sisakan(string $nilai, int $awal, int $akhir): string
    {
        $panjang = mb_strlen($nilai);
        if ($panjang === 0) {
            return '-';
        }
        if ($panjang <= $awal + $akhir) {
            // Terlalu pendek untuk menampilkan kedua ujungnya: sisakan satu karakter pertama saja.
            return mb_substr($nilai, 0, 1).str_repeat(self::X, $panjang - 1);
        }

        return mb_substr($nilai, 0, $awal)
            .str_repeat(self::X, $panjang - $awal - $akhir)
            .($akhir > 0 ? mb_substr($nilai, -$akhir) : '');
    }
}
