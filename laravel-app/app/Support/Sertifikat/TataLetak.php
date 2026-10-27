<?php

namespace App\Support\Sertifikat;

use Illuminate\Validation\ValidationException;

/**
 * Tata letak templat sertifikat: posisi dan ukuran elemen dalam persen halaman A4 (0-100).
 * Validasi dan penjepitan dilakukan di server; klien hanya membantu tampilan.
 */
final class TataLetak
{
    public const ELEMEN = [
        'judul', 'pengantar', 'nama', 'asal_institusi', 'jenis_predikat', 'kegiatan', 'tanggal', 'nomor_seri', 'qr',
        'penandatangan_1', 'penandatangan_2', 'penandatangan_3', 'penandatangan_4',
    ];

    /** Elemen yang selalu tampil dan tidak boleh disembunyikan. */
    public const WAJIB = ['nama', 'qr', 'nomor_seri'];

    public const PROPERTI = ['x', 'y', 'lebar', 'tinggi', 'ukuran_font', 'rata', 'tebal', 'warna', 'font', 'tampil'];

    public const RATA = ['kiri', 'tengah', 'kanan'];

    /** Hanya font yang benar-benar dibawa dompdf (DejaVu, Unicode) dan font inti PDF. */
    public const FONT = ['DejaVu Sans', 'DejaVu Serif', 'DejaVu Sans Mono', 'Helvetica', 'Times', 'Courier'];

    public const ORIENTASI = ['landscape', 'portrait'];

    public const QR_MIN_MM = 18.0;

    public const QR_MAKS_MM = 60.0;

    public const MARGIN_QR_MM = 5.0;

    /** @return array{0: float, 1: float} lebar, tinggi halaman A4 dalam mm */
    public static function ukuranHalaman(string $orientasi): array
    {
        return $orientasi === 'portrait' ? [210.0, 297.0] : [297.0, 210.0];
    }

    /** @return array<string, array<string, mixed>> */
    public static function bawaan(string $orientasi = 'landscape'): array
    {
        $dasar = ['rata' => 'tengah', 'tebal' => false, 'warna' => '#1a1c1c', 'font' => 'DejaVu Sans', 'tampil' => true];
        $l = $orientasi !== 'portrait';

        $tata = [
            'judul' => ['x' => 10, 'y' => $l ? 12 : 10, 'lebar' => 80, 'tinggi' => 9, 'ukuran_font' => 26, 'tebal' => true, 'warna' => '#1b6d24'],
            'pengantar' => ['x' => 10, 'y' => $l ? 28 : 22, 'lebar' => 80, 'tinggi' => 5, 'ukuran_font' => 12],
            'nama' => ['x' => 10, 'y' => $l ? 36 : 28, 'lebar' => 80, 'tinggi' => 11, 'ukuran_font' => 26, 'tebal' => true, 'warna' => '#1b6d24'],
            'asal_institusi' => ['x' => 10, 'y' => $l ? 49 : 38, 'lebar' => 80, 'tinggi' => 5, 'ukuran_font' => 12],
            'jenis_predikat' => ['x' => 10, 'y' => $l ? 56 : 44, 'lebar' => 80, 'tinggi' => 5, 'ukuran_font' => 12, 'tebal' => true],
            'kegiatan' => ['x' => 10, 'y' => $l ? 63 : 50, 'lebar' => 80, 'tinggi' => 9, 'ukuran_font' => 14, 'tebal' => true],
            'tanggal' => ['x' => 10, 'y' => $l ? 74 : 60, 'lebar' => 80, 'tinggi' => 5, 'ukuran_font' => 11],
            'nomor_seri' => ['x' => 10, 'y' => $l ? 91 : 94, 'lebar' => 50, 'tinggi' => 4, 'ukuran_font' => 8, 'rata' => 'kiri'],
            'qr' => ['x' => 85, 'y' => $l ? 74 : 82, 'lebar' => 10, 'tinggi' => 14, 'ukuran_font' => 7],
        ];
        $kolom = $l ? [8, 30, 52, 74] : [6, 28, 52, 74];
        foreach ([1, 2, 3, 4] as $i) {
            $tata["penandatangan_{$i}"] = ['x' => $kolom[$i - 1], 'y' => $l ? 80 : 70, 'lebar' => 20, 'tinggi' => 15, 'ukuran_font' => 8, 'tampil' => $i <= 2];
        }

        $hasil = [];
        foreach (self::ELEMEN as $kunci) {
            $hasil[$kunci] = self::jepit(array_merge($dasar, $tata[$kunci]), $kunci, $orientasi);
        }

        return $hasil;
    }

    /**
     * Validasi tata letak dari klien lalu jepit ke dalam halaman. Elemen yang tidak dikirim memakai bawaan.
     *
     * @param  array<string, mixed>  $layout
     * @return array<string, array<string, mixed>>
     *
     * @throws ValidationException
     */
    public static function validasi(array $layout, string $orientasi): array
    {
        $galat = [];
        if (! in_array($orientasi, self::ORIENTASI, true)) {
            throw ValidationException::withMessages(['orientasi' => 'Orientasi harus landscape atau portrait.']);
        }

        foreach (array_keys($layout) as $kunci) {
            if (! in_array($kunci, self::ELEMEN, true)) {
                $galat["layout.{$kunci}"] = "Elemen '{$kunci}' tidak dikenal.";
            }
        }

        $hasil = self::bawaan($orientasi);
        foreach (self::ELEMEN as $kunci) {
            if (! isset($layout[$kunci])) {
                continue;
            }
            $el = $layout[$kunci];
            if (! is_array($el)) {
                $galat["layout.{$kunci}"] = "Elemen '{$kunci}' tidak valid.";

                continue;
            }
            foreach (array_keys($el) as $prop) {
                if (! in_array($prop, self::PROPERTI, true)) {
                    $galat["layout.{$kunci}.{$prop}"] = "Properti '{$prop}' pada '{$kunci}' tidak dikenal.";
                }
            }
            $nilai = array_merge($hasil[$kunci], array_intersect_key($el, array_flip(self::PROPERTI)));

            foreach (['x' => [0, 100], 'y' => [0, 100], 'lebar' => [1, 100], 'tinggi' => [1, 100], 'ukuran_font' => [6, 72]] as $prop => [$min, $maks]) {
                if (! is_numeric($nilai[$prop]) || $nilai[$prop] < $min || $nilai[$prop] > $maks) {
                    $galat["layout.{$kunci}.{$prop}"] = "Nilai {$prop} pada '{$kunci}' harus antara {$min} dan {$maks}.";
                }
            }
            if (! in_array($nilai['rata'], self::RATA, true)) {
                $galat["layout.{$kunci}.rata"] = "Rata pada '{$kunci}' harus kiri, tengah, atau kanan.";
            }
            if (! is_bool($nilai['tebal']) || ! is_bool($nilai['tampil'])) {
                $galat["layout.{$kunci}.tebal"] = "Nilai tebal dan tampil pada '{$kunci}' harus benar atau salah.";
            }
            if (! is_string($nilai['warna']) || preg_match('/^#[0-9a-fA-F]{6}$/', $nilai['warna']) !== 1) {
                $galat["layout.{$kunci}.warna"] = "Warna pada '{$kunci}' harus berformat #RRGGBB.";
            }
            if (! in_array($nilai['font'], self::FONT, true)) {
                $galat["layout.{$kunci}.font"] = "Font '{$nilai['font']}' pada '{$kunci}' tidak tersedia. Pilihan: ".implode(', ', self::FONT).'.';
            }
            if (in_array($kunci, self::WAJIB, true) && $nilai['tampil'] !== true) {
                $galat["layout.{$kunci}.tampil"] = "Elemen '{$kunci}' wajib tampil dan tidak boleh disembunyikan.";
            }
            $hasil[$kunci] = $nilai;
        }

        if ($galat !== []) {
            throw ValidationException::withMessages($galat);
        }

        foreach (self::ELEMEN as $kunci) {
            $hasil[$kunci] = self::jepit($hasil[$kunci], $kunci, $orientasi);
        }

        return $hasil;
    }

    /**
     * Jepit satu elemen ke dalam halaman. QR: persegi, sisi 18-60 mm, margin 5 mm dari tepi halaman.
     *
     * @param  array<string, mixed>  $el
     * @return array<string, mixed>
     */
    public static function jepit(array $el, string $kunci, string $orientasi): array
    {
        [$w, $h] = self::ukuranHalaman($orientasi);
        $el = array_merge($el, [
            'x' => (float) $el['x'], 'y' => (float) $el['y'], 'lebar' => (float) $el['lebar'],
            'tinggi' => (float) $el['tinggi'], 'ukuran_font' => (float) $el['ukuran_font'],
        ]);

        if ($kunci === 'qr') {
            $sisi = max(self::QR_MIN_MM, min(self::QR_MAKS_MM, $el['lebar'] / 100 * $w));
            $xMm = max(self::MARGIN_QR_MM, min($w - self::MARGIN_QR_MM - $sisi, $el['x'] / 100 * $w));
            $yMm = max(self::MARGIN_QR_MM, min($h - self::MARGIN_QR_MM - $sisi, $el['y'] / 100 * $h));
            $el['lebar'] = round($sisi / $w * 100, 3);
            $el['tinggi'] = round($sisi / $h * 100, 3);
            $el['x'] = round($xMm / $w * 100, 3);
            $el['y'] = round($yMm / $h * 100, 3);
            $el['tampil'] = true;

            return $el;
        }

        $el['lebar'] = max(1.0, min(100.0, $el['lebar']));
        $el['tinggi'] = max(1.0, min(100.0, $el['tinggi']));
        $el['x'] = round(max(0.0, min(100.0 - $el['lebar'], $el['x'])), 3);
        $el['y'] = round(max(0.0, min(100.0 - $el['tinggi'], $el['y'])), 3);

        if (in_array($kunci, self::WAJIB, true)) {
            $el['tampil'] = true;
        }

        return $el;
    }
}
