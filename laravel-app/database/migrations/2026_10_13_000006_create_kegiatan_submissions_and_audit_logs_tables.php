<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kegiatan_submissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('kegiatan_id')->constrained('kegiatan')->cascadeOnDelete();
            $table->foreignId('registration_id')->unique()->constrained('kegiatan_registrations')->cascadeOnDelete();
            $table->string('nama_kelompok_peserta');
            $table->string('file_karya_url', 2048);
            $table->decimal('nilai', 5, 2)->nullable();
            $table->text('catatan_juri')->nullable();
            $table->foreignId('judged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('judged_at')->nullable();
            $table->timestamps();

            $table->index('kegiatan_id');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('description')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index('action');
            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('kegiatan_submissions');
    }
};
