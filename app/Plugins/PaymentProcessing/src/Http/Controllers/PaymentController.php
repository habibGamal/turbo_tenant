<?php

declare(strict_types=1);

namespace App\Plugins\PaymentProcessing\src\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Plugins\PaymentProcessing\src\Contracts\PaymentCallbackServiceInterface;
use App\Plugins\PaymentProcessing\src\Services\KashierService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class PaymentController extends Controller
{
    public function __construct(
        private readonly KashierService $kashierService,
        private readonly PaymentCallbackServiceInterface $paymentCallbackService
    ) {}

    /**
     * Handle payment callback (redirect from payment gateways)
     */
    public function paymentCallback(Request $request, int $orderId): mixed
    {
        // If already redirected to callback status in SPA, render SPA view
        if ($request->has('status')) {
            return response()->view('app');
        }

        try {
            $callbackData = $request->all();
            logger()->info('Payment callback data', ['data' => $callbackData]);

            $result = $this->paymentCallbackService->handleCallback($orderId, $callbackData);
            $status = ! empty($result['success']) ? 'success' : 'failed';
            $message = urlencode($result['message'] ?? $result['error'] ?? 'Unknown status');

            return redirect("/orders/{$orderId}/payment/callback?status={$status}&message={$message}");
        } catch (Exception $e) {
            Log::error('Payment callback exception', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return redirect("/orders/{$orderId}/payment/callback?status=failed&message=".urlencode('Failed to process payment callback'));
        }
    }
}
