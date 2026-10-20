<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keunikan NIM hanya untuk akun UNUSA.
     *
     * - is_unusa: penanda akun mahasiswa UNUSA (boolean, bukan pencocokan teks asal_kampus).
     *   Diisi true untuk mahasiswa_kipk dan akun jalur pendaftaran "unusa".
     * - Indeks unik global users.nim diganti indeks unik parsial: nim unik di antara akun is_unusa.
     *   Peserta umum dari kampus lain boleh memakai NIM yang sama (cukup email yang unik).
     *   PostgreSQL dan SQLite (tes) sama-sama mendukung indeks parsial.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_unusa')->default(false);
        });

        DB::table('users')
            ->where('role', 'mahasiswa_kipk')
            ->orWhere('jalur_pendaftaran', 'unusa')
            ->update(['is_unusa' => true]);

        $duplikat = DB::table('users')
            ->select('nim')
            ->where('is_unusa', true)
            ->whereNotNull('nim')
            ->groupBy('nim')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('nim');
        if ($duplikat->isNotEmpty()) {
            throw new RuntimeException('Ada NIM ganda di antara akun UNUSA ('.$duplikat->count().' NIM). Rapikan datanya dulu sebelum migrasi ini.');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_nim_unique');
        });

        DB::statement('CREATE UNIQUE INDEX users_nim_unusa_unique ON users (nim) WHERE is_unusa = '.$this->benar().' AND nim IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_nim_unusa_unique');

        Schema::table('users', function (Blueprint $table) {
            $table->unique('nim');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_unusa');
        });
    }

    /**
     * Literal boolean true di klausa WHERE indeks: TRUE di PostgreSQL, 1 di SQLite.
     */
    private function benar(): string
    {
        return DB::getDriverName() === 'pgsql' ? 'TRUE' : '1';
    }
};
