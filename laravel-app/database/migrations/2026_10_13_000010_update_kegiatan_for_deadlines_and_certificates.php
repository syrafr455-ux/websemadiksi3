<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kegiatan', function (Blueprint $table) {
            if (! Schema::hasColumn('kegiatan', 'registration_deadline')) {
                $table->timestamp('registration_deadline')->nullable()->after('tanggal_selesai');
            }
            if (! Schema::hasColumn('kegiatan', 'submission_deadline')) {
                $table->timestamp('submission_deadline')->nullable()->after('registration_deadline');
            }
            if (! Schema::hasColumn('kegiatan', 'certificate_template_path')) {
                $table->string('certificate_template_path')->nullable()->after('file_panduan_pdf');
            }
            if (! Schema::hasColumn('kegiatan', 'certificate_schema_config')) {
                $table->jsonb('certificate_schema_config')->nullable()->after('certificate_template_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('kegiatan', function (Blueprint $table) {
            $table->dropColumn([
                'registration_deadline',
                'submission_deadline',
                'certificate_template_path',
                'certificate_schema_config',
            ]);
        });
    }
};
