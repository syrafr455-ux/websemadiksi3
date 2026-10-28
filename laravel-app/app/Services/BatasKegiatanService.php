<?php

namespace App\Services;

use App\Enums\KegiatanStatus;
use App\Enums\RegistrationPaymentStatus;
use App\Models\Kegiatan;
use App\Models\KegiatanPresensi;
use App\Models\KegiatanRegistration;
use App\Models\PeriodeAkademik;
use App\Models\User;
use App\Services\Kegiatan\KelayakanSertifikatService;
use App\Support\Wib;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Batas kegiatan yang DIHADIRI per periode akademik (config unusa.batas_kegiatan_per_periode).
 *
 * Yang dihitung: kegiatan BERBEDA berstatus terbit (terbit, berlangsung, selesai) yang dihadiri pengguna menurut
 * definisi "hadir" mesin kelayakan sertifikat (KelayakanSertifikatService::kehadiranTercatat) dan tanggal mulainya
 * jatuh dalam periode. Hanya mengatur pendaftaran; presensi tidak pernah ditolak.
 */
class BatasKegiatanService
{
    public function __construct(private readonly KelayakanSertifikatService $kelayakan) {}

    public function batas(): int
    {
        return (int) config('unusa.batas_kegiatan_per_periode', 3);
    }

    /** Periode yang memuat tanggal (hari WIB); null bila tidak ada, sehingga batas tidak berlaku. */
    public function periodeBerjalan(?CarbonInterface $tanggal = null): ?PeriodeAkademik
    {
        $hari = Wib::dari($tanggal ?? now())->toDateString();

        return PeriodeAkademik::query()
            ->where('tanggal_mulai', '<=', $hari)
            ->where('tanggal_selesai', '>=', $hari)
            ->orderBy('tanggal_mulai')
            ->first();
    }

    /**
     * Kegiatan yang dihadiri pengguna beserta periodenya, terbaru di atas. Query tetap (registrasi, presensi, periode),
     * tanpa N+1. Tanpa $periode: semua periode; kegiatan di luar periode mana pun diberi periode null.
     *
     * @return Collection<int, array{kegiatan: Kegiatan, cara_hadir: string, periode: ?PeriodeAkademik}>
     */
    public function kegiatanDihadiri(User $user, ?PeriodeAkademik $periode = null): Collection
    {
        $registrasi = KegiatanRegistration::query()
            ->with(['kegiatan', 'gabung'])
            ->where('participant_user_id', $user->id)
            ->whereIn('status_pembayaran', [RegistrationPaymentStatus::Free->value, RegistrationPaymentStatus::Paid->value])
            ->whereHas('kegiatan', function ($q) use ($periode) {
                $q->whereNull('deleted_at')->whereIn('status', [KegiatanStatus::Published->value, KegiatanStatus::Ongoing->value, KegiatanStatus::Completed->value]);
                if ($periode) {
                    $q->where('tanggal_mulai', '>=', Wib::keUtc($periode->tanggal_mulai->toDateString().' 00:00:00'))
                        ->where('tanggal_mulai', '<', Wib::keUtc($periode->tanggal_selesai->copy()->addDay()->toDateString().' 00:00:00'));
                }
            })
            ->get();

        if ($registrasi->isEmpty()) {
            return collect();
        }

        $presensi = KegiatanPresensi::query()
            ->whereIn('kegiatan_id', $registrasi->pluck('kegiatan_id')->unique())
            ->where(fn ($q) => $q->whereIn('registration_id', $registrasi->pluck('id'))->orWhere('user_id', $user->id))
            ->get();

        $semuaPeriode = $periode ? collect([$periode]) : PeriodeAkademik::query()->orderBy('tanggal_mulai')->get();

        return $registrasi
            ->filter(function (KegiatanRegistration $reg) use ($presensi) {
                $milik = $presensi->first(fn (KegiatanPresensi $p) => $p->kegiatan_id === $reg->kegiatan_id
                    && ((int) $p->registration_id === $reg->id || (int) $p->user_id === (int) $reg->participant_user_id));

                return $this->kelayakan->kehadiranTercatat($reg->kegiatan, $reg, $milik)['hadir'];
            })
            ->groupBy('kegiatan_id')
            ->map(fn (Collection $kelompok) => $kelompok->first())
            ->map(fn (KegiatanRegistration $reg) => [
                'kegiatan' => $reg->kegiatan,
                'cara_hadir' => $reg->cara_hadir?->value ?? 'onsite',
                'periode' => $semuaPeriode->first(fn (PeriodeAkademik $p) => $this->dalamPeriode($reg->kegiatan->tanggal_mulai, $p)),
            ])
            ->sortByDesc(fn (array $baris) => $baris['kegiatan']->tanggal_mulai)
            ->values();
    }

    public function hitung(User $user, ?PeriodeAkademik $periode = null): int
    {
        $periode ??= $this->periodeBerjalan();

        return $periode ? $this->kegiatanDihadiri($user, $periode)->count() : 0;
    }

    /** Sisa jatah di periode berjalan; null bila batas tidak berlaku (tidak ada periode berjalan). */
    public function sisaJatah(User $user): ?int
    {
        $periode = $this->periodeBerjalan();

        return $periode ? max(0, $this->batas() - $this->hitung($user, $periode)) : null;
    }

    /** Pesan penolakan pendaftaran bila batas tercapai; null bila boleh mendaftar. */
    public function periksaDaftar(User $user, string $subjek = 'Kamu'): ?string
    {
        $periode = $this->periodeBerjalan();
        if (! $periode || $this->hitung($user, $periode) < $this->batas()) {
            return null;
        }

        return "{$subjek} sudah menghadiri {$this->batas()} kegiatan pada {$periode->nama}. Batas dibuka lagi pada periode berikutnya.";
    }

    private function dalamPeriode(?CarbonInterface $mulai, PeriodeAkademik $periode): bool
    {
        if ($mulai === null) {
            return false;
        }
        $hari = Wib::dari($mulai)->toDateString();

        return $hari >= $periode->tanggal_mulai->toDateString() && $hari <= $periode->tanggal_selesai->toDateString();
    }
}
