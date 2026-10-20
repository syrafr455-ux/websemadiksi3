<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pilihan fasilitas UNUSA di form kegiatan (kampus, gedung, ruangan) disimpan agar form edit
     * terisi kembali dengan benar. Hanya diisi bila lokasi_type = unusa_campus.
     */
    public function up(): void
    {
        Schema::table('kegiatan', function (Blueprint $table) {
            $table->string('lokasi_kampus')->nullable()->after('lokasi_type');
            $table->string('lokasi_gedung')->nullable()->after('lokasi_kampus');
            $table->string('lokasi_ruangan')->nullable()->after('lokasi_gedung');
        });
    }

    public function down(): void
    {
        Schema::table('kegiatan', function (Blueprint $table) {
            $table->dropColumn(['lokasi_kampus', 'lokasi_gedung', 'lokasi_ruangan']);
        });
    }
};
