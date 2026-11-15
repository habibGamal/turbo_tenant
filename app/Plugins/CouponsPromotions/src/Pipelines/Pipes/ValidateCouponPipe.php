<?php

declare(strict_types=1);

namespace App\Plugins\CouponsPromotions\src\Pipelines\Pipes;

use App\Core\Plugins\Contracts\GuestCustomer;
use App\Plugins\CartEngine\src\DTOs\CartLine;
use App\Plugins\CouponsPromotions\src\DTOs\CouponContext;
use App\Plugins\CouponsPromotions\src\Models\Coupon;
use App\Plugins\CouponsPromotions\src\Services\CouponService;
use App\Plugins\LocationsDelivery\src\Contracts\AddressBook;
use App\Plugins\OrderManagement\src\Exceptions\OrderValidationException;
use App\Plugins\OrderManagement\src\Pipelines\Context\OrderValidationContext;
use App\Plugins\OrderManagement\src\Pipelines\Contracts\OrderValidationPipeInterface;
use Closure;

final readonly class ValidateCouponPipe implements OrderValidationPipeInterface
{
    public function __construct(
        private CouponService $couponService,
        private AddressBook $addressBook,
    ) {}

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

            $customer = $context->data->user;
            $guestId = $customer instanceof GuestCustomer ? (int) $customer->getKey() : null;
            $phone = $customer->customerPhone();

            $subTotal = (float) array_reduce(
                $context->lines,
                fn (float $total, CartLine $line): float => $total + $line->subtotal,
                0.0
            );

            $areaId = null;
            $governorateId = null;

            if ($context->data->addressId !== null) {
                $snapshot = $this->addressBook->snapshotAddress($context->data->addressId);
                $areaId = isset($snapshot['area_id']) ? (int) $snapshot['area_id'] : null;
                $governorateId = isset($snapshot['area']['governorate_id'])
                    ? (int) $snapshot['area']['governorate_id']
                    : null;
            } else {
                $areaId = $customer->deliveryAreaId();
            }

            $couponContext = new CouponContext(
                customer: $customer,
                lines: $context->lines,
                subtotal: $subTotal,
                areaId: $areaId,
                governorateId: $governorateId,
                phone: $phone,
                guestId: $guestId,
            );

            $validation = $this->couponService->validateCoupon(
                coupon: $coupon,
                context: $couponContext
            );

            if (! $validation->isValid()) {
                throw new OrderValidationException($validation->message());
            }

            $context->applyPromotion((int) $coupon->getKey(), (string) ($coupon->getAttribute('code') ?? ''));
        }

        return $next($context);
    }
}
