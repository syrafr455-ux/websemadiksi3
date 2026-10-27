<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Nilai yang diizinkan per kolom enum, dibekukan di sini (bukan dibaca dari enum PHP) agar migrasi lama tidak
     * berubah perilakunya saat enum bertambah; nilai baru ditambahkan lewat migrasi baru.
     *
     * @var array<string, array<string, list<string>>>
     */
    private const BATASAN = [
        'kegiatan' => [
            'kategori' => ['webinar', 'workshop', 'seminar', 'pelatihan', 'pengabdian_masyarakat', 'lomba', 'lainnya'],
            'metode_pelaksanaan' => ['online', 'offline', 'hybrid'],
            'sasaran_peserta' => ['kipk_only', 'internal_unusa', 'umum'],
            'lokasi_type' => ['unusa_campus', 'external', 'online'],
            'status' => ['draft', 'diajukan', 'published', 'ongoing', 'completed', 'cancelled'],
        ],
        'kegiatan_seats' => [
            'tier' => ['reguler', 'vip', 'panitia'],
            'status' => ['available', 'reserved_panitia', 'booked', 'blocked'],
        ],
        'payments' => [
            'payment_status' => ['pending', 'settlement', 'expired', 'failed'],
        ],
        'kegiatan_registrations' => [
            'status_kehadiran' => ['registered', 'checked_in', 'absent'],
            'status_pembayaran' => ['free', 'pending', 'paid', 'expired', 'failed'],
        ],
        'sertifikat' => [
            'peran_sertifikat' => ['peserta', 'juara_1', 'juara_2', 'juara_3', 'narasumber', 'panitia'],
        ],
        'users' => [
            'role' => ['super_admin', 'mahasiswa_kipk', 'user_umum', 'verifikator_berkas', 'penilai_karya'],
        ],
    ];

    /**
     * Hanya PostgreSQL (SQLite tidak punya batasan CHECK enum yang bisa diubah). Batasan yang sudah sama dengan
     * daftar di atas tidak disentuh, dan tidak ada data yang diubah. Database lama yang dibuat dari versi
     * migrasi awal yang berbeda (misalnya tanpa 'diajukan') diselaraskan; bila ada baris dengan nilai di luar
     * daftar, PostgreSQL menolak batasan baru dan migrasi dibatalkan utuh.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::BATASAN as $tabel => $kolomKolom) {
            foreach ($kolomKolom as $kolom => $nilai) {
                $nama = "{$tabel}_{$kolom}_check";

                if ($this->nilaiSaatIni($tabel, $nama) === $this->diurutkan($nilai)) {
                    continue;
                }

                $daftar = implode(', ', array_map(static fn (string $v): string => "'{$v}'", $nilai));
                DB::statement("ALTER TABLE \"{$tabel}\" DROP CONSTRAINT IF EXISTS \"{$nama}\"");
                DB::statement("ALTER TABLE \"{$tabel}\" ADD CONSTRAINT \"{$nama}\" CHECK (\"{$kolom}\" IN ({$daftar}))");
            }
        }
    }

    /**
     * Tidak dikembalikan: daftar sebelumnya tidak diketahui dan versi lama menolak nilai yang sah.
     */
    public function down(): void {}

    /** @return list<string>|null nilai terurut pada batasan, atau null bila batasan tidak ada */
    private function nilaiSaatIni(string $tabel, string $nama): ?array
    {
        $baris = DB::selectOne(
            'select pg_get_constraintdef(c.oid) as definisi
             from pg_constraint c
             join pg_class t on t.oid = c.conrelid
             join pg_namespace n on n.oid = t.relnamespace
             where c.contype = \'c\' and n.nspname = current_schema() and t.relname = ? and c.conname = ?',
            [$tabel, $nama],
        );

        if ($baris === null) {
            return null;
        }

        preg_match_all("/'((?:[^']|'')*)'::/", $baris->definisi, $cocok);

        return $this->diurutkan(array_map(static fn (string $v): string => str_replace("''", "'", $v), $cocok[1]));
    }

    /** @param list<string> $nilai */
    private function diurutkan(array $nilai): array
    {
        sort($nilai);

        return $nilai;
    }
};
