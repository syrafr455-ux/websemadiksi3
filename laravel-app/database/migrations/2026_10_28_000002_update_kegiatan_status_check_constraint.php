<?php

use App\Enums\KegiatanStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE kegiatan DROP CONSTRAINT IF EXISTS kegiatan_status_check');
            $statuses = implode(', ', array_map(static fn (string $s): string => "'{$s}'", KegiatanStatus::values()));
            DB::statement("ALTER TABLE kegiatan ADD CONSTRAINT kegiatan_status_check CHECK (status IN ({$statuses}))");
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            $statuses = implode(', ', array_map(static fn (string $s): string => "'{$s}'", KegiatanStatus::values()));
            DB::statement("ALTER TABLE kegiatan MODIFY COLUMN status ENUM({$statuses}) DEFAULT 'draft'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE kegiatan DROP CONSTRAINT IF EXISTS kegiatan_status_check');
            $statuses = "'draft', 'published', 'ongoing', 'completed', 'cancelled'";
            DB::statement("ALTER TABLE kegiatan ADD CONSTRAINT kegiatan_status_check CHECK (status IN ({$statuses}))");
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            $statuses = "'draft', 'published', 'ongoing', 'completed', 'cancelled'";
            DB::statement("ALTER TABLE kegiatan MODIFY COLUMN status ENUM({$statuses}) DEFAULT 'draft'");
        }
    }
};
