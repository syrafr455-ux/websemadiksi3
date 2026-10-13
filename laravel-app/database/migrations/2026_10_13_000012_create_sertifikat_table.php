<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sertifikat', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nomor_sertifikat')->unique();
            $table->foreignId('kegiatan_id')->constrained('kegiatan')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('presensi_id')->nullable()->constrained('kegiatan_presensi')->nullOnDelete();
            $table->enum('peran_sertifikat', [
                'peserta',
                'juara_1',
                'juara_2',
                'juara_3',
                'narasumber',
                'panitia',
            ])->default('peserta');
            $table->string('file_pdf_path');
            $table->string('document_hash', 64);
            $table->boolean('is_valid')->default(true);
            $table->timestamp('issued_at')->useCurrent();
            $table->timestamps();

            $table->index(['kegiatan_id', 'user_id']);
            $table->index('document_hash');
            $table->index('nomor_sertifikat');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sertifikat');
    }
};
