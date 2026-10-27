<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hanya menambah tabel/kolom dan melonggarkan file_pdf_path. Sertifikat yang sudah terbit
     * (nomor seri, hash, berkas, QR, tautan verifikasi) tidak diubah.
     */
    public function up(): void
    {
        Schema::create('sertifikat_templat', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('kegiatan_id')->constrained('kegiatan')->cascadeOnDelete();
            $table->string('jenis', 32)->nullable(); // null = bawaan untuk semua jenis; terisi = penimpa jenis itu
            $table->string('orientasi', 10)->default('landscape'); // A4 landscape | portrait
            $table->string('latar_path', 500)->nullable(); // disk privat
            $table->json('layout');
            $table->json('teks');
            $table->unsignedInteger('versi')->default(1);
            $table->foreignId('diubah_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['kegiatan_id', 'jenis']);
        });

        // NULL dianggap berbeda pada unique biasa; templat bawaan (jenis NULL) hanya boleh satu per kegiatan.
        DB::statement('CREATE UNIQUE INDEX sertifikat_templat_bawaan_unique ON sertifikat_templat (kegiatan_id) WHERE jenis IS NULL');

        Schema::table('sertifikat', function (Blueprint $table) {
            // Berkas dibuat lazy saat pertama diunduh, jadi path boleh kosong untuk sertifikat baru.
            $table->string('file_pdf_path')->nullable()->change();
            $table->string('document_hash', 64)->nullable()->change(); // diisi saat berkas dibuat
            $table->string('file_disk', 20)->default('public')->after('file_pdf_path'); // sertifikat lama: public
            $table->string('file_status', 20)->nullable()->after('file_disk'); // null/siap | gagal
            $table->text('file_alasan_gagal')->nullable()->after('file_status');
            $table->timestamp('file_dibuat_pada')->nullable()->after('file_alasan_gagal');
            $table->unsignedInteger('file_templat_versi')->nullable()->after('file_dibuat_pada');
        });
    }

    public function down(): void
    {
        Schema::table('sertifikat', function (Blueprint $table) {
            $table->dropColumn(['file_disk', 'file_status', 'file_alasan_gagal', 'file_dibuat_pada', 'file_templat_versi']);
        });
        DB::statement('DROP INDEX IF EXISTS sertifikat_templat_bawaan_unique');
        Schema::dropIfExists('sertifikat_templat');
    }
};
