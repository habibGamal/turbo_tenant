<?php

declare(strict_types=1);

namespace App\Plugins\PaymentProcessing\src\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\PaymentProcessing\src\Http\Requests\KashierConfigRequest;
use App\Plugins\PaymentProcessing\src\Http\Requests\PaymentStatusRequest;
use App\Plugins\PaymentProcessing\src\Http\Resources\PaymentStatusResource;
use App\Plugins\PaymentProcessing\src\Services\KashierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

final class PaymentProcessingApiController extends Controller
{
    // Legitimate concrete dependency: this controller serves the Kashier-only endpoint (kashier-config).
    public function __construct(
        private readonly KashierService $kashierService
    ) {}

    /**
     * Get payment and order status for an order.
     */
    public function paymentStatus(PaymentStatusRequest $request, int $orderId): JsonResponse
    {
        /** @var Order $order */
        $order = $request->order();

        return ApiResponse::success((new PaymentStatusResource($order))->resolve());
    }

    /**
     * Get Kashier iFrame initialization configuration parameters.
     */
    public function kashierConfig(KashierConfigRequest $request, Order $order): JsonResponse
    {
        $kashierParams = session("kashier_params_{$order->id}");

        if (! $kashierParams) {
            $redirectionUrl = url("/orders/{$order->id}/payment/callback");
            $notificationUrl = url('/api/webhooks/kashier');

            try {
                $result = $this->kashierService->createPaymentIntention(
                    $order,
                    [],
                    $redirectionUrl,
                    $notificationUrl
                );

                if (! $result->successful) {
                    Log::error('Kashier payment intention creation failed', [
                        'order_id' => $order->id,
                        'error' => $result->error,
                    ]);

                    return ApiResponse::error('Failed to initialize payment gateway', 422);
                }

                $kashierParams = $result->data;
            } catch (Throwable $e) {
                Log::error('Kashier configuration exception', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);

                return ApiResponse::error('Failed to initialize payment gateway', 500);
            }
        }

        return ApiResponse::success([
            'kashierParams' => $kashierParams,
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'total' => (float) $order->total,
            ],
        ]);
    }
}
