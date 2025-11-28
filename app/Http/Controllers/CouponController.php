<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\CouponService;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class CouponController extends Controller
{
    public function __construct(
        private readonly CouponService $couponService
    ) {
    }

    /**
     * Validate a coupon code
     */
    public function validate(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
            'cart_items' => 'required|array',
            'sub_total' => 'required|numeric',
            'address_id' => 'nullable|integer|exists:addresses,id',
        ]);

        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'valid' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        $code = $request->input('code');
        $coupon = $this->couponService->findByCode($code);

        if (!$coupon) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid coupon code',
            ]);
        }

        $addressId = $request->input('address_id');
        $address = $addressId ? Address::with('area.governorate')->find($addressId) : null;
        $areaId = $address?->area_id;
        $governorateId = $address?->area?->governorate_id;

        $validation = $this->couponService->validateCoupon(
            $coupon,
            $user,
            $request->input('cart_items'),
            (float) $request->input('sub_total'),
            $addressId ? (int) $addressId : null,
            $areaId,
            $governorateId
        );

        if (!$validation['valid']) {
            return response()->json($validation);
        }

        // Calculate discount to show to user
        $discount = $this->couponService->calculateDiscount(
            $coupon,
            $request->input('cart_items'),
            (float) $request->input('sub_total')
        );

        // Calculate shipping discount if any
        // This is a bit tricky as we need the original shipping fee.
        // For now, we'll just return the coupon details and let the frontend/backend order placement handle the exact math.
        // But the user wants to see the discount.

        return response()->json([
            'valid' => true,
            'message' => 'Coupon applied successfully',
            'coupon' => [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'discount' => $discount,
                'type' => $coupon->type,
                'value' => $coupon->value,
                'free_shipping' => $coupon->conditions['shipping']['free_shipping'] ?? false,
            ],
        ]);
    }
}
