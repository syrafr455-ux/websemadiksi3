<?php

namespace App\Support;

/**
 * Sanitizer untuk mencegah CSV Formula Injection (CWE-1236).
 *
 * Mencegah eksekusi rumus spreadsheet berbahaya di Excel, LibreOffice Calc,
 * dan Google Sheets bila sel diawali karakter: '=', '+', '-', '@', "\t", "\r".
 */
class CsvSanitizer
{
    /**
     * Karakter awalan berbahaya yang memicu interpretasi formula oleh spreadsheet.
     *
     * @var list<string>
     */
    private const FORMULA_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Netralkan satu nilai agar aman dari eksekusi formula CSV.
     * Bila teks diawali karakter formula, tambahkan tanda petik tunggal (') di awal.
     */
    public static function netralkan(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $str = (string) $value;
        if ($str === '') {
            return '';
        }

        $firstChar = $str[0];
        if (in_array($firstChar, self::FORMULA_PREFIXES, true)) {
            return "'".$str;
        }

        return $str;
    }

    /**
     * Netralkan seluruh kolom dalam satu baris CSV.
     *
     * @param  array<int|string, mixed>  $row
     * @return array<int|string, string>
     */
    public static function netralkanBaris(array $row): array
    {
        return array_map([self::class, 'netralkan'], $row);
    }

    /**
     * Alias bahasa Inggris untuk netralkan.
     */
    public static function sanitize(mixed $value): string
    {
        return self::netralkan($value);
    }

    /**
     * Alias bahasa Inggris untuk netralkanBaris.
     *
     * @param  array<int|string, mixed>  $row
     * @return array<int|string, string>
     */
    public static function sanitizeRow(array $row): array
    {
        return self::netralkanBaris($row);
    }
}
