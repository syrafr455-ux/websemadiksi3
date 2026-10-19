<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Relasi pengguna ke master fakultas/prodi (dipakai mahasiswa KIP-K dan verifikator).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('fakultas_id')->nullable()->constrained('fakultas')->nullOnDelete();
            $table->foreignId('program_studi_id')->nullable()->constrained('program_studi')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('program_studi_id');
            $table->dropConstrainedForeignId('fakultas_id');
        });
    }
};
