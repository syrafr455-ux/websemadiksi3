<?php

namespace App\Console\Commands;

use App\Enums\KegiatanStatus;
use App\Enums\PeranSertifikat;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Certificate;
use App\Models\Kegiatan;
use App\Models\KegiatanPresensi;
use App\Models\KegiatanRegistration;
use App\Models\KegiatanSeat;
use App\Models\Sertifikat;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class MigrasiAlurLamaCommand extends Command
{
    protected $signature = 'semadiksi:migrasi-alur-lama {--eksekusi : Jalankan penyalinan data secara nyata (bawaan adalah dry-run/simulasi)}';

    protected $description = 'Migrasi data alur lama (activities, attendances, certificates) ke alur baru (kegiatan, kegiatan_presensi, sertifikat) secara idempoten';

    public function handle(): int
    {
        $isEksekusi = (bool) $this->option('eksekusi');

        $this->newLine();
        $this->info('================================================================');
        $this->info(' SEMADIKSI - Penyatuan & Migrasi Alur Lama ke Alur Baru');
        $this->info(' Mode Operasi: '.($isEksekusi ? 'EKSEKUSI NYATA' : 'DRY-RUN (Simulasi - Hanya Melapor)'));
        $this->info('================================================================');

        // 1. Migrasi Activities -> Kegiatan
        $this->line("\n[1/3] Meninjau tabel `activities` -> `kegiatan`...");
        $activities = Activity::all();
        $actTotal = $activities->count();
        $actReady = 0;
        $actSkipped = 0;

        foreach ($activities as $act) {
            $existing = Kegiatan::where('legacy_activity_id', $act->id)->first();
            if ($existing) {
                $actSkipped++;

                continue;
            }

            $actReady++;

            if ($isEksekusi) {
                $isFree = strtolower((string) $act->price) === 'gratis' || empty($act->price);
                $biaya = $isFree ? 0 : (float) preg_replace('/[^\d.]/', '', (string) $act->price);

                $kategori = match (strtolower((string) $act->category)) {
                    'seminar' => 'seminar',
                    'webinar' => 'webinar',
                    'workshop' => 'workshop',
                    'pelatihan' => 'pelatihan',
                    'lomba' => 'lomba',
                    'sosial', 'pengabdian', 'pengabdian masyarakat' => 'pengabdian_masyarakat',
                    default => 'lainnya',
                };

                $metode = match (strtolower((string) $act->type)) {
                    'online' => 'online',
                    'hybrid' => 'hybrid',
                    default => 'offline',
                };

                $startDate = $act->event_date ?? ($act->created_at ?? Carbon::now());

                $kegiatan = Kegiatan::create([
                    'legacy_activity_id' => $act->id,
                    'nama_kegiatan' => $act->title,
                    'deskripsi' => $act->description,
                    'kategori' => $kategori,
                    'metode_pelaksanaan' => $metode,
                    'sasaran_peserta' => 'umum',
                    'tanggal_mulai' => $startDate,
                    'tanggal_selesai' => (clone $startDate)->addHours(4),
                    'lokasi_type' => $metode === 'online' ? 'online' : 'unusa_campus',
                    'lokasi_nama' => $act->location ?: 'Kampus B UNUSA',
                    'lokasi_detail' => null,
                    'narasumber' => $act->speaker ? [['nama' => $act->speaker, 'gelar' => '', 'instansi' => 'UNUSA', 'foto' => null]] : null,
                    'file_panduan_pdf' => 'kegiatan/panduan/panduan.pdf',
                    'kuota' => $act->quota ?: 100,
                    'poin_xp' => $act->xp_points ?: 50,
                    'biaya' => $biaya,
                    'is_seat_booking_active' => (bool) $act->enable_seat_booking,
                    'seat_layout_config' => $act->seat_layout_config ?: [
                        'rows' => ['A', 'B', 'C', 'D'],
                        'cols_per_row' => 10,
                        'aisle_after_col' => 5,
                        'vip_rows' => ['A'],
                    ],
                    'is_submission_active' => (bool) $act->require_file_upload,
                    'submission_instructions' => $act->file_upload_instruction,
                    'status' => $act->is_closed ? KegiatanStatus::Completed : KegiatanStatus::Published,
                ]);

                // Buat kursi default bila ada denah kursi
                if ($kegiatan->is_seat_booking_active && $kegiatan->seats()->count() === 0) {
                    $rows = $kegiatan->seat_layout_config['rows'] ?? ['A', 'B', 'C'];
                    $cols = (int) ($kegiatan->seat_layout_config['cols_per_row'] ?? 10);
                    $vipRows = $kegiatan->seat_layout_config['vip_rows'] ?? ['A'];

                    foreach ($rows as $row) {
                        for ($c = 1; $c <= $cols; $c++) {
                            KegiatanSeat::create([
                                'kegiatan_id' => $kegiatan->id,
                                'seat_code' => "{$row}-{$c}",
                                'row_label' => $row,
                                'seat_number' => $c,
                                'tier' => in_array($row, $vipRows, true) ? 'vip' : 'reguler',
                                'status' => 'available',
                            ]);
                        }
                    }
                }
            }
        }

        // 2. Migrasi Attendances -> KegiatanPresensi
        $this->line('[2/3] Meninjau tabel `attendances` -> `kegiatan_presensi`...');
        $attendances = Attendance::all();
        $attTotal = $attendances->count();
        $attReady = 0;
        $attSkipped = 0;
        $attAmbiguous = 0;

        foreach ($attendances as $att) {
            if (empty($att->activity_id)) {
                $attAmbiguous++;

                continue;
            }

            // Cari kegiatan tujuan
            $targetKegiatan = Kegiatan::where('legacy_activity_id', $att->activity_id)->first()
                ?? Kegiatan::find($att->activity_id);

            if (! $targetKegiatan) {
                $attAmbiguous++;

                continue;
            }

            // Cek idempoten: apakah presensi sudah tercatat di kegiatan_presensi
            $alreadyExists = KegiatanPresensi::where('kegiatan_id', $targetKegiatan->id)
                ->where(function ($q) use ($att) {
                    if (! empty($att->student_nim)) {
                        $q->where('nim', $att->student_nim);
                    }
                    if (! empty($att->student_email)) {
                        $q->orWhere('email', $att->student_email);
                    }
                })
                ->exists();

            if ($alreadyExists) {
                $attSkipped++;

                continue;
            }

            $attReady++;

            if ($isEksekusi) {
                $user = null;
                if (! empty($att->user_id)) {
                    $user = User::find($att->user_id);
                }
                if (! $user && ! empty($att->student_nim)) {
                    $user = User::where('nim', $att->student_nim)->first();
                }
                if (! $user && ! empty($att->student_email)) {
                    $user = User::where('email', $att->student_email)->first();
                }

                $regId = null;
                if ($user) {
                    $reg = KegiatanRegistration::where('kegiatan_id', $targetKegiatan->id)
                        ->where(function ($q) use ($user) {
                            $q->where('participant_user_id', $user->id)
                                ->orWhere('booker_user_id', $user->id);
                        })
                        ->first();
                    $regId = $reg?->id;
                }

                KegiatanPresensi::create([
                    'kegiatan_id' => $targetKegiatan->id,
                    'user_id' => $user?->id ?? $att->user_id,
                    'registration_id' => $regId,
                    'nama_lengkap' => $att->student_name,
                    'nim' => $att->student_nim,
                    'prodi' => $user?->programStudi?->nama ?? ($att->university ?: 'Umum'),
                    'email' => $att->student_email ?: ($user?->email ?? 'peserta@semadiksi.id'),
                    'nomor_telepon' => $att->student_phone ?: '-',
                    'signature_path' => $att->proof_image_url ?: 'attendances/legacy_proof.png',
                    'check_in_at' => $att->submitted_at ?? now(),
                ]);
            }
        }

        // 3. Migrasi Certificates -> Sertifikat
        $this->line('[3/3] Meninjau tabel `certificates` -> `sertifikat`...');
        $certificates = Certificate::all();
        $certTotal = $certificates->count();
        $certReady = 0;
        $certSkipped = 0;
        $certAmbiguous = 0;

        foreach ($certificates as $cert) {
            if (empty($cert->activity_id)) {
                $certAmbiguous++;

                continue;
            }

            $targetKegiatan = Kegiatan::where('legacy_activity_id', $cert->activity_id)->first()
                ?? Kegiatan::find($cert->activity_id);

            if (! $targetKegiatan) {
                $certAmbiguous++;

                continue;
            }

            // Cek idempoten: nomor sertifikat unik
            $alreadyExists = Sertifikat::where('nomor_sertifikat', $cert->code)->exists();
            if ($alreadyExists) {
                $certSkipped++;

                continue;
            }

            $certReady++;

            if ($isEksekusi) {
                $user = null;
                if (! empty($cert->user_id)) {
                    $user = User::find($cert->user_id);
                }
                if (! $user && ! empty($cert->user_name)) {
                    $user = User::where('name', $cert->user_name)->first();
                }

                $presensi = KegiatanPresensi::where('kegiatan_id', $targetKegiatan->id)
                    ->where(function ($q) use ($user, $cert) {
                        if ($user) {
                            $q->where('user_id', $user->id);
                        }
                        $q->orWhere('nama_lengkap', $cert->user_name);
                    })
                    ->first();

                $hash = hash('sha256', $cert->code.$cert->user_name);
                $filePath = $cert->file_url ?: 'certificates/legacy/'.$cert->code.'.pdf';

                Sertifikat::create([
                    'nomor_sertifikat' => $cert->code,
                    'kegiatan_id' => $targetKegiatan->id,
                    'user_id' => $user?->id,
                    'nama_penerima' => $cert->user_name,
                    'email_penerima' => $user?->email,
                    'presensi_id' => $presensi?->id,
                    'peran_sertifikat' => PeranSertifikat::Peserta,
                    'file_pdf_path' => $filePath,
                    'document_hash' => $hash,
                    'is_valid' => strtolower((string) $cert->status) !== 'dicabut',
                    'issued_at' => $cert->issued_date ? Carbon::parse($cert->issued_date) : now(),
                ]);
            }
        }

        // Tampilkan tabel rekapitulasi hasil analisis
        $this->newLine();
        $this->table(
            ['Entitas Sumber -> Sasaran', 'Total Lama', 'Siap Disalin', 'Sudah Ada (Lewati)', 'Ambigu (Arsip Baca-Saja)'],
            [
                ['activities -> kegiatan', $actTotal, $actReady, $actSkipped, 0],
                ['attendances -> kegiatan_presensi', $attTotal, $attReady, $attSkipped, $attAmbiguous],
                ['certificates -> sertifikat', $certTotal, $certReady, $certSkipped, $certAmbiguous],
            ]
        );

        if (! $isEksekusi) {
            $this->warn('STATUS: DRY-RUN SELESAI. Tidak ada baris data yang diubah di basis data.');
            $this->line('Catatan penting: Data ambigu (activity_id kosong / tidak ditemukan) tetap dipertahankan sebagai arsip baca-saja di tabel lama.');
        } else {
            $this->info('STATUS: EKSEKUSI SELESAI. Seluruh data yang valid berhasil disalin secara idempoten.');
        }

        $this->newLine();

        return Command::SUCCESS;
    }
}
