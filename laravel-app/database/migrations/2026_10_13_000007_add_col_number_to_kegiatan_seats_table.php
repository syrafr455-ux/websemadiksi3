<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kegiatan_seats') && ! Schema::hasColumn('kegiatan_seats', 'col_number')) {
            Schema::table('kegiatan_seats', function (Blueprint $table) {
                $table->integer('col_number')->nullable()->after('row_label');
            });

            DB::statement('UPDATE kegiatan_seats SET col_number = seat_number WHERE col_number IS NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('kegiatan_seats') && Schema::hasColumn('kegiatan_seats', 'col_number')) {
            Schema::table('kegiatan_seats', function (Blueprint $table) {
                $table->dropColumn('col_number');
            });
        }
    }
};
