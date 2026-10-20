<?php

namespace App\Services\Kegiatan;

use App\Enums\SeatStatus;
use App\Models\Kegiatan;
use App\Models\KegiatanRegistration;
use App\Models\KegiatanSeat;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Data peta kursi untuk antarmuka. Versi admin menyertakan pemegang kursi; versi peserta hanya
 * menandai kursi milik pengguna dan delegasinya, tanpa membocorkan nama pemegang kursi lain.
 */
class PetaKursiService
{
    public const TERSEDIA = 'tersedia';

    public const TERISI = 'terisi';

    public const PANITIA = 'panitia';

    public const DIBLOKIR = 'diblokir';

    public const MILIK = 'milik';

    /**
     * Kursi berurutan sesuai urutan baris di denah, lalu nomor kolom.
     *
     * @return Collection<int, KegiatanSeat>
     */
    public function kursiTerurut(Kegiatan $kegiatan): Collection
    {
        $urutanBaris = array_flip(array_map('strtoupper', $kegiatan->seat_layout_config['rows'] ?? []));

        return $kegiatan->seats()->get()
            ->sortBy([
                fn (KegiatanSeat $a, KegiatanSeat $b) => ($urutanBaris[strtoupper($a->row_label)] ?? PHP_INT_MAX) <=> ($urutanBaris[strtoupper($b->row_label)] ?? PHP_INT_MAX)
                    ?: strcmp($a->row_label, $b->row_label),
                fn (KegiatanSeat $a, KegiatanSeat $b) => $a->col_number <=> $b->col_number,
            ])
            ->values();
    }

    /**
     * Peta untuk admin: status asli kursi + pemegang (nama peserta & nomor tiket) untuk kursi terisi.
     *
     * @return list<array<string, mixed>>
     */
    public function untukAdmin(Kegiatan $kegiatan): array
    {
        $pemegang = KegiatanRegistration::with(['participantUser:id,name', 'bookerUser:id,name'])
            ->where('kegiatan_id', $kegiatan->id)
            ->active()
            ->whereNotNull('seat_id')
            ->get()
            ->keyBy('seat_id');

        return $this->kursiTerurut($kegiatan)->map(function (KegiatanSeat $seat) use ($pemegang) {
            $reg = $pemegang->get($seat->id);

            return [
                ...$this->dasar($seat),
                'status' => $seat->status?->value,
                'pemegang' => $reg?->ticket_holder_name,
                'tiket' => $reg?->nomor_tiket,
            ];
        })->all();
    }

    /**
     * Peta untuk peserta/tamu: status disederhanakan; kursi milik $user (sebagai pemesan atau peserta)
     * ditandai "milik" beserta nama peserta di registrasi itu. Pemegang kursi lain tidak disebutkan.
     *
     * @return list<array<string, mixed>>
     */
    public function untukPeserta(Kegiatan $kegiatan, ?User $user): array
    {
        $milik = $this->kursiMilik($kegiatan, $user);

        return $this->kursiTerurut($kegiatan)->map(fn (KegiatanSeat $seat) => [
            ...$this->dasar($seat),
            'status' => isset($milik[$seat->id]) ? self::MILIK : $this->statusPeserta($seat->status),
            'pemegang' => $milik[$seat->id] ?? null,
        ])->all();
    }

    /**
     * Data ringkas untuk pembaruan berkala peta peserta: hanya id dan status (plus nama untuk kursi milik sendiri).
     *
     * @return list<array{id: int, status: string, pemegang?: string|null}>
     */
    public function statusRingkas(Kegiatan $kegiatan, ?User $user): array
    {
        $milik = $this->kursiMilik($kegiatan, $user);

        return $kegiatan->seats()->get(['id', 'status'])->map(function (KegiatanSeat $seat) use ($milik) {
            if (isset($milik[$seat->id])) {
                return ['id' => $seat->id, 'status' => self::MILIK, 'pemegang' => $milik[$seat->id]];
            }

            return ['id' => $seat->id, 'status' => $this->statusPeserta($seat->status)];
        })->values()->all();
    }

    /**
     * seat_id => nama peserta, untuk registrasi aktif yang dipesan atau diikuti $user.
     *
     * @return array<int, string>
     */
    public function kursiMilik(Kegiatan $kegiatan, ?User $user): array
    {
        if (! $user) {
            return [];
        }

        return KegiatanRegistration::with(['participantUser:id,name', 'bookerUser:id,name'])
            ->where('kegiatan_id', $kegiatan->id)
            ->active()
            ->whereNotNull('seat_id')
            ->where(fn ($q) => $q->where('booker_user_id', $user->id)->orWhere('participant_user_id', $user->id))
            ->get()
            ->mapWithKeys(fn (KegiatanRegistration $reg) => [$reg->seat_id => $reg->ticket_holder_name])
            ->all();
    }

    protected function statusPeserta(?SeatStatus $status): string
    {
        return match ($status) {
            SeatStatus::Available => self::TERSEDIA,
            SeatStatus::ReservedPanitia => self::PANITIA,
            SeatStatus::Blocked => self::DIBLOKIR,
            default => self::TERISI,
        };
    }

    /**
     * @return array{id: int, kode: string, baris: string, kolom: int, tier: string|null}
     */
    protected function dasar(KegiatanSeat $seat): array
    {
        return [
            'id' => $seat->id,
            'kode' => $seat->seat_code,
            'baris' => $seat->row_label,
            'kolom' => (int) $seat->col_number,
            'tier' => $seat->tier?->value,
        ];
    }
}
