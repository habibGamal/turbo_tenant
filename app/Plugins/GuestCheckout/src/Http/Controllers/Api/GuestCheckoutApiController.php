<?php

declare(strict_types=1);

namespace App\Plugins\GuestCheckout\src\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Plugins\GuestCheckout\src\Http\Requests\TrackOrderRequest;
use App\Plugins\GuestCheckout\src\Models\GuestUser;
use App\Plugins\GuestCheckout\src\Services\GuestTrackingTokenService;
use App\Plugins\OrderManagement\src\Http\Resources\OrderResource;
use App\Plugins\OrderManagement\src\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GuestCheckoutApiController extends Controller
{
    private const array TRACKING_RELATIONS = [
        'items.extras',
        'items.product.weightOption',
        'items.weightOptionValue',
        'branch',
        'address.area',
        'guestUser.area',
    ];

    public function __construct(
        private readonly GuestTrackingTokenService $tokenService,
    ) {}

    /**
     * Display a listing or configuration for Guest Checkout.
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'plugin' => 'guest-checkout',
            'version' => '1.0.0',
        ]);
    }

    /**
     * Track guest order by order number and tracking token or phone number.
     */
    public function track(TrackOrderRequest $request): JsonResponse
    {
        $orderNumber = (string) $request->input('order_number');

        if ($request->filled('token')) {
            $order = Order::with(self::TRACKING_RELATIONS)
                ->where('order_number', $orderNumber)
                ->first();

            if (! $order || ! $order->isGuestOrder() || ! $this->tokenService->verify($order, (string) $request->input('token'))) {
                return ApiResponse::error('Order not found', 404);
            }

            return ApiResponse::success(new OrderResource($order));
        }

        $phone = (string) $request->input('phone');
        $phoneCountryCode = (string) ($request->input('phone_country_code') ?? '+20');

        $guestUser = GuestUser::where('phone', $phone)
            ->where('phone_country_code', $phoneCountryCode)
            ->first();

        if (! $guestUser) {
            return ApiResponse::error('Order not found', 404);
        }

        $order = Order::with(self::TRACKING_RELATIONS)
            ->where('order_number', $orderNumber)
            ->where('guest_user_id', $guestUser->id)
            ->first();

        if (! $order) {
            return ApiResponse::error('Order not found', 404);
        }

        return ApiResponse::success(new OrderResource($order));
    }
}
