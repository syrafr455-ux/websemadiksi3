<?php

namespace App\Console\Commands;

use App\Enums\KegiatanStatus;
use App\Models\Activity;
use App\Models\Kegiatan;
use App\Models\KegiatanSeat;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class MigrateLegacyActivitiesCommand extends Command
{
    protected $signature = 'semadiksi:migrate-legacy-activities';

    protected $description = 'Migrasikan data tabel lama activities ke tabel kegiatan terstandarisasi';

    public function handle(): int
    {
        $this->info('Memulai migrasi activities lama ke kegiatan...');
        $activities = Activity::all();

        $count = 0;
        foreach ($activities as $act) {
            $isFree = strtolower((string) $act->price) === 'gratis' || empty($act->price);
            $biaya = $isFree ? 0 : (float) preg_replace('/[^\d.]/', '', $act->price);

            $kategori = match (strtolower((string) $act->category)) {
                'seminar' => 'seminar',
                'webinar' => 'webinar',
                'workshop' => 'workshop',
                'pelatihan' => 'pelatihan',
                'lomba' => 'lomba',
                'sosial', 'pengabdian' => 'pengabdian_masyarakat',
                default => 'lainnya',
            };

            $metode = match (strtolower((string) $act->type)) {
                'online' => 'online',
                'hybrid' => 'hybrid',
                default => 'offline',
            };

            $startDate = $act->event_date ?? Carbon::now()->addDays(7);

            $kegiatan = Kegiatan::updateOrCreate(
                ['legacy_activity_id' => $act->id],
                [
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
                    'file_panduan_pdf' => 'panduan/default_panduan.pdf',
                    'kuota' => $act->quota ?: 100,
                    'poin_xp' => $act->xp_points ?: 50,
                    'biaya' => $biaya,
                    'is_seat_booking_active' => (bool) $act->enable_seat_booking,
                    'seat_layout_config' => $act->seat_layout_config ?: [
                        'rows' => ['A', 'B', 'C', 'D', 'E'],
                        'cols_per_row' => 10,
                        'aisle_after_col' => 5,
                        'vip_rows' => ['A'],
                    ],
                    'is_submission_active' => (bool) $act->require_file_upload,
                    'submission_instructions' => $act->file_upload_instruction,
                    'status' => $act->is_closed ? KegiatanStatus::Completed : KegiatanStatus::Published,
                ]
            );

            // Generate demo seats if seat booking enabled
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

            $count++;
        }

        $this->info("Berhasil memigrasikan {$count} kegiatan ke sistem baru.");

        return Command::SUCCESS;
    }
}
