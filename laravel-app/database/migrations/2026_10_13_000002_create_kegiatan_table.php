<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kegiatan', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nama_kegiatan');
            $table->text('deskripsi')->nullable();
            $table->enum('kategori', [
                'webinar',
                'workshop',
                'seminar',
                'pelatihan',
                'pengabdian_masyarakat',
                'lomba',
                'lainnya',
            ]);
            $table->enum('metode_pelaksanaan', ['online', 'offline', 'hybrid']);
            $table->enum('sasaran_peserta', ['kipk_only', 'internal_unusa', 'umum'])->default('umum');

            // Jadwal (dibutuhkan agar perubahan tanggal bisa dideteksi & dibroadcast)
            $table->dateTime('tanggal_mulai');
            $table->dateTime('tanggal_selesai')->nullable();

            // Lokasi: nama ruangan UNUSA (master) atau lokasi kustom; detail = link maps / URL Zoom
            $table->enum('lokasi_type', ['unusa_campus', 'external', 'online']);
            $table->string('lokasi_nama');
            $table->text('lokasi_detail')->nullable();

            $table->jsonb('narasumber')->nullable(); // [{nama, gelar, instansi, foto}]
            $table->string('file_panduan_pdf');

            $table->integer('kuota');
            $table->integer('kuota_terisi')->default(0);
            $table->integer('poin_xp')->default(0);
            $table->decimal('biaya', 12, 2)->default(0); // 0 = gratis

            $table->boolean('is_seat_booking_active')->default(false);
            $table->jsonb('seat_layout_config')->nullable();

            $table->boolean('is_submission_active')->default(false);
            $table->text('submission_instructions')->nullable();

            $table->enum('status', ['draft', 'published', 'ongoing', 'completed', 'cancelled'])->default('draft');

            // Jejak migrasi dari tabel lama `activities` (idempoten untuk command migrasi).
            $table->unsignedBigInteger('legacy_activity_id')->nullable()->unique();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'tanggal_mulai']);
            $table->index('kategori');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kegiatan');
    }
};
