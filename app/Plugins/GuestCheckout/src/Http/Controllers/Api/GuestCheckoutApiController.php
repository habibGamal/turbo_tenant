<?php

declare(strict_types=1);

namespace App\Plugins\GuestCheckout\src\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Plugins\GuestCheckout\src\Models\GuestUser;
use App\Plugins\OrderManagement\src\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class GuestCheckoutApiController extends Controller
{
    /**
     * Display a listing or configuration for Guest Checkout.
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'plugin' => 'guest-checkout',
                'version' => '1.0.0',
            ],
        ]);
    }

    /**
     * Track guest order by order number and phone number.
     */
    public function track(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_number' => 'required|string',
            'phone' => 'required|string',
            'phone_country_code' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $orderNumber = (string) $request->input('order_number');
        $phone = (string) $request->input('phone');
        $phoneCountryCode = (string) ($request->input('phone_country_code') ?? '+20');

        $guestUser = GuestUser::where('phone', $phone)
            ->where('phone_country_code', $phoneCountryCode)
            ->first();

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
