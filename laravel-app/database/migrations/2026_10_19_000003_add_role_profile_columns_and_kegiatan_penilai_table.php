<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom profil per role dan penugasan juri per lomba.
     *
     * - user_umum: asal_kampus, program_studi_teks (teks bebas); NIM & nomor HP memakai kolom nim & phone.
     * - verifikator_berkas: verifikator_level (fakultas | prodi) + fakultas_id / program_studi_id.
     * - penilai_karya: tabel pivot kegiatan_penilai (lomba yang dijuri).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('asal_kampus')->nullable();
            $table->string('program_studi_teks')->nullable();
            $table->string('verifikator_level', 20)->nullable();
        });

        Schema::create('kegiatan_penilai', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kegiatan_id')->constrained('kegiatan')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['kegiatan_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kegiatan_penilai');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['asal_kampus', 'program_studi_teks', 'verifikator_level']);
        });
    }
};
