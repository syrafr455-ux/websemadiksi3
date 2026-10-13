<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('order_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('gross_amount', 12, 2);
            $table->string('payment_method'); // qris, bca_va, bni_va, dll
            $table->enum('payment_status', ['pending', 'settlement', 'expired', 'failed'])->default('pending');
            $table->jsonb('payload_response')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['payment_status', 'expired_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
