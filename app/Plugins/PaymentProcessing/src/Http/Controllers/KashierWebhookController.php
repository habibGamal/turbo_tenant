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

final class KashierWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentWebhookServiceInterface $webhookService,
        private readonly PaymentGatewayFactory $gatewayFactory
    ) {}

    /**
     * Handle Kashier webhook notification.
     */
    public function handle(Request $request): JsonResponse
    {
        try {
            $signature = $request->header('X-Kashier-Signature');

            if (! $signature) {
                Log::warning('Kashier webhook received without signature', [
                    'gateway' => 'kashier',
                    'result' => 'missing_signature',
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Missing Kashier signature',
                ], 400);
            }

            // T1.4: Explicitly select kashier gateway
            $gateway = $this->gatewayFactory->getGateway('kashier');
            $result = $this->webhookService->handleWebhook($request->all(), (string) $signature, $gateway);

            if (! $result['success']) {
                Log::error('Kashier webhook processing failed', [
                    'gateway' => 'kashier',
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
            Log::warning('Kashier webhook gateway not configured', [
                'gateway' => 'kashier',
                'result' => 'not_configured',
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 503);
        } catch (Exception $e) {
            Log::error('Kashier webhook exception', [
                'gateway' => 'kashier',
                'result' => 'exception',
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
            ], 500);
        }
    }
}
