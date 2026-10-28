<?php

declare(strict_types=1);

namespace App\Plugins\PaymentProcessing\src\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\PaymentProcessing\src\Services\KashierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class PaymentProcessingApiController extends Controller
{
    public function __construct(
        private readonly KashierService $kashierService
    ) {}

    /**
     * Get payment and order status for an order.
     */
    public function paymentStatus(Request $request, int $orderId): JsonResponse
    {
        $order = Order::with(['items.product', 'user', 'guestUser'])->find($orderId);

        if (! $order) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order not found',
            ], 404);
        }

        if (! $this->canAccessOrder($request, $order)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access to order payment status',
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'order' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                    'payment_method' => $order->payment_method,
                    'total' => (float) $order->total,
                    'currency' => 'EGP',
                ],
                'is_paid' => $order->payment_status === 'paid',
            ],
        ]);
    }

    /**
     * Get Kashier iFrame initialization configuration parameters.
     */
    public function kashierConfig(Request $request, Order $order): JsonResponse
    {
        if (! $this->canAccessOrder($request, $order)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 403);
        }

        $kashierParams = session("kashier_params_{$order->id}");

        if (! $kashierParams) {
            $redirectionUrl = url("/orders/{$order->id}/payment/callback");
            $notificationUrl = url('/api/webhooks/kashier');

            $result = $this->kashierService->createPaymentIntention(
                $order,
                [],
                $redirectionUrl,
                $notificationUrl
            );

            if (! $result['success']) {
                return response()->json([
                    'status' => 'error',
                    'message' => $result['error'] ?? 'Failed to initialize payment',
                ], 422);
            }

            $kashierParams = $result['kashier_params'];
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'kashierParams' => $kashierParams,
                'order' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'total' => (float) $order->total,
                ],
            ],
        ]);
    }

    private function canAccessOrder(Request $request, Order $order): bool
    {
        $userId = Auth::id();

        if ($order->user_id !== null) {
            return $userId !== null && (int) $order->user_id === (int) $userId;
        }

        $guestOrderIds = $request->hasSession()
            ? $request->session()->get('guest_order_ids', [])
            : session('guest_order_ids', []);

        return in_array($order->id, (array) $guestOrderIds, false);
    }
}
