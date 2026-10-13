<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kegiatan_presensi', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('kegiatan_id')->constrained('kegiatan')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('registration_id')->nullable()->constrained('kegiatan_registrations')->nullOnDelete();
            $table->string('nama_lengkap');
            $table->string('nim');
            $table->string('prodi');
            $table->string('email');
            $table->string('nomor_telepon');
            $table->string('signature_path');
            $table->timestamp('check_in_at')->useCurrent();
            $table->timestamps();

            $table->index(['kegiatan_id', 'check_in_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kegiatan_presensi');
    }
};
