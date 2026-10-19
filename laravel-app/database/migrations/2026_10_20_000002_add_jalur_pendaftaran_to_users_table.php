<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jalur pendaftaran mandiri: "unusa" (aktivasi via email kampus) | "umum" (wajib verifikasi email).
     * NULL = akun lama / dibuat admin / seeder.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('jalur_pendaftaran', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('jalur_pendaftaran');
        });
    }
};
