<?php

namespace App\Services;

use ZipArchive;

class SimpleXlsxService
{
    /**
     * Netralkan string terhadap serangan formula injection (CSV / Excel injection).
     * Jika diawali =, +, -, @, \t, atau \r, tambahkan tanda petik tunggal di depannya.
     */
    public static function neutralizeFormula(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = ltrim($value);
        if ($trimmed !== '' && in_array($trimmed[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * Bersihkan string dari formula injection saat impor.
     * Jika string diawali karakter formula trigger (=, +, -, @), bersihkan karakter tersebut
     * serta tanda petik tunggal netralisasi agar tidak dieksekusi di spreadsheet downstream.
     */
    public static function sanitizeFormulaOnImport(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $str = (string) $value;
        $trimmed = ltrim($str);

        // Jika diawali tanda petik yang ditambahkan saat ekspor untuk netralisasi
        if (str_starts_with($trimmed, "'")) {
            $trimmed = ltrim(substr($trimmed, 1));
        }

        // Bersihkan karakter formula trigger di awal string
        while ($trimmed !== '' && in_array($trimmed[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            $trimmed = ltrim(substr($trimmed, 1));
        }

        return $trimmed;
    }

    /**
     * Periksa apakah sebuah string berpotensi mengandung formula injection.
     */
    public static function startsWithFormula(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $trimmed = ltrim($value);

        return $trimmed !== '' && in_array($trimmed[0], ['=', '+', '-', '@', "\t", "\r"], true);
    }

    /**
     * Konversi indeks kolom (1-based: 1 => A, 2 => B, 27 => AA, dst) ke huruf kolom Excel.
     */
    public static function columnIndexToLetter(int $colIndex): string
    {
        $letter = '';
        while ($colIndex > 0) {
            $mod = ($colIndex - 1) % 26;
            $letter = chr(65 + $mod).$letter;
            $colIndex = intval(($colIndex - $mod) / 26);
        }

        return $letter;
    }

    /**
     * Konversi huruf kolom Excel (A => 1, B => 2, AA => 27, dst) ke indeks kolom 1-based.
     */
    public static function letterToColumnIndex(string $letter): int
    {
        $letter = strtoupper($letter);
        $len = strlen($letter);
        $index = 0;
        for ($i = 0; $i < $len; $i++) {
            $index = $index * 26 + (ord($letter[$i]) - 64);
        }

        return $index;
    }

    /**
     * Buat berkas XLSX dalam memori dan simpan ke file path tujuan.
     *
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, mixed>>  $rows
     */
    public function createXlsxFile(array $headers, array $rows, string $destinationPath): void
    {
        $zip = new ZipArchive;
        if ($zip->open($destinationPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Gagal membuat berkas XLSX pada path: {$destinationPath}");
        }

        // [Content_Types].xml
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'</Types>';
        $zip->addFromString('[Content_Types].xml', $contentTypes);

        // _rels/.rels
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
        $zip->addFromString('_rels/.rels', $rels);

        // xl/_rels/workbook.xml.rels
        $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'</Relationships>';
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);

        // xl/workbook.xml
        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'
            .'<sheet name="Penilaian" sheetId="1" r:id="rId1"/>'
            .'</sheets>'
            .'</workbook>';
        $zip->addFromString('xl/workbook.xml', $workbook);

        // xl/worksheets/sheet1.xml
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetData>';

        // Baris 1: Header
        $xml .= '<row r="1">';
        foreach ($headers as $colIdx => $headerText) {
            $cellRef = self::columnIndexToLetter($colIdx + 1).'1';
            $escaped = htmlspecialchars((string) $headerText, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $xml .= '<c r="'.$cellRef.'" t="inlineStr"><is><t>'.$escaped.'</t></is></c>';
        }
        $xml .= '</row>';

        // Baris 2 dst: Data
        $rowNum = 2;
        foreach ($rows as $row) {
            $xml .= '<row r="'.$rowNum.'">';
            foreach (array_values($row) as $colIdx => $cellVal) {
                $cellRef = self::columnIndexToLetter($colIdx + 1).$rowNum;
                if ($cellVal === null || $cellVal === '') {
                    continue;
                }

                if (is_int($cellVal) || (is_numeric($cellVal) && ! str_starts_with((string) $cellVal, '0') && ! str_contains((string) $cellVal, ' '))) {
                    $xml .= '<c r="'.$cellRef.'"><v>'.$cellVal.'</v></c>';
                } else {
                    $neutralized = self::neutralizeFormula((string) $cellVal);
                    $escaped = htmlspecialchars($neutralized, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                    $xml .= '<c r="'.$cellRef.'" t="inlineStr"><is><t>'.$escaped.'</t></is></c>';
                }
            }
            $xml .= '</row>';
            $rowNum++;
        }

        $xml .= '</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml);

        $zip->close();
    }

    /**
     * Baca berkas XLSX dan kembalikan array berisi baris data (array of arrays).
     *
     * @return array<int, array<int, string>>
     */
    public function readXlsxFile(string $filePath): array
    {
        $zip = new ZipArchive;
        if ($zip->open($filePath) !== true) {
            throw new \RuntimeException('Gagal membuka berkas XLSX.');
        }

        // Baca shared strings jika ada
        $sharedStrings = [];
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedStringsXml !== false) {
            $xmlObj = simplexml_load_string($sharedStringsXml);
            if ($xmlObj && isset($xmlObj->si)) {
                foreach ($xmlObj->si as $si) {
                    if (isset($si->t)) {
                        $sharedStrings[] = (string) $si->t;
                    } elseif (isset($si->r)) {
                        $text = '';
                        foreach ($si->r as $r) {
                            $text .= (string) $r->t;
                        }
                        $sharedStrings[] = $text;
                    } else {
                        $sharedStrings[] = '';
                    }
                }
            }
        }

        // Cari sheet1
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXml === false) {
            // Coba temukan sheet apapun
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (str_starts_with($name, 'xl/worksheets/sheet') && str_ends_with($name, '.xml')) {
                    $sheetXml = $zip->getFromIndex($i);
                    break;
                }
            }
        }

        $zip->close();

        if ($sheetXml === false) {
            throw new \RuntimeException('Worksheet tidak ditemukan di dalam berkas XLSX.');
        }

        $xml = simplexml_load_string($sheetXml);
        if (! $xml || ! isset($xml->sheetData)) {
            return [];
        }

        $rows = [];
        foreach ($xml->sheetData->row as $rowNode) {
            $rowArr = [];
            foreach ($rowNode->c as $cell) {
                $ref = (string) $cell['r'];
                preg_match('/^([A-Z]+)(\d+)$/', $ref, $matches);
                $colLetter = $matches[1] ?? 'A';
                $colIdx = self::letterToColumnIndex($colLetter) - 1; // 0-based

                $type = (string) $cell['t'];
                $val = '';

                if ($type === 's') {
                    // Shared string index
                    $idx = (int) $cell->v;
                    $val = $sharedStrings[$idx] ?? '';
                } elseif ($type === 'inlineStr' && isset($cell->is->t)) {
                    $val = (string) $cell->is->t;
                } elseif (isset($cell->v)) {
                    $val = (string) $cell->v;
                }

                $rowArr[$colIdx] = $val;
            }

            // Normalisasi agar array memiliki indeks berurutan dari 0
            if (! empty($rowArr)) {
                $maxKey = max(array_keys($rowArr));
                $normalizedRow = [];
                for ($k = 0; $k <= $maxKey; $k++) {
                    $normalizedRow[$k] = $rowArr[$k] ?? '';
                }
                $rows[] = $normalizedRow;
            }
        }

        return $rows;
    }
}
