<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Master mahasiswa UNUSA (sementara dari impor CSV; nanti dari API database UNUSA).
        Schema::create('master_mahasiswa_unusa', function (Blueprint $table) {
            $table->id();
            $table->string('nim', 30)->unique();
            $table->string('nama_lengkap');
            $table->string('email_kampus')->nullable();
            $table->foreignId('program_studi_id')->nullable()->constrained('program_studi')->nullOnDelete();
            $table->string('angkatan', 10)->nullable();
            $table->string('status_aktif', 20)->default('aktif');
            $table->string('sumber', 100)->default('impor_csv');
            $table->timestamp('diperbarui_pada')->nullable();
            $table->timestamps();
        });

        // Daftar penerima KIP Kuliah dari UNUSA (berbasis nomor pendaftaran SIM KIP Kuliah).
        Schema::create('daftar_penerima_kipk', function (Blueprint $table) {
            $table->id();
            // Nomor pendaftaran SIM KIP = username SIM KIP: disimpan terenkripsi, dicari lewat HMAC.
            $table->text('nomor_pendaftaran_kip');
            $table->string('nomor_kip_hash', 64)->unique();
            $table->string('nim', 30)->nullable()->index();
            $table->string('nama_lengkap');
            $table->string('program_studi_teks')->nullable();
            $table->string('angkatan', 10)->nullable();
            $table->string('status_penetapan', 50)->nullable();
            $table->string('sumber', 150)->nullable();
            $table->timestamp('diimpor_pada')->nullable();
            $table->foreignId('diimpor_oleh')->nullable()->constrained('users')->nullOnDelete();
            // Akun yang sudah terhubung (paling banyak satu baris per akun).
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('cara_terhubung', 30)->nullable();
            $table->timestamp('dicocokkan_pada')->nullable();
            $table->string('status_tinjau', 20)->default('normal');
            $table->string('alasan_tinjau', 50)->nullable();
            $table->foreignId('klaim_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('klaim_pada')->nullable();
            $table->text('catatan_tinjau')->nullable();
            $table->timestamps();

            $table->index('status_tinjau');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daftar_penerima_kipk');
        Schema::dropIfExists('master_mahasiswa_unusa');
    }
};
