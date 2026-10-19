<?php

namespace App\Console\Commands;

use App\Models\Fakultas;
use App\Models\ProgramStudi;
use Database\Seeders\ReferensiAkademikSeeder;
use Illuminate\Console\Command;

class SyncReferensiAkademikCommand extends Command
{
    protected $signature = 'semadiksi:referensi-akademik';

    protected $description = 'Isi/perbarui master data fakultas & program studi UNUSA (idempoten, aman di production)';

    public function handle(): int
    {
        (new ReferensiAkademikSeeder)->run();

        $this->info(sprintf('Referensi akademik tersinkron: %d fakultas, %d program studi.', Fakultas::count(), ProgramStudi::count()));

        return self::SUCCESS;
    }
}
