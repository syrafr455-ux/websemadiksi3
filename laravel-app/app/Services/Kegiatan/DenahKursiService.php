<?php

namespace App\Services\Kegiatan;

use App\Enums\RegistrationPaymentStatus;
use App\Enums\SeatStatus;
use App\Enums\SeatTier;
use App\Jobs\SendSeatReselectionNotificationJob;
use App\Models\Kegiatan;
use App\Models\KegiatanRegistration;
use App\Models\KegiatanSeat;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Satu-satunya jalur untuk membuat dan mengganti denah kursi kegiatan.
 * Dipakai form kegiatan (admin.kegiatan.store/update), endpoint venue-config, dan tab "Denah & Booking",
 * sehingga pengaman kapasitas, reset kursi, riwayat kursi, dan email pilih ulang selalu sama.
 * Pemanggil wajib membungkusnya dalam transaksi database.
 *
 * Denah (seat_layout_config): rows (label baris), cols_per_row, vip_rows, panitia_rows, dan opsional
 * blocked_seats (kode kursi yang diblokir, mis. "A03"). Mengubah baris/kolom membuat ulang kursi (reset);
 * mengubah VIP/panitia/blokir hanya menyinkronkan kursi yang ada.
 */
class DenahKursiService
{
    public const DEFAULT_LAYOUT = [
        'rows' => ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'],
        'cols_per_row' => 10,
        'aisle_after_col' => 5,
        'vip_rows' => ['A', 'B'],
        'panitia_rows' => [],
    ];

    public const PRESET_BIOSKOP_12 = [
        'rows' => ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'],
        'cols_per_row' => 12,
        'aisle_after_col' => 6,
        'vip_rows' => ['A', 'B'],
        'panitia_rows' => [],
    ];

    /**
     * Bentuk baku denah agar konfigurasi lama dan baru bisa dibandingkan.
     * Key yang tidak dikirim memakai default; key yang dikirim kosong (null) berarti daftar kosong.
     * blocked_seats hanya disertakan bila ada isinya (konfigurasi lama tetap sama bentuknya).
     *
     * @return array{rows: list<string>, cols_per_row: int, vip_rows: list<string>, panitia_rows: list<string>, aisle_after_col?: int, blocked_seats?: list<string>}
     */
    public function normalisasi(?array $layout): array
    {
        $layout ??= [];
        $daftar = fn (string $key, array $default = []): array => array_values(array_unique(array_filter(array_map(
            fn ($nilai) => strtoupper(trim((string) $nilai)),
            array_key_exists($key, $layout) ? (array) ($layout[$key] ?? []) : $default
        ), fn (string $nilai) => $nilai !== '')));

        $cols = (int) ($layout['cols_per_row'] ?? self::DEFAULT_LAYOUT['cols_per_row']);

        $hasil = [
            'rows' => $daftar('rows', self::DEFAULT_LAYOUT['rows']),
            'cols_per_row' => $cols,
            'vip_rows' => $daftar('vip_rows', self::DEFAULT_LAYOUT['vip_rows']),
            'panitia_rows' => $daftar('panitia_rows'),
        ];

        // Gang tengah (aisle): disertakan bila dikirim (konfigurasi lama tetap sama bentuknya)
        if (array_key_exists('aisle_after_col', $layout) && $layout['aisle_after_col'] !== null && $layout['aisle_after_col'] !== '') {
            $hasil['aisle_after_col'] = max(0, min($cols, (int) $layout['aisle_after_col']));
        }

        $diblokir = $daftar('blocked_seats');
        sort($diblokir);
        if ($diblokir !== []) {
            $hasil['blocked_seats'] = $diblokir;
        }

        return $hasil;
    }

    /**
     * Posisi kolom pembatas gang tengah (aisle). Default tengah baris bila tidak dikonfigurasi.
     */
    public function aisleAfterCol(array $layout): ?int
    {
        $norm = $this->normalisasi($layout);
        $col = $norm['aisle_after_col'] ?? ($norm['cols_per_row'] > 1 ? (int) floor($norm['cols_per_row'] / 2) : null);

        return ($col !== null && $col > 0 && $col < $norm['cols_per_row']) ? $col : null;
    }

    public static function kodeKursi(string $baris, int $kolom): string
    {
        return $baris.str_pad((string) $kolom, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Jumlah kursi yang bisa dipesan peserta (baris panitia dan kursi diblokir tidak dihitung).
     */
    public function kapasitas(array $layout): int
    {
        $layout = $this->normalisasi($layout);
        $diblokir = $layout['blocked_seats'] ?? [];
        $jumlah = 0;

        foreach (array_diff($layout['rows'], $layout['panitia_rows']) as $baris) {
            for ($kolom = 1; $kolom <= $layout['cols_per_row']; $kolom++) {
                if (! in_array(self::kodeKursi($baris, $kolom), $diblokir, true)) {
                    $jumlah++;
                }
            }
        }

        return $jumlah;
    }

    /**
     * Peserta aktif yang sedang memegang kursi. Registrasi expired/failed tidak dihitung,
     * termasuk kursinya yang mungkin masih berstatus booked.
     */
    public function jumlahKursiTerisi(Kegiatan $kegiatan): int
    {
        $kursiRegistrasiMati = KegiatanRegistration::where('kegiatan_id', $kegiatan->id)
            ->whereIn('status_pembayaran', [
                RegistrationPaymentStatus::Expired->value,
                RegistrationPaymentStatus::Failed->value,
            ])
            ->whereNotNull('seat_id')
            ->pluck('seat_id')
            ->all();

        $registrasiAktif = KegiatanRegistration::where('kegiatan_id', $kegiatan->id)
            ->active()
            ->whereNotNull('seat_id')
            ->count();

        $kursiBooked = $kegiatan->seats()
            ->where('status', SeatStatus::Booked->value)
            ->whereNotIn('id', $kursiRegistrasiMati)
            ->count();

        return max($registrasiAktif, $kursiBooked);
    }

    public function pesanKapasitasKurang(int $kapasitasBaru, int $terisi): string
    {
        return "Pemindahan ruangan dibatalkan: Kapasitas baru ({$kapasitasBaru} kursi) tidak mencukupi untuk menampung {$terisi} peserta yang sudah memiliki kursi.";
    }

    /**
     * Tolak kapasitas baru yang lebih kecil dari jumlah kursi terisi (pesan sama untuk semua jalur).
     *
     * @throws ValidationException
     */
    public function pastikanKapasitasCukup(Kegiatan $kegiatan, int $kapasitasBaru): int
    {
        $terisi = $this->jumlahKursiTerisi($kegiatan);

        if ($kapasitasBaru < $terisi) {
            throw ValidationException::withMessages(['capacity' => $this->pesanKapasitasKurang($kapasitasBaru, $terisi)]);
        }

        return $terisi;
    }

    /**
     * Buat denah untuk kegiatan yang belum punya kursi.
     */
    public function buat(Kegiatan $kegiatan, ?array $layout): array
    {
        $layout = $this->normalisasi($layout);
        $this->buatKursi($kegiatan, $layout);

        return $layout;
    }

    /**
     * Apakah denah baru mengubah susunan kursi (label baris atau jumlah kolom), sehingga kursi harus dibuat ulang.
     */
    public function strukturBerubah(Kegiatan $kegiatan, array $layout): bool
    {
        $layout = $this->normalisasi($layout);
        $kursi = $kegiatan->seats()->get(['row_label', 'col_number']);
        if ($kursi->isEmpty()) {
            return true;
        }

        $barisSekarang = $kursi->pluck('row_label')->map(fn ($b) => strtoupper($b))->unique()->sort()->values()->all();
        $barisBaru = collect($layout['rows'])->sort()->values()->all();

        return $barisSekarang !== $barisBaru || (int) $kursi->max('col_number') !== $layout['cols_per_row'];
    }

    /**
     * Terapkan denah: buat ulang kursi bila susunannya berubah (lihat ganti()), atau cukup sinkronkan
     * tier/panitia/blokir kursi yang ada. Kapasitas selalu dicek.
     *
     * @throws ValidationException
     */
    public function terapkan(Kegiatan $kegiatan, ?array $layout, ?int $kapasitasBaru = null, ?string $ruanganLama = null): array
    {
        $layout = $this->normalisasi($layout);

        if ($this->strukturBerubah($kegiatan, $layout)) {
            return $this->ganti($kegiatan, $layout, $kapasitasBaru, $ruanganLama);
        }

        $this->pastikanKapasitasCukup($kegiatan, $kapasitasBaru ?? $this->kapasitas($layout));

        return $this->sinkronkan($kegiatan, $layout);
    }

    /**
     * Ganti denah kursi. Kapasitas dicek dulu; bila ada peserta yang memegang kursi, kursinya direset
     * (riwayat dicatat di seat_history, needs_seat_reselection = true) dan email pilih ulang dikirim
     * setelah transaksi selesai. venue_change_count naik bila kegiatan sudah punya denah sebelumnya.
     *
     * @param  int|null  $kapasitasBaru  null = dihitung dari denah baru
     * @param  string|null  $ruanganLama  nama lokasi sebelum perubahan (untuk seat_history)
     * @return array denah baru dalam bentuk baku
     *
     * @throws ValidationException
     */
    public function ganti(Kegiatan $kegiatan, ?array $layout, ?int $kapasitasBaru = null, ?string $ruanganLama = null): array
    {
        $layout = $this->normalisasi($layout);
        $terisi = $this->pastikanKapasitasCukup($kegiatan, $kapasitasBaru ?? $this->kapasitas($layout));
        $sudahAdaDenah = $kegiatan->seats()->exists();

        if ($terisi > 0) {
            $ruanganLama = $ruanganLama ?: ($kegiatan->getOriginal('lokasi_nama') ?: 'Ruangan Sebelumnya');
            $waktu = now()->utc()->toIso8601String();

            foreach ($this->registrasiBerkursi($kegiatan)->load('seat') as $reg) {
                $riwayat = is_array($reg->seat_history) ? $reg->seat_history : [];
                $riwayat[] = [
                    'venue' => $ruanganLama,
                    'seat' => $reg->seat?->seat_code ?? 'N/A',
                    'changed_at' => $waktu,
                ];

                $reg->update([
                    'seat_id' => null,
                    'needs_seat_reselection' => true,
                    'seat_history' => $riwayat,
                ]);
            }
        }

        $kegiatan->seats()->delete();
        $this->buatKursi($kegiatan, $layout);

        if ($sudahAdaDenah) {
            $kegiatan->increment('venue_change_count');
        }

        if ($terisi > 0) {
            SendSeatReselectionNotificationJob::dispatch($kegiatan)->afterCommit();
        }

        return $layout;
    }

    /**
     * Perbarui tier dan status kursi yang ada sesuai baris VIP/panitia dan kursi diblokir, tanpa mereset.
     * Kursi yang sedang dipegang peserta tidak boleh dijadikan panitia atau diblokir.
     *
     * @throws ValidationException
     */
    public function sinkronkan(Kegiatan $kegiatan, ?array $layout): array
    {
        $layout = $this->normalisasi($layout);
        $kursi = $kegiatan->seats()->get();

        $konflik = $this->kursiBentrok($kursi, $layout);
        if ($konflik !== []) {
            throw ValidationException::withMessages([
                'layout' => 'Kursi '.implode(', ', $konflik).' sedang dipakai peserta sehingga tidak bisa dijadikan kursi panitia atau diblokir.',
            ]);
        }

        foreach ($kursi as $seat) {
            $tier = $this->tierBaris(strtoupper($seat->row_label), $layout);
            $atribut = ['tier' => $tier];
            if ($seat->status !== SeatStatus::Booked) {
                $atribut['status'] = $this->statusAwal($seat->seat_code, $tier, $layout);
            }
            $seat->fill($atribut);
            if ($seat->isDirty()) {
                $seat->save();
            }
        }

        return $layout;
    }

    /**
     * Alias lama (venue-config sebelum tab Denah & Booking).
     */
    public function sinkronkanTier(Kegiatan $kegiatan, ?array $layout): array
    {
        return $this->sinkronkan($kegiatan, $layout);
    }

    /**
     * Dampak menerapkan denah ini, untuk dialog konfirmasi admin sebelum menyimpan.
     * Jumlah email dihitung dengan aturan yang sama dengan SendSeatReselectionNotificationJob.
     *
     * @return array<string, mixed>
     */
    public function pratinjau(Kegiatan $kegiatan, ?array $layout, ?int $kapasitasBaru = null): array
    {
        $layout = $this->normalisasi($layout);
        $kapasitas = $kapasitasBaru ?? $this->kapasitas($layout);
        $terisi = $this->jumlahKursiTerisi($kegiatan);
        $sudahAdaDenah = $kegiatan->seats()->exists();
        $dibuatUlang = $this->strukturBerubah($kegiatan, $layout);
        $reset = $sudahAdaDenah && $dibuatUlang && $terisi > 0;

        $terdampak = $reset ? $this->registrasiBerkursi($kegiatan) : collect();
        $penerima = $reset
            ? SendSeatReselectionNotificationJob::penerima(
                KegiatanRegistration::with(['participantUser', 'bookerUser'])
                    ->where('kegiatan_id', $kegiatan->id)
                    ->active()
                    ->where(fn ($q) => $q->whereNotNull('seat_id')->orWhere('needs_seat_reselection', true))
                    ->get()
            )
            : [];

        return [
            'denah_dibuat_ulang' => $dibuatUlang,
            'akan_reset' => $reset,
            'kapasitas' => $kapasitas,
            'kursi_terisi' => $terisi,
            'cukup' => $kapasitas >= $terisi,
            'pesan_tolak' => $kapasitas >= $terisi ? null : $this->pesanKapasitasKurang($kapasitas, $terisi),
            'peserta_terdampak' => $terdampak->count(),
            'email_dikirim' => count($penerima),
            'email_pemesan' => count(array_filter($penerima, fn ($p) => $p['pemesan'])),
            'email_delegasi' => count(array_filter($penerima, fn ($p) => ! $p['pemesan'])),
            'kursi_bentrok' => $dibuatUlang ? [] : $this->kursiBentrok($kegiatan->seats()->get(), $layout),
        ];
    }

    /**
     * Registrasi aktif yang sedang memegang kursi (akan direset bila denah dibuat ulang).
     *
     * @return Collection<int, KegiatanRegistration>
     */
    protected function registrasiBerkursi(Kegiatan $kegiatan): Collection
    {
        return KegiatanRegistration::where('kegiatan_id', $kegiatan->id)
            ->active()
            ->whereNotNull('seat_id')
            ->get();
    }

    /**
     * Kode kursi booked yang oleh denah baru akan dijadikan panitia atau diblokir.
     *
     * @return list<string>
     */
    protected function kursiBentrok(Collection $kursi, array $layout): array
    {
        return $kursi
            ->filter(fn (KegiatanSeat $seat) => $seat->status === SeatStatus::Booked
                && $this->statusAwal($seat->seat_code, $this->tierBaris(strtoupper($seat->row_label), $layout), $layout) !== SeatStatus::Available)
            ->pluck('seat_code')
            ->sort()
            ->values()
            ->all();
    }

    protected function buatKursi(Kegiatan $kegiatan, array $layout): void
    {
        $kursi = [];
        $now = now();

        foreach ($layout['rows'] as $baris) {
            $tier = $this->tierBaris($baris, $layout);

            for ($kolom = 1; $kolom <= $layout['cols_per_row']; $kolom++) {
                $kode = self::kodeKursi($baris, $kolom);
                $kursi[] = [
                    'kegiatan_id' => $kegiatan->id,
                    'seat_code' => $kode,
                    'row_label' => $baris,
                    'seat_number' => $kolom,
                    'col_number' => $kolom,
                    'tier' => $tier->value,
                    'status' => $this->statusAwal($kode, $tier, $layout)->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($kursi, 500) as $bagian) {
            KegiatanSeat::insert($bagian);
        }
    }

    protected function tierBaris(string $baris, array $layout): SeatTier
    {
        return match (true) {
            in_array($baris, $layout['vip_rows'], true) => SeatTier::Vip,
            in_array($baris, $layout['panitia_rows'], true) => SeatTier::Panitia,
            default => SeatTier::Reguler,
        };
    }

    protected function statusAwal(string $kode, SeatTier $tier, array $layout): SeatStatus
    {
        return match (true) {
            in_array(strtoupper($kode), $layout['blocked_seats'] ?? [], true) => SeatStatus::Blocked,
            $tier === SeatTier::Panitia => SeatStatus::ReservedPanitia,
            default => SeatStatus::Available,
        };
    }
}
