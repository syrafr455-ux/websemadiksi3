<?php

namespace App\Support;

/**
 * Nomor pendaftaran SIM KIP Kuliah = username login SIM KIP (data sensitif).
 *
 * - Disimpan terenkripsi (cast "encrypted") dan dicari lewat HMAC-SHA256 dari nomor yang dinormalisasi.
 * - Hanya super_admin yang boleh melihat nomor lengkap; di tempat lain pakai samarkan().
 * - JANGAN menulis nomor ke log, pesan error, atau audit log.
 */
final class NomorKip
{
    /**
     * Hanya digit: "1234.567.89012.1766.761" dan "1234 56789012 1766761" dianggap sama.
     */
    public static function normalisasi(?string $nomor): string
    {
        return preg_replace('/\D+/', '', (string) $nomor) ?? '';
    }

    /**
     * HMAC-SHA256 (kunci APP_KEY) dari nomor ternormalisasi, untuk pencarian & keunikan tanpa menyimpan nomor polos.
     */
    public static function hash(string $nomor): string
    {
        return hash_hmac('sha256', self::normalisasi($nomor), (string) config('app.key'));
    }

    public static function valid(string $nomor): bool
    {
        $digit = self::normalisasi($nomor);

        return strlen($digit) >= 8 && strlen($digit) <= 30;
    }

    /**
     * Samarkan: hanya 7 digit terakhir yang terlihat.
     * Nomor 19 digit diformat seperti SIM KIP: ****.***.*****.1766.761
     */
    public static function samarkan(?string $nomor): string
    {
        $digit = self::normalisasi($nomor);
        if ($digit === '') {
            return '-';
        }

        $terlihat = min(7, max(0, strlen($digit) - 4));
        $tersamar = str_repeat('*', strlen($digit) - $terlihat).substr($digit, strlen($digit) - $terlihat);

        if (strlen($digit) === 19) {
            $bagian = [];
            $posisi = 0;
            foreach ([4, 3, 5, 4, 3] as $panjang) {
                $bagian[] = substr($tersamar, $posisi, $panjang);
                $posisi += $panjang;
            }

            return implode('.', $bagian);
        }

        return $tersamar;
    }
}
