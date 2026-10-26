<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('kegiatan', function (Blueprint $table) {
            $table->text('catatan_penolakan')->nullable()->after('status');
            $table->string('platform_daring', 50)->nullable()->after('lokasi_detail');
            $table->text('tautan_rapat')->nullable()->after('platform_daring');
            $table->string('meeting_id', 100)->nullable()->after('tautan_rapat');
            $table->text('passcode_rapat')->nullable()->after('meeting_id');
            $table->string('join_button_mode', 20)->default('otomatis')->after('passcode_rapat');
            $table->integer('menit_buka_gabung')->default(15)->after('join_button_mode');
            $table->dateTime('batas_tutup_gabung')->nullable()->after('menit_buka_gabung');
            $table->boolean('is_gabung_manual_open')->nullable()->default(null)->after('batas_tutup_gabung');
            $table->boolean('izin_tautan_presensi_akhir')->default(false)->after('is_gabung_manual_open');
            $table->boolean('wajib_tangkapan_layar')->default(false)->after('izin_tautan_presensi_akhir');
        });

        Schema::create('kegiatan_gabung', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kegiatan_id')->constrained('kegiatan')->cascadeOnDelete();
            $table->foreignId('registration_id')->unique()->constrained('kegiatan_registrations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('pertama_pada');
            $table->dateTime('terakhir_pada');
            $table->integer('jumlah')->default(1);
            $table->timestamps();

            $table->index(['kegiatan_id', 'user_id']);
        });

        Schema::table('kegiatan_presensi', function (Blueprint $table) {
            $table->string('tangkapan_layar_path', 500)->nullable()->after('signature_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kegiatan_presensi', function (Blueprint $table) {
            $table->dropColumn('tangkapan_layar_path');
        });

        Schema::dropIfExists('kegiatan_gabung');

        Schema::table('kegiatan', function (Blueprint $table) {
            $table->dropColumn([
                'catatan_penolakan',
                'platform_daring',
                'tautan_rapat',
                'meeting_id',
                'passcode_rapat',
                'join_button_mode',
                'menit_buka_gabung',
                'batas_tutup_gabung',
                'is_gabung_manual_open',
                'izin_tautan_presensi_akhir',
                'wajib_tangkapan_layar',
            ]);
        });
    }
};
