<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kunci penerima tanpa akun berbentuk "ext:" + SHA-256 heksadesimal (68 karakter), sedangkan kolom dibuat
     * varchar(64). PostgreSQL menolak nilai itu ("value too long for type character varying(64)"), SQLite tidak.
     * Pelebaran varchar hanya mengubah batas panjang; tidak ada data yang berubah atau hilang, dan aman diulang.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('sertifikat', 'penerima_key') || Schema::getConnection()->getDriverName() === 'sqlite') {
            return; // SQLite tidak menegakkan panjang varchar.
        }

        Schema::table('sertifikat', function (Blueprint $table) {
            $table->string('penerima_key', 100)->nullable()->change();
        });
    }

    /**
     * Tidak dikembalikan ke 64: baris "ext:" yang sudah tersimpan tidak muat lagi.
     */
    public function down(): void {}
};
