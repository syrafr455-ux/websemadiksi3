<?php

namespace App\Services\Kegiatan;

use App\Enums\JenisSertifikat;
use App\Enums\RegistrationPaymentStatus;
use App\Enums\StatusSertifikat;
use App\Models\Kegiatan;
use App\Models\KegiatanGabung;
use App\Models\KegiatanPresensi;
use App\Models\KegiatanRegistration;
use App\Models\KegiatanSubmission;
use App\Models\Sertifikat;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Mesin Kelayakan Sertifikat SEMADIKSI (Satu service, satu sumber kebenaran).
 *
 * Mengatur aturan ketat kelayakan penerbitan sertifikat untuk seluruh jenis:
 * - Peserta (offline, hybrid, daring, lomba)
 * - Juri (kegiatan_penilai)
 * - Pembicara (narasumber / manual)
 * - Juara (usulan peringkat nilai karya)
 */
class KelayakanSertifikatService
{
    /**
     * Periksa apakah penerbitan sertifikat untuk jenis tertentu sudah diizinkan secara waktu.
     *
     * - Peserta: waktu selesai lewat DAN sesi presensi akhir tertutup.
     * - Juri, Pembicara, Juara: setelah kegiatan dimulai.
     *
     * @return array{bisa: bool, alasan: string|null}
     */
    public function cekWaktuPenerbitan(Kegiatan $kegiatan, JenisSertifikat $jenis): array
    {
        $sekarang = now();

        if ($jenis === JenisSertifikat::Peserta) {
            if ($kegiatan->tanggal_selesai && $sekarang->isBefore($kegiatan->tanggal_selesai)) {
                return [
                    'bisa' => false,
                    'alasan' => 'Penerbitan sertifikat peserta hanya dapat dilakukan setelah kegiatan selesai.',
                ];
            }

            $isSesiTerbuka = (bool) ($kegiatan->presensi_akhir_dibuka ?? false)
                || (bool) ($kegiatan->is_presensi_akhir_aktif ?? false);

            if ($isSesiTerbuka) {
                return [
                    'bisa' => false,
                    'alasan' => 'Sesi presensi akhir masih terbuka. Tutup sesi presensi akhir terlebih dahulu.',
                ];
            }

            return ['bisa' => true, 'alasan' => null];
        }

        // Jenis Juri, Pembicara, Juara: boleh setelah kegiatan dimulai
        if ($kegiatan->tanggal_mulai && $sekarang->isBefore($kegiatan->tanggal_mulai)) {
            return [
                'bisa' => false,
                'alasan' => 'Penerbitan sertifikat '.$jenis->label().' hanya dapat dilakukan setelah kegiatan dimulai.',
            ];
        }

        return ['bisa' => true, 'alasan' => null];
    }

    /**
     * Evaluasi kelayakan sertifikat untuk satu registrasi peserta.
     *
     * @return array{
     *     layak: bool,
     *     alasan: string,
     *     detail: array{
     *         status_pembayaran: string,
     *         check_in_awal: bool,
     *         sumber_check_in: string|null,
     *         presensi_akhir: bool,
     *         sumber_presensi: string|null,
     *         karya_terkumpul: bool,
     *     }
     * }
     */
    public function evaluasiPeserta(
        Kegiatan $kegiatan,
        KegiatanRegistration $registration,
        ?KegiatanPresensi $presensi = null,
        ?bool $hasSubmission = null
    ): array {
        // 1. Validasi Status Pembayaran / Registrasi
        $statusReg = strtolower((string) ($registration->status_pembayaran?->value ?? $registration->status_pembayaran ?? $registration->payment_status ?? $registration->status ?? ''));
        $isCancelled = in_array($statusReg, ['cancelled', 'dibatalkan', 'expired', 'failed'], true);
        if ($isCancelled) {
            return [
                'layak' => false,
                'alasan' => 'Registrasi dibatalkan',
                'detail' => $this->bangunDetailPeserta($registration, false, null, false, null, false),
            ];
        }

        $isPaidOrFree = in_array($statusReg, [
            RegistrationPaymentStatus::Free->value,
            RegistrationPaymentStatus::Paid->value,
            'free',
            'paid',
        ], true);

        if (! $isPaidOrFree) {
            return [
                'layak' => false,
                'alasan' => 'Pembayaran belum lunas',
                'detail' => $this->bangunDetailPeserta($registration, false, null, false, null, false),
            ];
        }

        // Cek jika akun peserta diblokir
        if ($registration->participantUser && (bool) $registration->participantUser->is_blocked) {
            return [
                'layak' => false,
                'alasan' => 'Akun peserta diblokir',
                'detail' => $this->bangunDetailPeserta($registration, false, null, false, null, false),
            ];
        }

        $metode = strtolower((string) $kegiatan->metode_pelaksanaan);

        // 2. Evaluasi Check-in Awal (Fase 1)
        $hasTicketCheckIn = ! empty($registration->checked_in_at);
        $hasJoinClick = $registration->relationLoaded('gabung')
            ? ($registration->gabung !== null)
            : KegiatanGabung::where('registration_id', $registration->id)->exists();

        $checkInAwal = false;
        $sumberCheckIn = null;

        if ($metode === 'online' || $metode === 'daring') {
            // Daring: absen awal = ada kegiatan_gabung
            $checkInAwal = $hasJoinClick;
            $sumberCheckIn = $hasJoinClick ? 'gabung' : null;
            if (! $checkInAwal) {
                return [
                    'layak' => false,
                    'alasan' => 'Belum gabung',
                    'detail' => $this->bangunDetailPeserta($registration, false, null, false, null, false),
                ];
            }
        } elseif ($metode === 'hybrid') {
            // Hybrid: absen awal = check-in tiket ATAU gabung
            $checkInAwal = $hasTicketCheckIn || $hasJoinClick;
            $sumberCheckIn = $hasTicketCheckIn ? 'tiket' : ($hasJoinClick ? 'gabung' : null);
            if (! $checkInAwal) {
                return [
                    'layak' => false,
                    'alasan' => 'Belum check-in awal atau gabung',
                    'detail' => $this->bangunDetailPeserta($registration, false, null, false, null, false),
                ];
            }
        } else {
            // Offline: check-in tiket fase 1 tercatat
            $checkInAwal = $hasTicketCheckIn;
            $sumberCheckIn = $hasTicketCheckIn ? 'tiket' : null;
            if (! $checkInAwal) {
                return [
                    'layak' => false,
                    'alasan' => 'Belum check-in awal',
                    'detail' => $this->bangunDetailPeserta($registration, false, null, false, null, false),
                ];
            }
        }

        // 3. Evaluasi Presensi Akhir (Fase 2)
        // Data presensi wajib memiliki registration_id yang valid dan signature_path
        if ($presensi === null) {
            $presensi = KegiatanPresensi::where('kegiatan_id', $kegiatan->id)
                ->where(function ($q) use ($registration) {
                    $q->where('registration_id', $registration->id);
                    if ($registration->participant_user_id) {
                        $q->orWhere('user_id', $registration->participant_user_id);
                    }
                })
                ->first();
        }

        $hasPresensiAkhir = false;
        $sumberPresensi = null;

        if ($presensi && ! empty($presensi->signature_path)) {
            $hasPresensiAkhir = true;
            $sumberPresensi = str_contains($presensi->signature_path, 'sig_manual_') ? 'manual' : 'qr';
        }

        if (! $hasPresensiAkhir) {
            return [
                'layak' => false,
                'alasan' => 'Belum presensi akhir',
                'detail' => $this->bangunDetailPeserta($registration, $checkInAwal, $sumberCheckIn, false, null, false),
            ];
        }

        // 4. Evaluasi Karya Lomba (Bila Kategori Lomba atau Wajib Pengumpulan Karya)
        $isLomba = $kegiatan->isLomba() || (bool) $kegiatan->is_submission_active;

        if ($isLomba) {
            if ($hasSubmission === null) {
                $hasSubmission = KegiatanSubmission::where('registration_id', $registration->id)
                    ->where('kegiatan_id', $kegiatan->id)
                    ->exists();
            }

            if (! $hasSubmission) {
                return [
                    'layak' => false,
                    'alasan' => 'Karya belum dikumpulkan',
                    'detail' => $this->bangunDetailPeserta($registration, $checkInAwal, $sumberCheckIn, true, $sumberPresensi, false),
                ];
            }
        } else {
            $hasSubmission = false;
        }

        return [
            'layak' => true,
            'alasan' => 'Layak',
            'detail' => $this->bangunDetailPeserta($registration, $checkInAwal, $sumberCheckIn, true, $sumberPresensi, $isLomba ? $hasSubmission : true),
        ];
    }

    private function bangunDetailPeserta(
        KegiatanRegistration $reg,
        bool $checkInAwal,
        ?string $sumberCheckIn,
        bool $presensiAkhir,
        ?string $sumberPresensi,
        bool $karyaTerkumpul
    ): array {
        return [
            'status_pembayaran' => (string) ($reg->status?->value ?? $reg->status),
            'check_in_awal' => $checkInAwal,
            'sumber_check_in' => $sumberCheckIn,
            'presensi_akhir' => $presensiAkhir,
            'sumber_presensi' => $sumberPresensi,
            'karya_terkumpul' => $karyaTerkumpul,
        ];
    }

    /**
     * Evaluasi seluruh peserta pada kegiatan.
     *
     * @return Collection<int, array>
     */
    public function evaluasiSemuaPeserta(Kegiatan $kegiatan): Collection
    {
        $registrations = KegiatanRegistration::with(['participantUser', 'bookerUser'])
            ->where('kegiatan_id', $kegiatan->id)
            ->get();

        // Ambil ID penerima yang sudah memiliki sertifikat peserta aktif
        $sertifikatPeserta = Sertifikat::where('kegiatan_id', $kegiatan->id)
            ->where('jenis', JenisSertifikat::Peserta)
            ->where('status', StatusSertifikat::Aktif)
            ->get()
            ->keyBy('penerima_key');

        // Ambil penerima yang sudah memiliki sertifikat juara aktif
        $sertifikatJuara = Sertifikat::where('kegiatan_id', $kegiatan->id)
            ->where('jenis', JenisSertifikat::Juara)
            ->where('status', StatusSertifikat::Aktif)
            ->get()
            ->keyBy('penerima_key');

        return $registrations->map(function (KegiatanRegistration $reg) use ($kegiatan, $sertifikatPeserta, $sertifikatJuara) {
            $user = $reg->participantUser;
            $nama = $reg->participant_name ?: ($user?->name ?? 'Peserta');
            $email = $user?->email ?? ($reg->delegation_data['email'] ?? null);
            $nim = $user?->nim ?? ($reg->delegation_data['nim'] ?? null);
            $institusi = $user?->institution ?? 'Universitas Nahdlatul Ulama Surabaya';

            $penerimaKey = Sertifikat::hitungPenerimaKey($user?->id, $nama, $institusi);
            $evaluasi = $this->evaluasiPeserta($kegiatan, $reg);

            $sudahTerbit = $sertifikatPeserta->has($penerimaKey);
            $sertifikat = $sudahTerbit ? $sertifikatPeserta->get($penerimaKey) : null;
            $sudahJuara = $sertifikatJuara->has($penerimaKey);

            // Bawaan tercentang: layak, belum terbit, dan BUKAN peraih juara
            $defaultChecked = $evaluasi['layak'] && ! $sudahTerbit && ! $sudahJuara;

            return [
                'registration_id' => $reg->id,
                'user_id' => $user?->id,
                'penerima_key' => $penerimaKey,
                'nomor_tiket' => $reg->ticket_code ?? $reg->nomor_tiket,
                'nama' => $nama,
                'nim' => $nim,
                'email' => $email,
                'asal_institusi' => $institusi,
                'layak' => $evaluasi['layak'],
                'alasan' => $evaluasi['alasan'],
                'detail' => $evaluasi['detail'],
                'sudah_terbit' => $sudahTerbit,
                'nomor_sertifikat' => $sertifikat?->nomor_sertifikat,
                'sertifikat_id' => $sertifikat?->id,
                'sudah_juara' => $sudahJuara,
                'default_checked' => $defaultChecked,
            ];
        });
    }

    /**
     * Evaluasi juri pada kegiatan (kegiatan_penilai).
     *
     * @return Collection<int, array>
     */
    public function evaluasiJuri(Kegiatan $kegiatan): Collection
    {
        $penilai = $kegiatan->penilai()->get();
        $totalKarya = KegiatanSubmission::where('kegiatan_id', $kegiatan->id)->count();

        $sertifikatJuri = Sertifikat::where('kegiatan_id', $kegiatan->id)
            ->where('jenis', JenisSertifikat::Juri)
            ->where('status', StatusSertifikat::Aktif)
            ->get()
            ->keyBy('penerima_key');

        return $penilai->map(function (User $juri) use ($kegiatan, $totalKarya, $sertifikatJuri) {
            $karyaDinilai = KegiatanSubmission::where('kegiatan_id', $kegiatan->id)
                ->where('judged_by', $juri->id)
                ->whereNotNull('nilai')
                ->count();

            $telahMenilai = $karyaDinilai >= 1;
            $penerimaKey = Sertifikat::hitungPenerimaKey($juri->id, $juri->name, $juri->institution);
            $sudahTerbit = $sertifikatJuri->has($penerimaKey);
            $sertifikat = $sudahTerbit ? $sertifikatJuri->get($penerimaKey) : null;

            $alasan = $telahMenilai ? 'Layak' : 'Belum menilai karya';

            return [
                'user_id' => $juri->id,
                'penerima_key' => $penerimaKey,
                'nama' => $juri->name,
                'email' => $juri->email,
                'nim' => $juri->nim,
                'asal_institusi' => $juri->institution ?: 'Universitas Nahdlatul Ulama Surabaya',
                'karya_dinilai' => $karyaDinilai,
                'total_karya' => $totalKarya,
                'layak' => true, // Ditugaskan sebagai juri
                'telah_menilai' => $telahMenilai,
                'alasan' => $alasan,
                'sudah_terbit' => $sudahTerbit,
                'nomor_sertifikat' => $sertifikat?->nomor_sertifikat,
                'sertifikat_id' => $sertifikat?->id,
                'default_checked' => $telahMenilai && ! $sudahTerbit,
            ];
        });
    }

    /**
     * Usulan peringkat juara berdasarkan nilai karya akhir lomba.
     *
     * @return array{
     *     usulan: Collection<int, array>,
     *     ada_karya_belum_dinilai: bool,
     *     jumlah_belum_dinilai: int
     * }
     */
    public function evaluasiJuara(Kegiatan $kegiatan): array
    {
        $submissions = KegiatanSubmission::with(['registration.participantUser'])
            ->where('kegiatan_id', $kegiatan->id)
            ->get();

        $belumDinilai = $submissions->whereNull('nilai')->count();

        // Hanya karya dengan nilai numerik valid yang diurutkan
        $dinilai = $submissions->whereNotNull('nilai')
            ->sortByDesc('nilai')
            ->values();

        $predikatPreset = [
            1 => 'Juara 1',
            2 => 'Juara 2',
            3 => 'Juara 3',
            4 => 'Harapan 1',
            5 => 'Harapan 2',
            6 => 'Harapan 3',
        ];

        $sertifikatJuara = Sertifikat::where('kegiatan_id', $kegiatan->id)
            ->where('jenis', JenisSertifikat::Juara)
            ->where('status', StatusSertifikat::Aktif)
            ->get()
            ->keyBy('penerima_key');

        $usulan = $dinilai->map(function (KegiatanSubmission $sub, int $index) use ($predikatPreset, $sertifikatJuara) {
            $peringkat = $index + 1;
            $reg = $sub->registration;
            $user = $reg?->participantUser;
            $nama = $reg?->participant_name ?: ($user?->name ?? ($sub->nama_kelompok_peserta ?: 'Peserta'));
            $email = $user?->email;
            $nim = $user?->nim;
            $institusi = $user?->institution ?: 'Universitas Nahdlatul Ulama Surabaya';

            $penerimaKey = Sertifikat::hitungPenerimaKey($user?->id, $nama, $institusi);
            $sudahTerbit = $sertifikatJuara->has($penerimaKey);
            $sertifikat = $sudahTerbit ? $sertifikatJuara->get($penerimaKey) : null;

            return [
                'submission_id' => $sub->id,
                'registration_id' => $reg?->id,
                'user_id' => $user?->id,
                'penerima_key' => $penerimaKey,
                'peringkat' => $peringkat,
                'predikat_usulan' => $predikatPreset[$peringkat] ?? "Peringkat {$peringkat}",
                'judul_karya' => $sub->title ?: $sub->nama_kelompok_peserta,
                'nilai' => (float) $sub->nilai,
                'nama' => $nama,
                'nim' => $nim,
                'email' => $email,
                'asal_institusi' => $institusi,
                'sudah_terbit' => $sudahTerbit,
                'nomor_sertifikat' => $sertifikat?->nomor_sertifikat,
                'sertifikat_id' => $sertifikat?->id,
                'default_checked' => $peringkat <= 3 && ! $sudahTerbit,
            ];
        });

        return [
            'usulan' => $usulan,
            'ada_karya_belum_dinilai' => $belumDinilai > 0,
            'jumlah_belum_dinilai' => $belumDinilai,
        ];
    }
}
