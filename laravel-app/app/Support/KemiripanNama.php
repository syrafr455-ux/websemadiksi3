<?php

namespace App\Support;

/**
 * Kemiripan nama untuk klaim status KIP-K (nama di daftar penerima vs nama akun).
 *
 * Normalisasi: huruf kecil, transliterasi ke ASCII, buang gelar depan (Dr., Drs., Ir., Prof., H., Hj., ...)
 * dan gelar belakang (bagian setelah koma, serta token seperti S.Kom, S.Pd, M.Kes, A.Md), buang tanda baca.
 *
 * Skor (0..1):
 * - 1.0 bila sama persis setelah normalisasi;
 * - 0.95 bila semua kata nama yang lebih pendek (minimal 2 kata) ada di nama yang lebih panjang
 *   (nama tengah/belakang tidak ditulis, mis. "Siti Aminah" vs "Siti Aminah Putri");
 * - selain itu 1 - (jarak Levenshtein / panjang terpanjang) atas kata-kata yang diurutkan
 *   (urutan kata tidak berpengaruh).
 *
 * AMBANG 0.85: menoleransi salah ketik ringan (~1 huruf per 7 huruf, mis. "Muhamad"/"Muhammad",
 * "Hidayat"/"Hidayah") tetapi menolak nama orang lain yang hanya sama nama depannya
 * (mis. "Siti Aminah" vs "Siti Rahmah" = 0.64). Nama di bawah ambang TIDAK ditolak, melainkan
 * diteruskan ke super_admin untuk ditinjau, sehingga ambang yang ketat tidak merugikan pemilik sah.
 */
final class KemiripanNama
{
    public const AMBANG = 0.85;

    private const GELAR_DEPAN = ['prof', 'dr', 'drs', 'dra', 'ir', 'h', 'hj', 'kh', 'ns', 'bd', 'apt'];

    private const GELAR_BELAKANG = [
        'skom', 'spd', 'sgz', 'skep', 'skm', 'ssi', 'st', 'se', 'sh', 'sked', 'sst', 'ssos', 'sag', 'sak', 'sm', 'sfarm', 'stp', 'sikom',
        'mpd', 'mkom', 'mm', 'msi', 'mt', 'mkes', 'mh', 'mkep', 'mag', 'mba', 'mak', 'msc', 'phd', 'amd', 'amdkep', 'amdkeb', 'ners',
    ];

    public static function normalisasi(?string $nama): string
    {
        $nama = mb_strtolower(trim((string) $nama));
        $nama = explode(',', $nama, 2)[0];
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nama);
        $nama = $ascii !== false ? $ascii : $nama;

        $kata = [];
        foreach (preg_split('/\s+/', $nama) ?: [] as $token) {
            $bersih = preg_replace('/[^a-z]/', '', $token) ?? '';
            if ($bersih !== '') {
                $kata[] = $bersih;
            }
        }

        while ($kata !== [] && in_array($kata[0], self::GELAR_DEPAN, true)) {
            array_shift($kata);
        }

        return implode(' ', array_values(array_filter($kata, fn (string $k) => ! in_array($k, self::GELAR_BELAKANG, true))));
    }

    public static function skor(?string $a, ?string $b): float
    {
        $a = self::normalisasi($a);
        $b = self::normalisasi($b);

        if ($a === '' || $b === '') {
            return 0.0;
        }
        if ($a === $b) {
            return 1.0;
        }

        $kataA = explode(' ', $a);
        $kataB = explode(' ', $b);
        [$pendek, $panjang] = count($kataA) <= count($kataB) ? [$kataA, $kataB] : [$kataB, $kataA];
        if (count($pendek) >= 2 && array_diff($pendek, $panjang) === []) {
            return 0.95;
        }

        sort($kataA);
        sort($kataB);
        $urutA = implode(' ', $kataA);
        $urutB = implode(' ', $kataB);

        return 1 - levenshtein($urutA, $urutB) / max(strlen($urutA), strlen($urutB));
    }

    public static function cocok(?string $a, ?string $b): bool
    {
        return self::skor($a, $b) >= self::AMBANG;
    }
}
