<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kegiatan_seats', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('kegiatan_id')->constrained('kegiatan')->cascadeOnDelete();
            $table->string('seat_code'); // contoh: A-1, B-12
            $table->string('row_label');
            $table->integer('seat_number');
            $table->integer('col_number')->nullable();
            $table->enum('tier', ['reguler', 'vip', 'panitia'])->default('reguler');
            $table->enum('status', ['available', 'reserved_panitia', 'booked', 'blocked'])->default('available');
            $table->timestamps();

            $table->unique(['kegiatan_id', 'seat_code']);
            $table->index(['kegiatan_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kegiatan_seats');
    }
};
