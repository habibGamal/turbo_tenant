<?php

declare(strict_types=1);

namespace App\Plugins\CouponsPromotions\src\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Plugins\CouponsPromotions\src\Services\CouponService;
use App\Plugins\LocationsDelivery\src\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class CouponApiController extends Controller
{
    public function __construct(
        private readonly CouponService $couponService
    ) {}

    /**
     * Validate a coupon code for authenticated or guest users.
     */
    public function validate(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
            'cart_items' => 'required|array',
            'sub_total' => 'required|numeric',
            'address_id' => 'nullable|integer',
            'guest_phone' => 'nullable|string',
        ]);

        $user = Auth::user();
        $guestPhone = $request->input('guest_phone');

        $code = $request->input('code');
        $coupon = $this->couponService->findByCode($code);

        if (! $coupon) {
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
            $governorateId,
            $guestPhone
        );

        if (! $validation['valid']) {
            return response()->json($validation);
        }

        // Calculate discount to show to user
        $discount = $this->couponService->calculateDiscount(
            $coupon,
            $request->input('cart_items'),
            (float) $request->input('sub_total')
        );

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
