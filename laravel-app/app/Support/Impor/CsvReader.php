<?php

namespace App\Support\Impor;

/**
 * Pembaca CSV untuk impor admin (CSV dari Excel: pemisah "," / ";" / tab, BOM UTF-8 dibuang).
 * Header dinormalisasi ke snake_case; alias header dipetakan ke nama kolom baku.
 */
final class CsvReader
{
    public const MAKS_BARIS = 20000;

    /**
     * @param  array<string, string>  $alias  header ternormalisasi => nama kolom baku
     * @return array{header: list<string>, baris: list<array{baris: int, data: array<string, string>}>}
     */
    public static function baca(string $isi, array $alias = []): array
    {
        $isi = preg_replace('/^\xEF\xBB\xBF/', '', $isi) ?? $isi;
        $isi = str_replace(["\r\n", "\r"], "\n", $isi);

        $barisPertama = strtok($isi, "\n") ?: '';
        $pemisah = self::tebakPemisah($barisPertama);

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $isi);
        rewind($stream);

        $header = [];
        $hasil = [];
        $nomorBaris = 0;

        while (($kolom = fgetcsv($stream, 0, $pemisah, '"', '')) !== false) {
            $nomorBaris++;

            if ($kolom === [null] || $kolom === ['']) {
                continue;
            }

            if ($header === []) {
                $header = array_map(fn ($h) => self::kunci((string) $h, $alias), $kolom);

                continue;
            }

            if (count($hasil) >= self::MAKS_BARIS) {
                break;
            }

            $data = [];
            foreach ($header as $i => $nama) {
                if ($nama !== '') {
                    $data[$nama] = trim((string) ($kolom[$i] ?? ''));
                }
            }

            if (implode('', $data) === '') {
                continue;
            }

            $hasil[] = ['baris' => $nomorBaris, 'data' => $data];
        }

        fclose($stream);

        return ['header' => array_values(array_filter($header)), 'baris' => $hasil];
    }

    private static function tebakPemisah(string $baris): string
    {
        $jumlah = [',' => substr_count($baris, ','), ';' => substr_count($baris, ';'), "\t" => substr_count($baris, "\t")];
        arsort($jumlah);

        return (string) array_key_first($jumlah);
    }

    private static function kunci(string $header, array $alias): string
    {
        $kunci = trim(preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($header))) ?? '', '_');

        return $alias[$kunci] ?? $kunci;
    }
}
