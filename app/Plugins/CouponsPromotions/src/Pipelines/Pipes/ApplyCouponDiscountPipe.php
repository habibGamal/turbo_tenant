<?php

declare(strict_types=1);

namespace App\Plugins\CouponsPromotions\src\Pipelines\Pipes;

use App\Plugins\CouponsPromotions\src\Models\Coupon;
use App\Plugins\CouponsPromotions\src\Services\CouponService;
use App\Plugins\OrderManagement\src\Pipelines\Context\PricingCalculationContext;
use App\Plugins\OrderManagement\src\Pipelines\Contracts\PricingCalculationPipeInterface;
use App\Plugins\OrderManagement\src\Pipelines\DTOs\PricingAdjustment;
use Closure;

final readonly class ApplyCouponDiscountPipe implements PricingCalculationPipeInterface
{
    public function __construct(private CouponService $couponService) {}

    public function handle(PricingCalculationContext $context, Closure $next): mixed
    {
        $coupon = $context->get('applied_coupon');

        if (! $coupon && $context->data->couponId) {
            $coupon = Coupon::find($context->data->couponId);
        }

        if ($coupon instanceof Coupon) {
            $discount = (float) $this->couponService->calculateDiscount(
                $coupon,
                $context->cartItems,
                $context->subTotal
            );

            if ($discount > 0) {
                $context->addAdjustment(new PricingAdjustment(
                    identifier: 'discount:coupon',
                    label: "Coupon Discount ({$coupon->code})",
                    amount: -$discount,
                    type: PricingAdjustment::TYPE_DISCOUNT
                ));
            }

            $context->set('applied_coupon', $coupon);
            $context->set('coupon_id', $coupon->id);
        }

        $result = $next($context);

        if ($coupon instanceof Coupon) {
            $currentShipping = $context->getAdjustmentTotal(PricingAdjustment::TYPE_SHIPPING);
            if ($currentShipping > 0) {
                $discountTotal = abs($context->getAdjustmentTotal(PricingAdjustment::TYPE_DISCOUNT));
                $newShipping = (float) $this->couponService->calculateShippingFee(
                    $coupon,
                    $currentShipping,
                    $context->subTotal,
                    $discountTotal
                );

                if ($newShipping < $currentShipping) {
                    $context->removeAdjustment('shipping:delivery');
                    if ($newShipping > 0) {
                        $context->addAdjustment(new PricingAdjustment(
                            identifier: 'shipping:delivery',
                            label: 'Delivery Fee',
                            amount: $newShipping,
                            type: PricingAdjustment::TYPE_SHIPPING
                        ));
                    }
                }
            }
        }

        return $result;
    }
}
