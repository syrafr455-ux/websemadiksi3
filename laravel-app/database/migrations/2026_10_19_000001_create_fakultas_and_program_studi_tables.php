<?php

use Database\Seeders\ReferensiAkademikSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fakultas', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 20)->unique();
            $table->string('nama');
            $table->timestamps();
        });

        Schema::create('program_studi', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 30)->unique();
            $table->string('nama');
            $table->string('jenjang', 20);
            $table->foreignId('fakultas_id')->constrained('fakultas')->restrictOnDelete();
            $table->timestamps();

            $table->index('fakultas_id');
        });

        // Master data referensi selalu tersedia setelah migrasi (idempoten, aman di production).
        (new ReferensiAkademikSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('program_studi');
        Schema::dropIfExists('fakultas');
    }
};
