<?php

declare(strict_types=1);

namespace App\Plugins\CouponsPromotions\src\Http\Controllers\Api;

use App\Core\Contracts\Customer;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Plugins\CartEngine\src\DTOs\CartLine;
use App\Plugins\CartEngine\src\Services\Contracts\CartServiceInterface;
use App\Plugins\CouponsPromotions\src\DTOs\CouponContext;
use App\Plugins\CouponsPromotions\src\Http\Requests\ValidateCouponRequest;
use App\Plugins\CouponsPromotions\src\Services\CouponService;
use App\Plugins\LocationsDelivery\src\Contracts\AddressBook;
use Illuminate\Http\JsonResponse;

final class CouponApiController extends Controller
{
    public function __construct(
        private readonly CouponService $couponService,
        private readonly CartServiceInterface $cartService,
        private readonly AddressBook $addressBook
    ) {}

    /**
     * Validate a coupon code for authenticated or guest users.
     */
    public function validate(ValidateCouponRequest $request): JsonResponse
    {
        $code = (string) $request->input('code');
        $coupon = $this->couponService->findByCode($code);

        if (! $coupon) {
            return ApiResponse::error('Invalid coupon code', 422, [
                'code' => ['Invalid coupon code'],
            ]);
        }

        $user = $request->user();
        $customer = $user instanceof Customer ? $user : null;
        $guestPhone = $request->input('guest_phone');
        $phone = $guestPhone ?: $customer?->customerPhone();

        $addressId = $request->input('address_id') !== null ? (int) $request->input('address_id') : null;
        $areaId = null;
        $governorateId = null;

        if ($addressId !== null) {
            $snapshot = $this->addressBook->snapshotAddress($addressId);
            $areaId = isset($snapshot['area_id']) ? (int) $snapshot['area_id'] : null;
            $governorateId = isset($snapshot['area']['governorate_id'])
                ? (int) $snapshot['area']['governorate_id']
                : null;
        }

        $lines = $this->cartService->getLines($user);
        $subTotal = (float) array_reduce(
            $lines,
            fn (float $total, CartLine $line): float => $total + $line->subtotal,
            0.0
        );

        $context = new CouponContext(
            customer: $customer,
            lines: $lines,
            subtotal: $subTotal,
            areaId: $areaId,
            governorateId: $governorateId,
            phone: $phone !== null ? (string) $phone : null,
            guestId: null,
        );

        $validation = $this->couponService->validateCoupon(
            coupon: $coupon,
            context: $context
        );

        if (! $validation->isValid()) {
            $message = $validation->message();

            return ApiResponse::error($message, 422, [
                'code' => [$message],
            ]);
        }

        // Calculate discount to show to user
        $discount = $this->couponService->calculateDiscount(
            $coupon,
            $lines,
            $subTotal
        );

        return ApiResponse::success([
            'coupon' => [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'discount' => $discount,
                'type' => $coupon->type->value,
                'value' => $coupon->value,
                'free_shipping' => $coupon->conditions['shipping']['free_shipping'] ?? false,
                'applicable_to' => $coupon->conditions['applicable_to'] ?? ['type' => 'all'],
            ],
        ], 'Coupon applied successfully');
    }
}
