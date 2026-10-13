<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kegiatan_registrations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('kegiatan_id')->constrained('kegiatan')->cascadeOnDelete();
            // Booker = pemilik transaksi/checkout, Participant = pemilik tiket (multi-delegation).
            $table->foreignId('booker_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('participant_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('seat_id')->nullable()->constrained('kegiatan_seats')->nullOnDelete();
            $table->string('nomor_tiket')->unique(); // TKT-{KEGIATAN_ID}-{RANDOM}-{SEAT}
            $table->string('participant_name')->nullable();
            $table->string('participant_identity')->nullable();
            $table->string('participant_phone')->nullable();
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->json('delegation_data')->nullable();
            $table->enum('status_kehadiran', ['registered', 'checked_in', 'absent'])->default('registered');
            $table->enum('status_pembayaran', ['free', 'pending', 'paid', 'expired', 'failed'])->default('pending');
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamps();

            $table->index(['kegiatan_id', 'status_pembayaran']);
            $table->index('participant_user_id');
            $table->index('booker_user_id');
        });

        // Pagar terakhir anti double-booking di level database: satu kursi / satu peserta
        // hanya boleh punya SATU registrasi aktif (free / pending / paid).
        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement(
                "CREATE UNIQUE INDEX kegiatan_registrations_active_seat_unique
                 ON kegiatan_registrations (seat_id)
                 WHERE seat_id IS NOT NULL AND status_pembayaran IN ('free', 'pending', 'paid')"
            );
            DB::statement(
                "CREATE UNIQUE INDEX kegiatan_registrations_active_participant_unique
                 ON kegiatan_registrations (kegiatan_id, participant_user_id)
                 WHERE status_pembayaran IN ('free', 'pending', 'paid')"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kegiatan_registrations');
    }
};
