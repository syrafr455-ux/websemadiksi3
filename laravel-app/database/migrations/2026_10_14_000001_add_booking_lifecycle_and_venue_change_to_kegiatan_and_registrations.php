<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambahkan kolom siklus booking & venue revision pada tabel kegiatan
        if (Schema::hasTable('kegiatan')) {
            Schema::table('kegiatan', function (Blueprint $table) {
                if (! Schema::hasColumn('kegiatan', 'booking_opens_at')) {
                    $table->dateTime('booking_opens_at')->nullable()->after('is_seat_booking_active');
                }

                if (! Schema::hasColumn('kegiatan', 'booking_closes_at')) {
                    $table->dateTime('booking_closes_at')->nullable()->after('booking_opens_at');
                }

                if (! Schema::hasColumn('kegiatan', 'venue_change_count')) {
                    $table->integer('venue_change_count')->default(0)->after('booking_closes_at');
                }
            });
        }

        // 2. Tambahkan kolom status reset kursi & riwayat kursi pada tabel kegiatan_registrations
        if (Schema::hasTable('kegiatan_registrations')) {
            Schema::table('kegiatan_registrations', function (Blueprint $table) {
                if (! Schema::hasColumn('kegiatan_registrations', 'needs_seat_reselection')) {
                    $table->boolean('needs_seat_reselection')->default(false)->after('seat_id');
                }

                if (! Schema::hasColumn('kegiatan_registrations', 'seat_history')) {
                    $isPgSql = DB::getDriverName() === 'pgsql';
                    if ($isPgSql) {
                        $table->jsonb('seat_history')->nullable()->after('needs_seat_reselection');
                    } else {
                        $table->json('seat_history')->nullable()->after('needs_seat_reselection');
                    }
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('kegiatan')) {
            Schema::table('kegiatan', function (Blueprint $table) {
                $cols = array_filter([
                    'booking_opens_at',
                    'booking_closes_at',
                    'venue_change_count',
                ], fn ($col) => Schema::hasColumn('kegiatan', $col));

                if (! empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }

        if (Schema::hasTable('kegiatan_registrations')) {
            Schema::table('kegiatan_registrations', function (Blueprint $table) {
                $cols = array_filter([
                    'needs_seat_reselection',
                    'seat_history',
                ], fn ($col) => Schema::hasColumn('kegiatan_registrations', $col));

                if (! empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
