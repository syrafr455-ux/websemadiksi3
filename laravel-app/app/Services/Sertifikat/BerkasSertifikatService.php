<?php

namespace App\Services\Sertifikat;

use App\Enums\JenisSertifikat;
use App\Enums\StatusSertifikat;
use App\Models\AuditLog;
use App\Models\Kegiatan;
use App\Models\Sertifikat;
use App\Models\SertifikatRiwayatPerubahan;
use App\Models\User;
use App\Support\Wib;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Berkas PDF sertifikat dibuat lazy (saat pertama diunduh atau lewat tombol batch), bukan saat penerbitan.
 * Berkas yang sudah ada tidak pernah dibuat ulang otomatis supaya hash tetap cocok.
 */
class BerkasSertifikatService
{
    public const DISK = 'local';

    public const UKURAN_BATCH = 20;

    public function __construct(
        private readonly TemplatSertifikatService $templat,
        private readonly BuatPdfSertifikat $pembuat,
    ) {}

    /**
     * Pastikan berkas ada. Dua permintaan serentak membuat satu berkas (kunci per sertifikat).
     * Kegagalan menandai file_status = gagal beserta alasan, tanpa melempar galat.
     */
    public function pastikan(Sertifikat $sertifikat): Sertifikat
    {
        if ($sertifikat->adaBerkas()) {
            return $sertifikat;
        }

        try {
            return Cache::lock("sertifikat-berkas:{$sertifikat->id}", 60)->block(15, function () use ($sertifikat) {
                $segar = Sertifikat::findOrFail($sertifikat->id);
                if ($segar->adaBerkas()) {
                    return $segar;
                }

                return $this->tulis($segar);
            });
        } catch (LockTimeoutException) {
            return Sertifikat::findOrFail($sertifikat->id);
        }
    }

    /**
     * Buat berkas untuk sertifikat aktif yang belum punya, paling banyak UKURAN_BATCH per panggilan.
     * Kegagalan satu sertifikat tidak menghentikan yang lain.
     *
     * @return array{diproses: int, berhasil: int, gagal: int, sisa: int, kursor: int}
     */
    public function batch(Kegiatan $kegiatan, int $setelahId = 0): array
    {
        $dasar = fn () => Sertifikat::where('kegiatan_id', $kegiatan->id)
            ->where('status', StatusSertifikat::Aktif)
            ->whereNull('file_pdf_path');

        $daftar = $dasar()->where('id', '>', $setelahId)->orderBy('id')->limit(self::UKURAN_BATCH)->get();
        $berhasil = 0;
        $gagal = 0;
        foreach ($daftar as $sertifikat) {
            $hasil = $this->pastikan($sertifikat);
            $hasil->adaBerkas() ? $berhasil++ : $gagal++;
        }
        $kursor = (int) ($daftar->last()?->id ?? $setelahId);

        return [
            'diproses' => $daftar->count(),
            'berhasil' => $berhasil,
            'gagal' => $gagal,
            'sisa' => $dasar()->where('id', '>', $kursor)->count(),
            'kursor' => $kursor,
        ];
    }

    /** Buat ulang berkas atas permintaan eksplisit super_admin; hash lama dan baru dicatat di riwayat. */
    public function buatUlang(Sertifikat $sertifikat, User $oleh, string $alasan, ?string $ip = null): Sertifikat
    {
        $hashLama = $sertifikat->document_hash;
        $pathLama = $sertifikat->file_pdf_path;
        $diskLama = $sertifikat->diskBerkas();

        $baru = $this->tulis($sertifikat->fresh());
        if (! $baru->adaBerkas()) {
            return $baru;
        }

        SertifikatRiwayatPerubahan::create([
            'sertifikat_id' => $sertifikat->id,
            'user_id' => $oleh->id,
            'ip_address' => $ip,
            'kolom_diubah' => 'document_hash',
            'nilai_lama' => $hashLama,
            'nilai_baru' => $baru->document_hash,
            'alasan' => $alasan,
        ]);
        AuditLog::record($oleh->id, 'sertifikat_berkas_dibuat_ulang', "Membuat ulang berkas sertifikat {$sertifikat->nomor_sertifikat}.", $sertifikat, ['alasan' => $alasan]);

        // Berkas privat lama dibuang; berkas lama di disk publik dibiarkan (tidak pernah dihapus otomatis).
        if ($pathLama && $pathLama !== $baru->file_pdf_path && $diskLama === self::DISK) {
            Storage::disk(self::DISK)->delete($pathLama);
        }

        return $baru;
    }

    private function tulis(Sertifikat $sertifikat): Sertifikat
    {
        try {
            $kegiatan = $sertifikat->kegiatan()->first();
            $jenis = $sertifikat->jenis instanceof JenisSertifikat ? $sertifikat->jenis : JenisSertifikat::Peserta;
            $templat = $this->templat->untuk($kegiatan, $jenis);

            $pdf = $this->pembuat->pdf(
                layout: $templat['layout'],
                teks: $templat['teks'],
                orientasi: $templat['orientasi'],
                latarDataUri: $this->templat->dataUriLatar($templat['latar_path']),
                data: [
                    'nama' => $sertifikat->nama_penerima,
                    'asal_institusi' => $sertifikat->asal_institusi,
                    'jenis' => $jenis->label(),
                    'predikat' => $sertifikat->predikat,
                    'kegiatan' => $kegiatan->nama_kegiatan,
                    'tanggal' => $kegiatan->tanggal_mulai ? Wib::dari($kegiatan->tanggal_mulai)->translatedFormat('d F Y') : '',
                    'nomor' => $sertifikat->nomor_sertifikat,
                    'tanggal_terbit' => 'Surabaya, '.Wib::dari($sertifikat->issued_at ?? now())->translatedFormat('d F Y'),
                ],
                tautanVerifikasi: $sertifikat->verification_url,
                penandatangan: $sertifikat->penandatangan_snapshot ?? [],
            );

            if (! str_starts_with($pdf, '%PDF')) {
                throw new \RuntimeException('Keluaran bukan PDF yang valid.');
            }

            $path = 'sertifikat/berkas/'.Str::random(40).'.pdf';
            Storage::disk(self::DISK)->put($path, $pdf);

            $sertifikat->forceFill([
                'file_pdf_path' => $path,
                'file_disk' => self::DISK,
                'document_hash' => hash('sha256', $pdf),
                'file_status' => null,
                'file_alasan_gagal' => null,
                'file_dibuat_pada' => now(),
                'file_templat_versi' => $templat['versi'],
            ])->save();
        } catch (Throwable $e) {
            report($e);
            $sertifikat->forceFill([
                'file_status' => 'gagal',
                'file_alasan_gagal' => Str::limit($e->getMessage(), 300, '...'),
            ])->save();
        }

        return $sertifikat;
    }
}
