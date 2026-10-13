<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kegiatan') && ! Schema::hasColumn('kegiatan', 'kuota_terisi')) {
            Schema::table('kegiatan', function (Blueprint $table) {
                $table->integer('kuota_terisi')->default(0)->after('kuota');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('kegiatan') && Schema::hasColumn('kegiatan', 'kuota_terisi')) {
            Schema::table('kegiatan', function (Blueprint $table) {
                $table->dropColumn('kuota_terisi');
            });
        }
    }
};
