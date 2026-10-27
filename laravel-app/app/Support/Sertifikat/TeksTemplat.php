<?php

namespace App\Support\Sertifikat;

use App\Enums\JenisSertifikat;
use Illuminate\Validation\ValidationException;

/** Teks per jenis sertifikat (judul, pengantar, penutup) dengan penampung {nama}, {kegiatan}, dan seterusnya. */
final class TeksTemplat
{
    public const PENAMPUNG = ['nama', 'asal_institusi', 'jenis', 'predikat', 'kegiatan', 'tanggal', 'nomor'];

    public const BIDANG = ['judul' => 150, 'pengantar' => 200, 'penutup' => 600];

    /** @return array<string, array<string, string>> */
    public static function bawaan(): array
    {
        return [
            'peserta' => ['judul' => 'SERTIFIKAT PESERTA', 'pengantar' => 'Diberikan kepada', 'penutup' => 'Atas partisipasi dalam kegiatan {kegiatan} yang diselenggarakan pada {tanggal}.'],
            'juri' => ['judul' => 'PIAGAM PENGHARGAAN JURI', 'pengantar' => 'Diberikan kepada', 'penutup' => 'Atas dedikasi sebagai juri pada kegiatan {kegiatan} yang diselenggarakan pada {tanggal}.'],
            'pembicara' => ['judul' => 'SERTIFIKAT PEMBICARA', 'pengantar' => 'Diberikan kepada', 'penutup' => 'Atas kesediaan menjadi pembicara pada kegiatan {kegiatan} yang diselenggarakan pada {tanggal}.'],
            'juara' => ['judul' => 'PIAGAM JUARA', 'pengantar' => 'Diberikan kepada', 'penutup' => 'Sebagai {predikat} pada kegiatan {kegiatan} yang diselenggarakan pada {tanggal}.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $teks
     * @return array<string, array<string, string>>
     *
     * @throws ValidationException
     */
    public static function validasi(array $teks): array
    {
        $galat = [];
        $hasil = [];
        $jenisSah = JenisSertifikat::values();
        $sisa = '/\{(?:'.implode('|', self::PENAMPUNG).')\}/';

        foreach ($teks as $jenis => $bidang) {
            if (! in_array($jenis, $jenisSah, true) || ! is_array($bidang)) {
                $galat["teks.{$jenis}"] = "Jenis teks '{$jenis}' tidak dikenal.";

                continue;
            }
            foreach ($bidang as $nama => $isi) {
                if (! isset(self::BIDANG[$nama])) {
                    $galat["teks.{$jenis}.{$nama}"] = "Bidang teks '{$nama}' tidak dikenal.";

                    continue;
                }
                if (! is_string($isi) || mb_strlen($isi) > self::BIDANG[$nama]) {
                    $galat["teks.{$jenis}.{$nama}"] = "Teks {$nama} untuk {$jenis} maksimal ".self::BIDANG[$nama].' karakter.';

                    continue;
                }
                preg_match_all('/\{([^{}]*)\}/', $isi, $cocok);
                foreach ($cocok[1] as $penampung) {
                    if (! in_array($penampung, self::PENAMPUNG, true)) {
                        $galat["teks.{$jenis}.{$nama}"] = "Penampung {{$penampung}} tidak dikenal. Yang tersedia: {".implode('}, {', self::PENAMPUNG).'}.';
                    }
                }
                if (! isset($galat["teks.{$jenis}.{$nama}"]) && preg_match('/[{}]/', preg_replace($sisa, '', $isi)) === 1) {
                    $galat["teks.{$jenis}.{$nama}"] = 'Tanda kurung kurawal harus berpasangan dan berisi nama penampung.';
                }
                $hasil[$jenis][$nama] = $isi;
            }
        }

        if ($galat !== []) {
            throw ValidationException::withMessages($galat);
        }

        return $hasil;
    }

    /**
     * Isi penampung dengan data. Nilai dikembalikan sebagai teks mentah; escape dilakukan saat HTML dirender.
     *
     * @param  array<string, string|null>  $data
     */
    public static function isi(string $teks, array $data): string
    {
        return preg_replace_callback(
            '/\{([a-z_]+)\}/',
            fn (array $m) => in_array($m[1], self::PENAMPUNG, true) ? (string) ($data[$m[1]] ?? '') : $m[0],
            $teks,
        );
    }
}
