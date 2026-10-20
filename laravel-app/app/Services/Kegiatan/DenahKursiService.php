<?php

namespace App\Services\Kegiatan;

use App\Enums\RegistrationPaymentStatus;
use App\Enums\SeatStatus;
use App\Enums\SeatTier;
use App\Jobs\SendSeatReselectionNotificationJob;
use App\Models\Kegiatan;
use App\Models\KegiatanRegistration;
use App\Models\KegiatanSeat;
use Illuminate\Validation\ValidationException;

/**
 * Satu-satunya jalur untuk membuat dan mengganti denah kursi kegiatan.
 * Dipakai form kegiatan (admin.kegiatan.store/update) dan endpoint venue-config,
 * sehingga pengaman kapasitas, reset kursi, riwayat kursi, dan email pilih ulang selalu sama.
 * Pemanggil wajib membungkusnya dalam transaksi database.
 */
class DenahKursiService
{
    public const DEFAULT_LAYOUT = [
        'rows' => ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'],
        'cols_per_row' => 10,
        'vip_rows' => ['A', 'B'],
        'panitia_rows' => [],
    ];

    /**
     * Bentuk baku denah agar konfigurasi lama dan baru bisa dibandingkan.
     * Key yang tidak dikirim memakai default; key yang dikirim kosong (null) berarti daftar kosong.
     *
     * @return array{rows: list<string>, cols_per_row: int, vip_rows: list<string>, panitia_rows: list<string>}
     */
    public function normalisasi(?array $layout): array
    {
        $layout ??= [];
        $baris = fn (string $key): array => array_values(array_map(
            fn ($row) => strtoupper(trim((string) $row)),
            array_key_exists($key, $layout) ? (array) ($layout[$key] ?? []) : self::DEFAULT_LAYOUT[$key]
        ));

        return [
            'rows' => $baris('rows'),
            'cols_per_row' => (int) ($layout['cols_per_row'] ?? self::DEFAULT_LAYOUT['cols_per_row']),
            'vip_rows' => $baris('vip_rows'),
            'panitia_rows' => $baris('panitia_rows'),
        ];
    }

    /**
     * Jumlah kursi yang bisa dipesan peserta (baris panitia tidak dihitung).
     */
    public function kapasitas(array $layout): int
    {
        $layout = $this->normalisasi($layout);
        $barisPeserta = array_diff($layout['rows'], $layout['panitia_rows']);

        return count(array_unique($barisPeserta)) * $layout['cols_per_row'];
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

    /**
     * Tolak kapasitas baru yang lebih kecil dari jumlah kursi terisi (pesan sama untuk semua jalur).
     *
     * @throws ValidationException
     */
    public function pastikanKapasitasCukup(Kegiatan $kegiatan, int $kapasitasBaru): int
    {
        $terisi = $this->jumlahKursiTerisi($kegiatan);

        if ($kapasitasBaru < $terisi) {
            throw ValidationException::withMessages([
                'capacity' => "Pemindahan ruangan dibatalkan: Kapasitas baru ({$kapasitasBaru} kursi) tidak mencukupi untuk menampung {$terisi} peserta yang sudah memiliki kursi.",
            ]);
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

            $terdampak = KegiatanRegistration::with('seat')
                ->where('kegiatan_id', $kegiatan->id)
                ->active()
                ->whereNotNull('seat_id')
                ->get();

            foreach ($terdampak as $reg) {
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
     * Perbarui tier/status kursi yang belum dipesan sesuai baris VIP/panitia, tanpa mereset kursi.
     */
    public function sinkronkanTier(Kegiatan $kegiatan, ?array $layout): array
    {
        $layout = $this->normalisasi($layout);

        $kegiatan->seats()
            ->where('status', '!=', SeatStatus::Booked->value)
            ->get()
            ->each(function (KegiatanSeat $seat) use ($layout) {
                $tier = $this->tierBaris(strtoupper($seat->row_label), $layout);
                $seat->update(['tier' => $tier, 'status' => $this->statusAwal($tier)]);
            });

        return $layout;
    }

    protected function buatKursi(Kegiatan $kegiatan, array $layout): void
    {
        $kursi = [];
        $now = now();

        foreach (array_unique($layout['rows']) as $baris) {
            $tier = $this->tierBaris($baris, $layout);

            for ($kolom = 1; $kolom <= $layout['cols_per_row']; $kolom++) {
                $kursi[] = [
                    'kegiatan_id' => $kegiatan->id,
                    'seat_code' => $baris.str_pad((string) $kolom, 2, '0', STR_PAD_LEFT),
                    'row_label' => $baris,
                    'seat_number' => $kolom,
                    'col_number' => $kolom,
                    'tier' => $tier->value,
                    'status' => $this->statusAwal($tier)->value,
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

    protected function statusAwal(SeatTier $tier): SeatStatus
    {
        return $tier === SeatTier::Panitia ? SeatStatus::ReservedPanitia : SeatStatus::Available;
    }
}
