<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Token aktivasi akun mahasiswa: hanya hash SHA-256 yang disimpan, sekali pakai, terikat ke NIM.
        Schema::create('aktivasi_mahasiswa', function (Blueprint $table) {
            $table->id();
            $table->string('nim', 30)->index();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('kedaluwarsa_pada');
            $table->timestamp('dipakai_pada')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aktivasi_mahasiswa');
    }
};
