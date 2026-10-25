<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kegiatan', function (Blueprint $table) {
            if (! Schema::hasColumn('kegiatan', 'presensi_akhir_dibuka')) {
                $table->boolean('presensi_akhir_dibuka')->default(false)->after('status');
            }
            if (! Schema::hasColumn('kegiatan', 'presensi_akhir_mulai')) {
                $table->timestamp('presensi_akhir_mulai')->nullable()->after('presensi_akhir_dibuka');
            }
            if (! Schema::hasColumn('kegiatan', 'presensi_akhir_selesai')) {
                $table->timestamp('presensi_akhir_selesai')->nullable()->after('presensi_akhir_mulai');
            }
        });
    }

    public function down(): void
    {
        Schema::table('kegiatan', function (Blueprint $table) {
            $table->dropColumn([
                'presensi_akhir_dibuka',
                'presensi_akhir_mulai',
                'presensi_akhir_selesai',
            ]);
        });
    }
};
