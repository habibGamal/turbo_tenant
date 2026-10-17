<?php

declare(strict_types=1);

namespace App\Plugins\CouponsPromotions\src\Pipelines\Pipes;

use App\Exceptions\OrderValidationException;
use App\Models\User;
use App\Plugins\CouponsPromotions\src\Models\Coupon;
use App\Plugins\CouponsPromotions\src\Services\CouponService;
use App\Plugins\LocationsDelivery\src\Models\Address;
use App\Plugins\OrderManagement\src\Pipelines\Context\OrderValidationContext;
use App\Plugins\OrderManagement\src\Pipelines\Contracts\OrderValidationPipeInterface;
use Closure;

final readonly class ValidateCouponPipe implements OrderValidationPipeInterface
{
    public function __construct(private CouponService $couponService) {}

    public function handle(OrderValidationContext $context, Closure $next): mixed
    {
        $couponId = $context->data->couponId;

        if ($couponId !== null) {
            $coupon = Coupon::find($couponId);

            if (! $coupon) {
                throw new OrderValidationException('Invalid coupon');
            }

            if (! $coupon->is_active) {
                throw new OrderValidationException('This coupon is not active');
            }

            $user = $context->data->user;
            $guestPhone = $context->data->isGuest() ? ($context->data->user->phone ?? null) : null;

            $subTotal = array_reduce(
                $context->cart['items'] ?? [],
                fn ($total, $item): float => $total + (float) ($item['subtotal'] ?? 0),
                0.0
            );

            $address = $context->data->addressId ? Address::find($context->data->addressId) : null;
            $areaId = $address?->area_id ?? ($context->data->user?->area_id ?? null);
            $governorateId = $address?->area?->governorate_id ?? null;

            $validation = $this->couponService->validateCoupon(
                coupon: $coupon,
                user: $user,
                cartItems: $context->cart['items'] ?? [],
                subTotal: $subTotal,
                addressId: $context->data->addressId,
                areaId: $areaId,
                governorateId: $governorateId,
                guestPhone: $guestPhone
            );

            if (! $validation['valid']) {
                throw new OrderValidationException($validation['message']);
            }

            $context->set('applied_coupon', $coupon);
            $context->set('coupon_id', $coupon->id);
        }

        return $next($context);
    }
}
