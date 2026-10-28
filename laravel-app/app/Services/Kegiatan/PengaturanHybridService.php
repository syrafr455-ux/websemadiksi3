<?php

namespace App\Services\Kegiatan;

use App\Models\Kegiatan;
use Illuminate\Validation\ValidationException;

/**
 * Aturan form admin untuk kegiatan hybrid: pembagian kuota, kelengkapan saat Ajukan, dan peringatan hybrid lama.
 */
class PengaturanHybridService
{
    public function __construct(
        private readonly KuotaHybridService $kuota,
        private readonly DenahKursiService $denah,
    ) {}

    /**
     * Validasi pembagian kuota. $efektif = nilai setelah perubahan (metode_pelaksanaan, kuota, kuota_onsite,
     * is_seat_booking_active, seat_layout_config); $lama = kegiatan tersimpan (null saat membuat baru).
     *
     * @param  array<string, mixed>  $efektif
     *
     * @throws ValidationException
     */
    public function validasiKuota(array $efektif, ?Kegiatan $lama = null): void
    {
        if (($efektif['metode_pelaksanaan'] ?? null) !== 'hybrid') {
            return;
        }

        $total = (int) ($efektif['kuota'] ?? 0);
        $onsite = $efektif['kuota_onsite'] ?? null;

        if ($onsite === null) {
            // Hybrid lama tanpa pembagian tetap diizinkan selama belum pernah diatur.
            $hybridLama = $lama !== null && $lama->isHybrid() && $lama->kuota_onsite === null;
            if (! $hybridLama) {
                throw ValidationException::withMessages(['kuota_onsite' => 'Kuota onsite wajib diisi untuk kegiatan hybrid.']);
            }

            return;
        }

        $onsite = (int) $onsite;
        if ($total < 2) {
            throw ValidationException::withMessages(['kuota' => 'Kuota total kegiatan hybrid minimal 2 agar ada kuota onsite dan daring.']);
        }
        if ($onsite < 1 || $onsite > $total - 1) {
            throw ValidationException::withMessages(['kuota_onsite' => 'Kuota onsite harus antara 1 dan '.($total - 1).'. Sisanya otomatis menjadi kuota daring.']);
        }

        if (! empty($efektif['is_seat_booking_active']) && ! empty($efektif['seat_layout_config'])) {
            $kursi = $this->denah->kapasitas($efektif['seat_layout_config']);
            if ($onsite > $kursi) {
                throw ValidationException::withMessages(['kuota_onsite' => "Kuota onsite ({$onsite}) melebihi jumlah kursi denah ruangan ({$kursi})."]);
            }
        }

        if ($lama !== null && $lama->exists) {
            $terisi = $this->kuota->terisi($lama);
            if ($terisi['onsite'] > $onsite) {
                throw ValidationException::withMessages(['kuota_onsite' => "Kuota onsite tidak boleh lebih kecil dari pendaftar onsite aktif ({$terisi['onsite']})."]);
            }
            if ($terisi['daring'] > $total - $onsite) {
                throw ValidationException::withMessages(['kuota_onsite' => 'Kuota daring ('.($total - $onsite).") tidak boleh lebih kecil dari pendaftar daring aktif ({$terisi['daring']})."]);
            }
        }
    }

    /**
     * Bagian hybrid yang belum lengkap saat Ajukan, satu pesan per kekurangan. Kosong bila lengkap atau bukan hybrid.
     *
     * @param  array<string, mixed>  $data  metode_pelaksanaan, lokasi_type, lokasi_nama, lokasi_detail, lokasi_ruangan, tautan_rapat, kuota_onsite
     * @return list<string>
     */
    public function kekuranganAjukan(array $data): array
    {
        if (($data['metode_pelaksanaan'] ?? null) !== 'hybrid') {
            return [];
        }

        $pesan = [];
        $tipe = $data['lokasi_type'] ?? null;

        if ($tipe === 'unusa_campus') {
            if (blank($data['lokasi_ruangan'] ?? null)) {
                $pesan[] = 'Onsite: pilih ruangan UNUSA.';
            }
        } elseif ($tipe === 'external') {
            if (blank($data['lokasi_nama'] ?? null)) {
                $pesan[] = 'Onsite: nama venue eksternal wajib diisi.';
            }
            if (blank($data['lokasi_detail'] ?? null)) {
                $pesan[] = 'Onsite: alamat atau tautan peta venue eksternal wajib diisi.';
            }
        } else {
            $pesan[] = 'Onsite: pilih Ruangan UNUSA atau Venue eksternal.';
        }

        if (blank($data['tautan_rapat'] ?? null)) {
            $pesan[] = 'Via Zoom: tautan rapat wajib diisi.';
        }

        if (($data['kuota_onsite'] ?? null) === null) {
            $pesan[] = 'Kuota: kuota onsite wajib diisi.';
        }

        return $pesan;
    }

    public function kekuranganAjukanKegiatan(Kegiatan $kegiatan): array
    {
        return $this->kekuranganAjukan([
            'metode_pelaksanaan' => $kegiatan->metode_pelaksanaan,
            'lokasi_type' => $kegiatan->lokasi_type,
            'lokasi_nama' => $kegiatan->lokasi_nama,
            'lokasi_detail' => $kegiatan->lokasi_detail,
            'lokasi_ruangan' => $kegiatan->lokasi_ruangan,
            'tautan_rapat' => $kegiatan->tautan_rapat,
            'kuota_onsite' => $kegiatan->kuota_onsite,
        ]);
    }

    /**
     * Peringatan kuning untuk admin pada hybrid yang belum lengkap (hybrid lama tidak diblokir).
     *
     * @return list<string>
     */
    public function peringatan(Kegiatan $kegiatan): array
    {
        if (! $kegiatan->isHybrid()) {
            return [];
        }

        $hasil = [];
        if ($kegiatan->kuota_onsite === null) {
            $hasil[] = 'Atur pembagian kuota onsite dan daring.';
        }
        if (blank($kegiatan->tautan_rapat)) {
            $hasil[] = 'Tautan Zoom belum diisi.';
        }

        return $hasil;
    }

    /**
     * Ringkasan kuota untuk admin, mis. "Onsite 100/100 · Daring 12/50 · Daring: terbuka". Null bila tidak berpembagian.
     */
    public function ringkasan(Kegiatan $kegiatan): ?string
    {
        if (! $this->kuota->berpembagian($kegiatan)) {
            return null;
        }

        $s = $this->kuota->status($kegiatan);
        $statusDaring = ['belum_dibuka' => 'belum dibuka', 'terbuka' => 'terbuka', 'penuh' => 'penuh'][$s['daring']['status']];

        return "Onsite {$s['onsite']['terisi']}/{$s['onsite']['kuota']} · Daring {$s['daring']['terisi']}/{$s['daring']['kuota']} · Daring: {$statusDaring}";
    }
}
