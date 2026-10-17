<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Plugins\OrderManagement\src\Http\Requests\OrderStatusUpdateRequest;
use App\Plugins\OrderManagement\src\Models\Order;
use Exception;
use Illuminate\Http\JsonResponse;

class OrderStatusController extends Controller
{
    /**
     * Update the status of an order by order number.
     */
    public function update(OrderStatusUpdateRequest $request): JsonResponse
    {
        try {
            $order = Order::where('order_number', $request->input('orderNumber'))->firstOrFail();

            $order->update([
                'status' => OrderStatus::from($request->input('status')),
            ]);

            return response()->json([
                'message' => 'تم تحديث حالة الطلب',
            ]);
        } catch (Exception $e) {
            logger()->error($e->getMessage());

            return response()->json([
                'message' => 'حدث خطأ ما',
            ], 400);
        }
    }
}
