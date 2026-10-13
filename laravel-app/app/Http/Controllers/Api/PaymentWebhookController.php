<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function __construct(
        protected PaymentGatewayService $paymentService
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $rawContent = $request->getContent();
        $signature = $request->header('X-Callback-Signature')
            ?? $request->server('HTTP_X_CALLBACK_SIGNATURE')
            ?? '';

        if (! $this->paymentService->verifyWebhookSignature($rawContent, $signature)) {
            Log::warning('Tripay Webhook Invalid Signature', [
                'ip' => $request->ip(),
                'signature' => $signature,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid signature.',
            ], 403);
        }

        $payload = $request->json()->all();
        if (empty($payload)) {
            $payload = json_decode($rawContent, true) ?? [];
        }

        $result = $this->paymentService->processWebhook($payload);

        return response()->json($result);
    }
}
