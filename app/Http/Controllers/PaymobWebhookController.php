<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\OrderPOSService;
use App\Services\PlaceOrderService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class PaymobWebhookController extends Controller
{
    public function __construct(
        private readonly PlaceOrderService $placeOrderService
    ) {
    }

    /**
     * Handle Paymob webhook notification
     */
    public function handle(Request $request): JsonResponse
    {
        try {
            $webhookData = $request->all();
            $hmac = $request->query('hmac') ?? $webhookData['hmac'] ?? null;
            logger()->info('Paymob webhook data', ['data' => $webhookData]);
            if (!$hmac) {
                Log::warning('Paymob webhook received without HMAC', [
                    'data' => $webhookData,
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Missing HMAC signature',
                ], 400);
            }

            Log::info('Paymob webhook received', [
                'type' => $webhookData['type'] ?? 'unknown',
                'transaction_id' => $webhookData['obj']['id'] ?? null,
            ]);

            $result = $this->placeOrderService->handleWebhook($webhookData, $hmac);

            if (!$result['success']) {
                Log::error('Paymob webhook processing failed', [
                    'error' => $result['error'] ?? 'Unknown error',
                    'data' => $webhookData,
                ]);

                return response()->json([
                    'success' => false,
                    'error' => $result['error'],
                ], 400);
            }

            Log::info('Paymob webhook processed successfully', [
                'order_id' => $result['order']->id ?? null,
                'payment_status' => $result['payment_data']['payment_status'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Webhook processed successfully',
            ]);
        } catch (Exception $e) {
            Log::error('Paymob webhook exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
            ], 500);
        }
    }
}
