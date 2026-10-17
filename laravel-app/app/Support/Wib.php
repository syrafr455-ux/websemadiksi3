<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Aturan waktu tunggal: simpan UTC, input pengguna dianggap WIB, tampilkan dalam WIB.
 */
final class Wib
{
    public const ZONA = 'Asia/Jakarta';

    /**
     * Urai input pengguna (jam WIB, mis. dari <input type="datetime-local">) menjadi waktu UTC untuk disimpan.
     */
    public static function keUtc(?string $input): ?Carbon
    {
        if ($input === null || trim($input) === '') {
            return null;
        }

        return Carbon::parse($input, self::ZONA)->utc();
    }

    /**
     * Salinan waktu tersimpan (UTC) dalam WIB, untuk ditampilkan.
     */
    public static function dari(?CarbonInterface $waktu): ?Carbon
    {
        return $waktu === null ? null : Carbon::instance($waktu)->setTimezone(self::ZONA);
    }

    /**
     * Waktu sekarang dalam WIB (mis. untuk tanggal terbit dan tahun nomor sertifikat).
     */
    public static function sekarang(): Carbon
    {
        return Carbon::now(self::ZONA);
    }
}
