<?php

namespace Database\Seeders;

use App\Models\Fakultas;
use App\Models\ProgramStudi;
use Illuminate\Database\Seeder;

/**
 * Master data fakultas & program studi UNUSA.
 *
 * Data referensi (bukan data demo): idempoten (updateOrCreate per kode) dan BOLEH berjalan di
 * production. Dijalankan otomatis oleh migrasi pembuat tabelnya dan bisa diulang lewat
 * `php artisan semadiksi:referensi-akademik`. Terpisah dari DatabaseSeeder yang menolak production.
 */
class ReferensiAkademikSeeder extends Seeder
{
    /**
     * Kode fakultas => nama fakultas + daftar program studi [kode prodi, jenjang, nama prodi].
     *
     * @var array<string, array{nama: string, program_studi: list<array{0: string, 1: string, 2: string}>}>
     */
    public const DATA = [
        'FK' => [
            'nama' => 'Fakultas Kedokteran',
            'program_studi' => [
                ['FK-S1-PDOK', 'S1', 'S1 Pendidikan Dokter'],
                ['FK-PRO-DOK', 'Profesi', 'Profesi Dokter'],
                ['FK-SP1-OBGIN', 'Sp-1', 'Spesialis-1 Obstetri dan Ginekologi'],
                ['FK-SP1-PULMO', 'Sp-1', 'Spesialis-1 Pulmonologi dan Kedokteran Respirasi'],
            ],
        ],
        'FKK' => [
            'nama' => 'Fakultas Keperawatan dan Kebidanan',
            'program_studi' => [
                ['FKK-D3-KEP', 'D3', 'D3 Keperawatan'],
                ['FKK-S1-KEP', 'S1', 'S1 Keperawatan'],
                ['FKK-S2-KEPT', 'S2', 'S2 Keperawatan Terapan'],
                ['FKK-PRO-NERS', 'Profesi', 'Profesi Ners'],
                ['FKK-D3-KEB', 'D3', 'D3 Kebidanan'],
                ['FKK-S1-KEB', 'S1', 'S1 Kebidanan'],
                ['FKK-PRO-BIDAN', 'Profesi', 'Profesi Bidan'],
            ],
        ],
        'FKES' => [
            'nama' => 'Fakultas Kesehatan',
            'program_studi' => [
                ['FKES-D4-TLM', 'D4', 'D4 Analis Kesehatan (Teknologi Laboratorium Medis)'],
                ['FKES-D4-K3', 'D4', 'D4 Keselamatan dan Kesehatan Kerja (K3)'],
                ['FKES-S1-GIZI', 'S1', 'S1 Gizi'],
                ['FKES-S1-KESMAS', 'S1', 'S1 Kesehatan Masyarakat'],
                ['FKES-S2-KESMAS', 'S2', 'S2 Kesehatan Masyarakat'],
            ],
        ],
        'FKIP' => [
            'nama' => 'Fakultas Keguruan dan Ilmu Pendidikan (FKIP)',
            'program_studi' => [
                ['FKIP-S1-PGSD', 'S1', 'S1 Pendidikan Guru SD (PGSD)'],
                ['FKIP-S1-PGPAUD', 'S1', 'S1 Pendidikan Guru PAUD (PG-PAUD)'],
                ['FKIP-S1-PBI', 'S1', 'S1 Pendidikan Bahasa Inggris'],
                ['FKIP-S2-PDAS', 'S2', 'S2 Pendidikan Dasar'],
                ['FKIP-PRO-PPG', 'Profesi', 'Pendidikan Profesi Guru (PPG)'],
            ],
        ],
        'FEBTD' => [
            'nama' => 'Fakultas Ekonomi Bisnis dan Teknologi Digital (FEBTD)',
            'program_studi' => [
                ['FEBTD-S1-AKT', 'S1', 'S1 Akuntansi'],
                ['FEBTD-S1-MNJ', 'S1', 'S1 Manajemen'],
                ['FEBTD-S2-MNJ', 'S2', 'S2 Manajemen'],
                ['FEBTD-S1-BD', 'S1', 'S1 Bisnis Digital'],
                ['FEBTD-S1-SI', 'S1', 'S1 Sistem Informasi'],
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::DATA as $kodeFakultas => $fakultasData) {
            $fakultas = Fakultas::updateOrCreate(['kode' => $kodeFakultas], ['nama' => $fakultasData['nama']]);

            foreach ($fakultasData['program_studi'] as [$kode, $jenjang, $nama]) {
                ProgramStudi::updateOrCreate(
                    ['kode' => $kode],
                    ['nama' => $nama, 'jenjang' => $jenjang, 'fakultas_id' => $fakultas->id]
                );
            }
        }
    }
}
