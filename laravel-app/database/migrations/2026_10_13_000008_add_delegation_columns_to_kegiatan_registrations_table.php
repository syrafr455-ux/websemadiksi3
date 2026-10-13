<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kegiatan_registrations', function (Blueprint $table) {
            $table->foreignId('participant_user_id')->nullable()->change();
            if (! Schema::hasColumn('kegiatan_registrations', 'participant_name')) {
                $table->string('participant_name')->nullable()->after('nomor_tiket');
            }
            if (! Schema::hasColumn('kegiatan_registrations', 'participant_identity')) {
                $table->string('participant_identity')->nullable()->after('participant_name');
            }
            if (! Schema::hasColumn('kegiatan_registrations', 'participant_phone')) {
                $table->string('participant_phone')->nullable()->after('participant_identity');
            }
            if (! Schema::hasColumn('kegiatan_registrations', 'amount_paid')) {
                $table->decimal('amount_paid', 10, 2)->default(0)->after('participant_phone');
            }
            if (! Schema::hasColumn('kegiatan_registrations', 'delegation_data')) {
                $table->json('delegation_data')->nullable()->after('amount_paid');
            }
        });
    }

    public function down(): void
    {
        Schema::table('kegiatan_registrations', function (Blueprint $table) {
            $cols = array_filter([
                'participant_name',
                'participant_identity',
                'participant_phone',
                'amount_paid',
                'delegation_data',
            ], fn ($col) => Schema::hasColumn('kegiatan_registrations', $col));

            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
