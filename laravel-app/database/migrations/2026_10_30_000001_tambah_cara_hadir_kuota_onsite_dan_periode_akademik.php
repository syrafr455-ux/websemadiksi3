<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hanya menambah kolom dan tabel; data lama tidak berubah. Pendaftaran lama otomatis bernilai 'onsite'
     * (bawaan kolom), kegiatan lama tetap tanpa pembagian kuota (kuota_onsite NULL).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('kegiatan_registrations', 'cara_hadir')) {
            Schema::table('kegiatan_registrations', function (Blueprint $table) {
                $table->enum('cara_hadir', ['onsite', 'daring'])->default('onsite');
                $table->index(['kegiatan_id', 'cara_hadir', 'status_pembayaran'], 'registrasi_kegiatan_cara_hadir_status_index');
            });
        }

        if (! Schema::hasColumn('kegiatan', 'kuota_onsite')) {
            Schema::table('kegiatan', function (Blueprint $table) {
                $table->integer('kuota_onsite')->nullable();
            });
        }

        if (! Schema::hasTable('periode_akademik')) {
            Schema::create('periode_akademik', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('nama', 100);
                $table->date('tanggal_mulai');
                $table->date('tanggal_selesai');
                $table->timestamps();

                $table->index(['tanggal_mulai', 'tanggal_selesai']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('periode_akademik');

        if (Schema::hasColumn('kegiatan', 'kuota_onsite')) {
            Schema::table('kegiatan', function (Blueprint $table) {
                $table->dropColumn('kuota_onsite');
            });
        }

        if (Schema::hasColumn('kegiatan_registrations', 'cara_hadir')) {
            Schema::table('kegiatan_registrations', function (Blueprint $table) {
                $table->dropIndex('registrasi_kegiatan_cara_hadir_status_index');
                $table->dropColumn('cara_hadir');
            });
        }
    }
};
