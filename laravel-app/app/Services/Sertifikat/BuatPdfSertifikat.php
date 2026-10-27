<?php

namespace App\Services\Sertifikat;

use App\Support\Sertifikat\FitTeks;
use App\Support\Sertifikat\TataLetak;
use App\Support\Sertifikat\TeksTemplat;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Menyusun HTML berposisi absolut (mm) di atas latar lalu mencetaknya menjadi PDF A4 dengan dompdf.
 * Semua teks di-escape; nama panjang dipecah baris atau dikecilkan sampai batas minimum.
 */
class BuatPdfSertifikat
{
    private const FONT_MIN_PT = 7.0;

    /**
     * @param  array<string, array<string, mixed>>  $layout  hasil TataLetak::validasi
     * @param  array<string, string>  $teks  judul, pengantar, penutup (penampung belum diisi)
     * @param  array<string, string|null>  $data  nama, asal_institusi, jenis, predikat, kegiatan, tanggal, nomor, tanggal_terbit
     * @param  list<array<string, mixed>>  $penandatangan  snapshot beku pada sertifikat
     */
    public function pdf(
        array $layout,
        array $teks,
        string $orientasi,
        ?string $latarDataUri,
        array $data,
        string $tautanVerifikasi,
        array $penandatangan,
        bool $contoh = false,
    ): string {
        $html = $this->html($layout, $teks, $orientasi, $latarDataUri, $data, $tautanVerifikasi, $penandatangan, $contoh);

        return Pdf::loadHTML($html)
            ->setPaper('a4', $orientasi)
            ->setOption('isRemoteEnabled', false)
            ->setOption('isHtml5ParserEnabled', true)
            ->output();
    }

    /** @return array<string, mixed> */
    public function html(
        array $layout,
        array $teks,
        string $orientasi,
        ?string $latarDataUri,
        array $data,
        string $tautanVerifikasi,
        array $penandatangan,
        bool $contoh = false,
    ): string {
        [$w, $h] = TataLetak::ukuranHalaman($orientasi);
        $e = fn (?string $s) => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $isi = fn (string $kunci) => TeksTemplat::isi($teks[$kunci] ?? '', $data);
        $jenisPredikat = trim(($data['jenis'] ?? '').(($data['predikat'] ?? '') !== '' ? ' - '.$data['predikat'] : ''));

        $teksElemen = [
            'judul' => $isi('judul'),
            'pengantar' => $isi('pengantar'),
            'nama' => (string) ($data['nama'] ?? ''),
            'asal_institusi' => (string) ($data['asal_institusi'] ?? ''),
            'jenis_predikat' => $jenisPredikat,
            'kegiatan' => $isi('penutup'),
            'tanggal' => (string) ($data['tanggal_terbit'] ?? ''),
            'nomor_seri' => 'No. '.($data['nomor'] ?? ''),
        ];

        $bagian = [];
        foreach (TataLetak::ELEMEN as $kunci) {
            $el = $layout[$kunci];
            if (! $el['tampil']) {
                continue;
            }
            $kiri = $el['x'] / 100 * $w;
            $atas = $el['y'] / 100 * $h;
            $lebar = $el['lebar'] / 100 * $w;
            $tinggi = $el['tinggi'] / 100 * $h;
            $posisi = sprintf('left:%.2fmm;top:%.2fmm;width:%.2fmm;height:%.2fmm;', $kiri, $atas, $lebar, $tinggi);
            $rata = ['kiri' => 'left', 'tengah' => 'center', 'kanan' => 'right'][$el['rata']];
            $gaya = sprintf(
                'font-family:%s;color:%s;font-weight:%s;text-align:%s;',
                $this->cssFont($el['font']),
                $el['warna'],
                $el['tebal'] ? 'bold' : 'normal',
                $rata,
            );

            if ($kunci === 'qr') {
                $plat = 2.0; // pelat putih agar QR terbaca di atas latar gelap
                $bagian[] = sprintf(
                    '<div style="position:absolute;%sbackground:#ffffff;"><img src="%s" style="position:absolute;left:%.2fmm;top:%.2fmm;width:%.2fmm;height:%.2fmm;"></div>',
                    $posisi,
                    $this->qrDataUri($tautanVerifikasi),
                    $plat,
                    $plat,
                    max(1.0, $lebar - 2 * $plat),
                    max(1.0, $tinggi - 2 * $plat),
                );

                continue;
            }

            if (str_starts_with($kunci, 'penandatangan_')) {
                $bagian[] = $this->blokPenandatangan($penandatangan[(int) substr($kunci, -1) - 1] ?? null, $posisi, $gaya, $el, $lebar, $tinggi, $e);

                continue;
            }

            $teksKotak = $teksElemen[$kunci] ?? '';
            if ($teksKotak === '') {
                continue;
            }
            $ukuran = FitTeks::ukuranFont($teksKotak, $lebar, $tinggi, $el['ukuran_font'], self::FONT_MIN_PT, $el['tebal']);
            $bagian[] = sprintf(
                '<div style="position:absolute;%s%sfont-size:%.1fpt;line-height:1.25;overflow:hidden;word-wrap:break-word;">%s</div>',
                $posisi,
                $gaya,
                $ukuran,
                nl2br($e($teksKotak)),
            );
        }

        $latar = $latarDataUri
            ? sprintf('<img src="%s" style="position:absolute;left:0;top:0;width:%.2fmm;height:%.2fmm;">', $latarDataUri, $w, $h)
            : '';
        $watermark = $contoh
            ? '<div style="position:absolute;left:0;top:'.round($h * 0.38, 2).'mm;width:'.$w.'mm;text-align:center;font-family:\'DejaVu Sans\';font-weight:bold;font-size:90pt;color:#d9d9d9;">CONTOH</div>'
            : '';

        return '<!doctype html><html><head><meta charset="utf-8"><style>@page{size:A4 '.$orientasi.';margin:0}'
            .'html,body{margin:0;padding:0}body{position:relative;width:'.$w.'mm;height:'.$h.'mm;}</style></head><body>'
            .$latar.implode('', $bagian).$watermark.'</body></html>';
    }

    private function cssFont(string $font): string
    {
        return in_array($font, TataLetak::FONT, true) ? "'{$font}'" : "'DejaVu Sans'";
    }

    /** @param  array<string, mixed>|null  $p */
    private function blokPenandatangan(?array $p, string $posisi, string $gaya, array $el, float $lebar, float $tinggi, callable $e): string
    {
        if ($p === null) {
            return '';
        }

        $gambar = '';
        $tinggiGambar = $tinggi * 0.42;
        if (! empty($p['tanda_tangan_path']) && Storage::exists($p['tanda_tangan_path'])) {
            $mime = str_ends_with(strtolower($p['tanda_tangan_path']), '.webp') ? 'image/webp' : 'image/png';
            $gambar = sprintf(
                '<img src="data:%s;base64,%s" style="height:%.2fmm;max-width:%.2fmm;">',
                $mime,
                base64_encode(Storage::get($p['tanda_tangan_path'])),
                $tinggiGambar,
                $lebar,
            );
        }

        $baris = array_filter([
            (string) ($p['nama_lengkap'] ?? $p['nama'] ?? ''),
            (string) ($p['jabatan'] ?? ''),
            (string) ($p['identitas_terformat'] ?? ''),
        ], fn ($s) => $s !== '');
        $ukuran = FitTeks::ukuranFont(implode("\n", $baris), $lebar, max(1.0, $tinggi - ($gambar !== '' ? $tinggiGambar : 0)), $el['ukuran_font'], 6.0, false);

        return sprintf(
            '<div style="position:absolute;%s%sfont-size:%.1fpt;line-height:1.25;overflow:hidden;word-wrap:break-word;">%s<div>%s</div></div>',
            $posisi,
            $gaya,
            $ukuran,
            $gambar,
            implode('<br>', array_map($e, $baris)),
        );
    }

    public function qrDataUri(string $tautan): string
    {
        $renderer = new ImageRenderer(new RendererStyle(256, 1), new SvgImageBackEnd);
        $svg = (new Writer($renderer))->writeString($tautan, 'utf-8', ErrorCorrectionLevel::H());

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
