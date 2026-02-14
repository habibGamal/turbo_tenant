<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\PlaceOrderService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class KashierWebhookController extends Controller
{
    public function __construct(
        private readonly PlaceOrderService $placeOrderService
    ) {}

    /**
     * Handle Kashier webhook notification.
     */
    public function handle(Request $request): JsonResponse
    {
        try {
            $webhookData = $request->all();

            // Get signature from header
            $signature = $request->header('X-Kashier-Signature');

            if (! $signature) {
                Log::warning('Kashier webhook received without signature', [
                    'data' => $webhookData,
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Missing Kashier signature',
                ], 400);
            }

            Log::info('Kashier webhook received', [
                'event' => $webhookData['event'] ?? 'unknown',
                'transaction_id' => $webhookData['data']['transactionId'] ?? null,
            ]);

            $result = $this->placeOrderService->handleWebhook($webhookData, $signature);

            if (! $result['success']) {
                Log::error('Kashier webhook processing failed', [
                    'error' => $result['error'] ?? 'Unknown error',
                    'data' => $webhookData,
                ]);

                return response()->json([
                    'success' => false,
                    'error' => $result['error'],
                ], 400);
            }

            Log::info('Kashier webhook processed successfully', [
                'order_id' => $result['order']->id ?? null,
                'payment_status' => $result['payment_data']['payment_status'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Webhook processed successfully',
            ]);
        } catch (Exception $e) {
            Log::error('Kashier webhook exception', [
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
