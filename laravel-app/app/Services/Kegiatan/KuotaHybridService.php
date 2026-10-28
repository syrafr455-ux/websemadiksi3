<?php

namespace App\Services\Kegiatan;

use App\Enums\CaraHadir;
use App\Models\Kegiatan;
use App\Models\KegiatanRegistration;

/**
 * Kuota terbagi untuk kegiatan hybrid: kuota total (kolom kuota) = kuota onsite (kolom kuota_onsite) + kuota daring
 * (selisihnya, tidak disimpan). Pendaftaran daring terbuka otomatis hanya saat kuota onsite penuh.
 *
 * "Terisi" dihitung langsung dari pendaftaran yang menahan kursi (RegistrationPaymentStatus::activeValues();
 * dibatalkan, expired, dan gagal tidak dihitung), TIDAK memakai accessor Kegiatan::kuota_terisi. Tidak ada status
 * tersimpan: setiap permintaan menghitung ulang. Pemanggil di jalur pendaftaran wajib berada di dalam transaksi
 * dengan baris kegiatan terkunci (lockForUpdate) agar dua pendaftaran bersamaan tidak melewati batas.
 */
class KuotaHybridService
{
    public const DARING_BELUM_DIBUKA = 'belum_dibuka';

    public const DARING_TERBUKA = 'terbuka';

    public const DARING_PENUH = 'penuh';

    /** Hybrid yang kuota_onsite-nya sudah diisi. Hybrid lama (kuota_onsite kosong) memakai satu kuota total. */
    public function berpembagian(Kegiatan $kegiatan): bool
    {
        return $kegiatan->isHybrid() && $kegiatan->kuota_onsite !== null;
    }

    public function kuotaOnsite(Kegiatan $kegiatan): int
    {
        return (int) $kegiatan->kuota_onsite;
    }

    public function kuotaDaring(Kegiatan $kegiatan): int
    {
        return max(0, (int) $kegiatan->kuota - (int) $kegiatan->kuota_onsite);
    }

    /**
     * Cara hadir otomatis untuk kegiatan non-hybrid: daring murni = daring, selain itu onsite.
     */
    public function caraHadirOtomatis(Kegiatan $kegiatan): CaraHadir
    {
        return $kegiatan->isOnline() ? CaraHadir::Daring : CaraHadir::Onsite;
    }

    /**
     * @return array{onsite: int, daring: int}
     */
    public function terisi(Kegiatan $kegiatan): array
    {
        $hitung = KegiatanRegistration::query()
            ->where('kegiatan_id', $kegiatan->id)
            ->active()
            ->selectRaw('cara_hadir, count(*) as jumlah')
            ->groupBy('cara_hadir')
            ->pluck('jumlah', 'cara_hadir');

        return [
            'onsite' => (int) ($hitung[CaraHadir::Onsite->value] ?? 0),
            'daring' => (int) ($hitung[CaraHadir::Daring->value] ?? 0),
        ];
    }

    /**
     * Ringkasan untuk tampilan dan penegakan.
     *
     * @return array{onsite: array{terisi: int, kuota: int, sisa: int, penuh: bool}, daring: array{terisi: int, kuota: int, sisa: int, status: string, tersedia: bool}, tersedia: bool}
     */
    public function status(Kegiatan $kegiatan): array
    {
        $terisi = $this->terisi($kegiatan);
        $kuotaOnsite = $this->kuotaOnsite($kegiatan);
        $kuotaDaring = $this->kuotaDaring($kegiatan);

        $onsitePenuh = $terisi['onsite'] >= $kuotaOnsite;
        $daringStatus = ! $onsitePenuh
            ? self::DARING_BELUM_DIBUKA
            : ($terisi['daring'] >= $kuotaDaring ? self::DARING_PENUH : self::DARING_TERBUKA);

        $sisaOnsite = max(0, $kuotaOnsite - $terisi['onsite']);
        $sisaDaring = max(0, $kuotaDaring - $terisi['daring']);

        return [
            'onsite' => ['terisi' => $terisi['onsite'], 'kuota' => $kuotaOnsite, 'sisa' => $sisaOnsite, 'penuh' => $onsitePenuh],
            'daring' => ['terisi' => $terisi['daring'], 'kuota' => $kuotaDaring, 'sisa' => $sisaDaring, 'status' => $daringStatus, 'tersedia' => $daringStatus === self::DARING_TERBUKA],
            'tersedia' => $sisaOnsite > 0 || $daringStatus === self::DARING_TERBUKA,
        ];
    }

    /**
     * Pesan penolakan bila $jumlah pendaftar dengan $cara tidak boleh dibuat sekarang; null bila boleh.
     */
    public function periksa(Kegiatan $kegiatan, CaraHadir $cara, int $jumlah): ?string
    {
        $status = $this->status($kegiatan);

        if ($cara === CaraHadir::Onsite) {
            if ($status['onsite']['sisa'] === 0) {
                return 'Kuota onsite sudah penuh. Pendaftaran via Zoom akan dibuka otomatis.';
            }

            return $jumlah > $status['onsite']['sisa']
                ? "Sisa kuota onsite hanya {$status['onsite']['sisa']}, kurang dari {$jumlah} peserta yang didaftarkan."
                : null;
        }

        if ($status['daring']['status'] === self::DARING_BELUM_DIBUKA) {
            return 'Pendaftaran via Zoom dibuka otomatis setelah kuota onsite penuh.';
        }
        if ($status['daring']['sisa'] === 0) {
            return 'Kuota via Zoom sudah penuh.';
        }

        return $jumlah > $status['daring']['sisa']
            ? "Sisa kuota via Zoom hanya {$status['daring']['sisa']}, kurang dari {$jumlah} peserta yang didaftarkan."
            : null;
    }
}
