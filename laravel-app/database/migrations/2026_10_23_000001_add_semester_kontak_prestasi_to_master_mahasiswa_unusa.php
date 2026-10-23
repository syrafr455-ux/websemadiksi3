<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_mahasiswa_unusa', function (Blueprint $table) {
            $table->unsignedTinyInteger('semester')->nullable()->after('angkatan');
            // Terenkripsi lewat cast model (encrypted), jadi bertipe text.
            $table->text('no_telepon')->nullable()->after('email_kampus');
            $table->text('prestasi')->nullable()->after('no_telepon');
        });
    }

    public function down(): void
    {
        Schema::table('master_mahasiswa_unusa', function (Blueprint $table) {
            $table->dropColumn(['semester', 'no_telepon', 'prestasi']);
        });
    }
};
