<?php

use App\Http\Controllers\Api\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/payment/webhook', [PaymentWebhookController::class, 'handle'])->name('api.payment.webhook');
