<?php

declare(strict_types=1);

namespace App\Plugins\PaymentProcessing\src\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Plugins\PaymentProcessing\src\Contracts\PaymentCallbackServiceInterface;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class PaymentController extends Controller
{
    public function __construct(
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
            $status = $this->paymentCallbackService->handleCallback($orderId);

            return redirect("/orders/{$orderId}/payment/callback?status={$status}");
        } catch (Exception $e) {
            Log::error('Payment callback exception', [
                'merchant_order_id' => (string) $orderId,
                'result' => 'error',
            ]);

            return redirect("/orders/{$orderId}/payment/callback?status=failed");
        }
    }
}
