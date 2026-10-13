<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Memperluas kolom role menjadi matriks 5 role dan menambah kolom tracking login.
     *
     * Pemetaan data lama: admin -> super_admin, student -> mahasiswa_kipk.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->index('role');
        });

        DB::table('users')->where('role', 'admin')->update(['role' => UserRole::SuperAdmin->value]);
        DB::table('users')->where('role', 'student')->update(['role' => UserRole::MahasiswaKipk->value]);
        DB::table('users')->whereNotIn('role', UserRole::values())->update(['role' => UserRole::UserUmum->value]);

        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default(UserRole::UserUmum->value)->change();
        });

        if (in_array(DB::getDriverName(), ['pgsql', 'mysql', 'mariadb'], true)) {
            $roles = implode(', ', array_map(static fn (string $role): string => "'{$role}'", UserRole::values()));
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ({$roles}))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
        } elseif (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE users DROP CHECK users_role_check');
        }

        DB::table('users')->where('role', UserRole::SuperAdmin->value)->update(['role' => 'admin']);
        DB::table('users')->whereIn('role', [
            UserRole::MahasiswaKipk->value,
            UserRole::UserUmum->value,
            UserRole::VerifikatorBerkas->value,
            UserRole::PenilaiKarya->value,
        ])->update(['role' => 'student']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn(['last_login_at', 'last_login_ip']);
            $table->string('role')->default('student')->change();
        });
    }
};
