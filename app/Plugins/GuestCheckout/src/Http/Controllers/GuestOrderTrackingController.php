<?php

declare(strict_types=1);

namespace App\Plugins\GuestCheckout\src\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Plugins\GuestCheckout\src\Models\GuestUser;
use App\Plugins\OrderManagement\src\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

final class GuestOrderTrackingController extends Controller
{
    public function showTrackPage(): Response
    {
        return Inertia::render('Track');
    }

    public function trackGuestOrder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_number' => 'required|string',
            'phone' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $orderNumber = (string) $request->input('order_number');
        $phone = (string) $request->input('phone');

        $guestUser = GuestUser::where('phone', $phone)->first();

        if (! $guestUser) {
            return response()->json([
                'success' => false,
                'error' => 'No orders found for this phone number',
            ], 404);
        }

        $order = Order::with(['items.extras', 'items.product.weightOption', 'items.weightOptionValue', 'branch', 'guestUser'])
            ->where('order_number', $orderNumber)
            ->where('guest_user_id', $guestUser->id)
            ->first();

        if (! $order) {
            return response()->json([
                'success' => false,
                'error' => 'Order not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'order' => $order,
        ]);
    }
}
