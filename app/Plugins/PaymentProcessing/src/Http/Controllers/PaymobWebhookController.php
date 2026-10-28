<?php

declare(strict_types=1);

namespace App\Plugins\PaymentProcessing\src\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Plugins\PaymentProcessing\src\Contracts\PaymentWebhookServiceInterface;
use App\Plugins\PaymentProcessing\src\Exceptions\PaymentGatewayNotConfiguredException;
use App\Plugins\PaymentProcessing\src\Services\PaymentGatewayFactory;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class PaymobWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentWebhookServiceInterface $webhookService,
        private readonly PaymentGatewayFactory $gatewayFactory
    ) {}

    /**
     * Handle Paymob webhook notification
     */
    public function handle(Request $request): JsonResponse
    {
        try {
            // T1.8: Read HMAC only from query string, never from body
            $hmac = $request->query('hmac');

            if (! $hmac) {
                Log::warning('Paymob webhook received without HMAC', [
                    'gateway' => 'paymob',
                    'result' => 'missing_hmac',
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Missing HMAC signature',
                ], 400);
            }

            // T1.4: Explicitly select paymob gateway
            $gateway = $this->gatewayFactory->getGateway('paymob');
            $result = $this->webhookService->handleWebhook($request->all(), (string) $hmac, $gateway);

            if (! $result['success']) {
                Log::error('Paymob webhook processing failed', [
                    'gateway' => 'paymob',
                    'error' => $result['error'] ?? 'Unknown error',
                    'result' => 'failed',
                ]);

                return response()->json([
                    'success' => false,
                    'error' => $result['error'],
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Webhook processed successfully',
            ]);
        } catch (PaymentGatewayNotConfiguredException $e) {
            // T1.3: Gateway not configured mapped to HTTP 503 with no state change
            Log::warning('Paymob webhook gateway not configured', [
                'gateway' => 'paymob',
                'result' => 'not_configured',
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 503);
        } catch (Exception $e) {
            Log::error('Paymob webhook exception', [
                'gateway' => 'paymob',
                'result' => 'exception',
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
            ], 500);
        }
    }
}
