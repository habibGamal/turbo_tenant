<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Plugins\OrderManagement\src\Http\Requests\OrderStatusUpdateRequest;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Models\OrderStatusEvent;
use App\Plugins\OrderManagement\src\Services\OrderStatusTransitions;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class OrderStatusController extends Controller
{
    public function __construct(
        private readonly OrderStatusTransitions $transitions
    ) {}

    /**
     * Update the status of an order by order number.
     */
    public function update(OrderStatusUpdateRequest $request): JsonResponse
    {
        try {
            $order = Order::query()
                ->where('order_number', $request->validated('orderNumber'))
                ->firstOrFail();
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'الطلب غير موجود',
            ], 404);
        }

        $fromStatus = $order->status;
        $targetStatus = OrderStatus::from((string) $request->validated('status'));

        if (! $this->transitions->canTransition($fromStatus, $targetStatus)) {
            return response()->json([
                'message' => "Disallowed status transition from {$fromStatus->value} to {$targetStatus->value}.",
                'from_status' => $fromStatus->value,
                'to_status' => $targetStatus->value,
            ], 409);
        }

        DB::transaction(function () use ($order, $fromStatus, $targetStatus, $request): void {
            $order->update([
                'status' => $targetStatus,
            ]);

            OrderStatusEvent::create([
                'order_id' => $order->id,
                'from_status' => $fromStatus,
                'to_status' => $targetStatus,
                'source' => 'integration',
                'ip' => $request->ip(),
            ]);
        });

        return response()->json([
            'message' => 'تم تحديث حالة الطلب',
        ]);
    }
}
