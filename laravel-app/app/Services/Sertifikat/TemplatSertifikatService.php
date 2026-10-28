<?php

namespace App\Services\Sertifikat;

use App\Enums\JenisSertifikat;
use App\Models\AuditLog;
use App\Models\Kegiatan;
use App\Models\SertifikatTemplat;
use App\Models\User;
use App\Support\Sertifikat\TataLetak;
use App\Support\Sertifikat\TeksTemplat;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Templat sertifikat per kegiatan: baris bawaan (jenis NULL) dan penimpa per jenis.
 * Tidak ada templat berarti tata letak bawaan sistem. Latar disimpan di disk privat.
 */
class TemplatSertifikatService
{
    public const DISK = 'local';

    public const LATAR_MAKS_KB = 5120;

    public const LATAR_MAKS_PX = 4000;

    /**
     * Templat efektif untuk satu jenis: penimpa jenis lalu bawaan kegiatan lalu bawaan sistem.
     *
     * @return array{orientasi: string, layout: array, teks: array<string, string>, latar_path: ?string, versi: int}
     */
    public function untuk(Kegiatan $kegiatan, JenisSertifikat $jenis): array
    {
        $baris = SertifikatTemplat::where('kegiatan_id', $kegiatan->id)->get();
        $dasar = $baris->firstWhere('jenis', null);
        $timpa = $baris->firstWhere('jenis', $jenis->value);

        $orientasi = $timpa?->orientasi ?? $dasar?->orientasi ?? 'landscape';
        $layout = array_replace(TataLetak::bawaan($orientasi), $timpa?->layout ?? $dasar?->layout ?? []);
        foreach ($layout as $k => $el) {
            if (is_array($el) && ! array_key_exists('tampil', $el)) {
                $layout[$k]['tampil'] = true;
            }
        }
        $teks = array_replace(
            TeksTemplat::bawaan()[$jenis->value],
            $dasar?->teks[$jenis->value] ?? [],
            $timpa?->teks[$jenis->value] ?? [],
        );

        return [
            'orientasi' => $orientasi,
            'layout' => $layout,
            'teks' => $teks,
            'latar_path' => $timpa?->latar_path ?? $dasar?->latar_path,
            'versi' => (int) max($dasar?->versi ?? 0, $timpa?->versi ?? 0),
        ];
    }

    /**
     * Data lengkap untuk editor: bawaan sistem, templat tersimpan, dan penimpa per jenis.
     *
     * @return array<string, mixed>
     */
    public function untukEditor(Kegiatan $kegiatan): array
    {
        $baris = SertifikatTemplat::where('kegiatan_id', $kegiatan->id)->get();
        $dasar = $baris->firstWhere('jenis', null);

        $layoutEditor = array_replace(TataLetak::bawaan($dasar?->orientasi ?? 'landscape'), $dasar?->layout ?? []);
        foreach ($layoutEditor as $k => $el) {
            if (is_array($el) && ! array_key_exists('tampil', $el)) {
                $layoutEditor[$k]['tampil'] = true;
            }
        }

        return [
            'orientasi' => $dasar?->orientasi ?? 'landscape',
            'layout' => $layoutEditor,
            'teks' => array_replace_recursive(TeksTemplat::bawaan(), $dasar?->teks ?? []),
            'latar_url' => $dasar?->latar_path ? route('admin.kegiatan.sertifikat.latar', [$kegiatan->id]).'?v='.$dasar->versi : null,
            'versi' => $dasar?->versi ?? 0,
            'tersimpan' => $dasar !== null,
            'penimpa' => $baris->whereNotNull('jenis')->mapWithKeys(function (SertifikatTemplat $t) {
                $layoutPenimpa = array_replace(TataLetak::bawaan($t->orientasi), $t->layout ?? []);
                foreach ($layoutPenimpa as $k => $el) {
                    if (is_array($el) && ! array_key_exists('tampil', $el)) {
                        $layoutPenimpa[$k]['tampil'] = true;
                    }
                }

                return [$t->jenis => [
                    'orientasi' => $t->orientasi,
                    'layout' => $layoutPenimpa,
                    'teks' => $t->teks[$t->jenis] ?? [],
                    'versi' => $t->versi,
                ]];
            })->all(),
            'bawaan' => ['landscape' => TataLetak::bawaan('landscape'), 'portrait' => TataLetak::bawaan('portrait'), 'teks' => TeksTemplat::bawaan()],
            'aturan' => [
                'font' => TataLetak::FONT,
                'elemen' => TataLetak::ELEMEN,
                'wajib' => TataLetak::WAJIB,
                'penampung' => TeksTemplat::PENAMPUNG,
                'batas_teks' => TeksTemplat::BIDANG,
                'qr_min_mm' => TataLetak::QR_MIN_MM,
                'qr_maks_mm' => TataLetak::QR_MAKS_MM,
                'margin_qr_mm' => TataLetak::MARGIN_QR_MM,
            ],
        ];
    }

    /**
     * Simpan templat (bawaan bila $jenis null). Mengembalikan baris tersimpan; versi naik tiap simpan.
     *
     * @param  array<string, mixed>  $layout
     * @param  array<string, mixed>  $teks
     *
     * @throws ValidationException
     */
    public function simpan(Kegiatan $kegiatan, ?string $jenis, string $orientasi, array $layout, array $teks, User $oleh, ?string $ip = null): SertifikatTemplat
    {
        if ($jenis !== null && ! in_array($jenis, JenisSertifikat::values(), true)) {
            throw ValidationException::withMessages(['jenis' => 'Jenis sertifikat tidak dikenal.']);
        }
        $layoutBersih = TataLetak::validasi($layout, $orientasi);
        $teksBersih = TeksTemplat::validasi($teks);

        return DB::transaction(function () use ($kegiatan, $jenis, $orientasi, $layoutBersih, $teksBersih, $oleh, $ip) {
            $templat = SertifikatTemplat::firstOrNew(['kegiatan_id' => $kegiatan->id, 'jenis' => $jenis]);
            $templat->fill([
                'orientasi' => $orientasi,
                'layout' => $layoutBersih,
                'teks' => $teksBersih,
                'versi' => ($templat->versi ?? 0) + 1,
                'diubah_oleh' => $oleh->id,
            ])->save();

            AuditLog::record(
                $oleh->id,
                'sertifikat_templat_simpan',
                "Menyimpan templat sertifikat kegiatan '{$kegiatan->nama_kegiatan}' (jenis: ".($jenis ?? 'bawaan').", versi {$templat->versi}).",
                $kegiatan,
                ['kegiatan_id' => $kegiatan->id, 'jenis' => $jenis, 'versi' => $templat->versi, 'ip' => $ip],
            );

            return $templat;
        });
    }

    public function reset(Kegiatan $kegiatan, ?string $jenis, User $oleh): void
    {
        $baris = SertifikatTemplat::where('kegiatan_id', $kegiatan->id)->where('jenis', $jenis)->first();
        if ($baris === null) {
            return;
        }
        $this->hapusBerkasLatar($baris->latar_path);
        $baris->delete();

        AuditLog::record($oleh->id, 'sertifikat_templat_reset', "Mengembalikan templat sertifikat kegiatan '{$kegiatan->nama_kegiatan}' (jenis: ".($jenis ?? 'bawaan').') ke bawaan.', $kegiatan);
    }

    /**
     * Latar png/jpg: MIME diperiksa dari isi berkas, dimensi dibatasi, lalu dibuat ulang lewat GD untuk membuang metadata.
     *
     * @return array{path: string, rasio_cocok: bool}
     *
     * @throws ValidationException
     */
    public function simpanLatar(Kegiatan $kegiatan, UploadedFile $berkas, User $oleh): array
    {
        $galat = fn (string $pesan) => ValidationException::withMessages(['latar' => $pesan]);

        if ($berkas->getSize() === false || $berkas->getSize() > self::LATAR_MAKS_KB * 1024) {
            throw $galat('Ukuran latar maksimal 5 MB.');
        }
        $isi = file_get_contents($berkas->getRealPath());
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($isi);
        if (! in_array($mime, ['image/png', 'image/jpeg'], true)) {
            throw $galat('Latar harus berupa gambar PNG atau JPG asli (SVG dan jenis lain tidak diterima).');
        }
        $ukuran = @getimagesizefromstring($isi);
        if ($ukuran === false) {
            throw $galat('Berkas gambar tidak dapat dibaca.');
        }
        [$lebar, $tinggi] = $ukuran;
        if ($lebar > self::LATAR_MAKS_PX || $tinggi > self::LATAR_MAKS_PX) {
            throw $galat('Dimensi latar maksimal '.self::LATAR_MAKS_PX.' x '.self::LATAR_MAKS_PX.' piksel.');
        }

        $ekstensi = $mime === 'image/png' ? 'png' : 'jpg';
        if (function_exists('imagecreatefromstring')) {
            $gambar = @imagecreatefromstring($isi);
            if ($gambar === false) {
                throw $galat('Berkas gambar rusak atau tidak dapat diproses.');
            }
            ob_start();
            $mime === 'image/png' ? imagepng($gambar) : imagejpeg($gambar, null, 92);
            $isi = (string) ob_get_clean();
            imagedestroy($gambar);
        }

        $path = 'sertifikat/latar/'.Str::random(40).'.'.$ekstensi;
        Storage::disk(self::DISK)->put($path, $isi);

        $templat = SertifikatTemplat::firstOrNew(['kegiatan_id' => $kegiatan->id, 'jenis' => null]);
        $lama = $templat->latar_path;
        // Orientasi mengikuti gambar latar (potret bila lebih tinggi daripada lebar); tata letak dijepit ke halaman baru.
        $orientasi = $tinggi > $lebar ? 'portrait' : 'landscape';
        $templat->fill([
            'orientasi' => $orientasi,
            'layout' => $this->layoutUntukOrientasi($templat->layout, $orientasi),
            'teks' => $templat->teks ?? [],
            'latar_path' => $path,
            'versi' => ($templat->versi ?? 0) + 1,
            'diubah_oleh' => $oleh->id,
        ])->save();
        $this->hapusBerkasLatar($lama);

        AuditLog::record($oleh->id, 'sertifikat_templat_latar', "Mengunggah latar templat sertifikat kegiatan '{$kegiatan->nama_kegiatan}'.", $kegiatan);

        $rasio = $lebar / max(1, $tinggi);
        $harapan = $orientasi === 'portrait' ? 210 / 297 : 297 / 210;

        return ['path' => $path, 'rasio_cocok' => abs($rasio - $harapan) / $harapan <= 0.03];
    }

    public function hapusLatar(Kegiatan $kegiatan, User $oleh): void
    {
        $templat = SertifikatTemplat::where('kegiatan_id', $kegiatan->id)->whereNull('jenis')->first();
        if ($templat?->latar_path === null) {
            return;
        }
        $this->hapusBerkasLatar($templat->latar_path);
        $templat->update([
            'latar_path' => null,
            'orientasi' => 'landscape',
            'layout' => $this->layoutUntukOrientasi($templat->layout, 'landscape'),
            'versi' => $templat->versi + 1,
            'diubah_oleh' => $oleh->id,
        ]);

        AuditLog::record($oleh->id, 'sertifikat_templat_latar_hapus', "Menghapus latar templat sertifikat kegiatan '{$kegiatan->nama_kegiatan}'.", $kegiatan);
    }

    /** Salin semua baris templat dari kegiatan lain (latar disalin ke nama acak baru). */
    public function salinDari(Kegiatan $sumber, Kegiatan $tujuan, User $oleh): int
    {
        if ($sumber->id === $tujuan->id) {
            throw ValidationException::withMessages(['sumber' => 'Pilih kegiatan lain sebagai sumber salinan.']);
        }
        $baris = SertifikatTemplat::where('kegiatan_id', $sumber->id)->get();
        if ($baris->isEmpty()) {
            throw ValidationException::withMessages(['sumber' => 'Kegiatan sumber belum memiliki templat.']);
        }

        DB::transaction(function () use ($baris, $tujuan, $oleh) {
            foreach (SertifikatTemplat::where('kegiatan_id', $tujuan->id)->get() as $lama) {
                $this->hapusBerkasLatar($lama->latar_path);
                $lama->delete();
            }
            foreach ($baris as $b) {
                $latar = null;
                if ($b->latar_path && Storage::disk(self::DISK)->exists($b->latar_path)) {
                    $latar = 'sertifikat/latar/'.Str::random(40).'.'.pathinfo($b->latar_path, PATHINFO_EXTENSION);
                    Storage::disk(self::DISK)->copy($b->latar_path, $latar);
                }
                SertifikatTemplat::create([
                    'kegiatan_id' => $tujuan->id, 'jenis' => $b->jenis, 'orientasi' => $b->orientasi, 'latar_path' => $latar,
                    'layout' => $b->layout, 'teks' => $b->teks, 'versi' => 1, 'diubah_oleh' => $oleh->id,
                ]);
            }
        });

        AuditLog::record($oleh->id, 'sertifikat_templat_salin', "Menyalin templat sertifikat dari '{$sumber->nama_kegiatan}' ke '{$tujuan->nama_kegiatan}'.", $tujuan);

        return $baris->count();
    }

    public function dataUriLatar(?string $path): ?string
    {
        if (! $path || ! Storage::disk(self::DISK)->exists($path)) {
            return null;
        }
        $mime = str_ends_with($path, '.png') ? 'image/png' : 'image/jpeg';

        return 'data:'.$mime.';base64,'.base64_encode(Storage::disk(self::DISK)->get($path));
    }

    /** Tata letak tersimpan dijepit ke halaman A4 berorientasi baru; tanpa tata letak tersimpan, memakai bawaan. */
    private function layoutUntukOrientasi(?array $layout, string $orientasi): array
    {
        return $layout === null || $layout === [] ? TataLetak::bawaan($orientasi) : TataLetak::validasi($layout, $orientasi);
    }

    private function hapusBerkasLatar(?string $path): void
    {
        if ($path && str_starts_with($path, 'sertifikat/latar/')) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
