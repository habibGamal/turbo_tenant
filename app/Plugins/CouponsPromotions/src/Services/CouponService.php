<?php

declare(strict_types=1);

namespace App\Plugins\CouponsPromotions\src\Services;

use App\Plugins\CartEngine\src\DTOs\CartLine;
use App\Plugins\CouponsPromotions\src\DTOs\CouponContext;
use App\Plugins\CouponsPromotions\src\DTOs\CouponValidationResult;
use App\Plugins\CouponsPromotions\src\Enums\ApplicableTo;
use App\Plugins\CouponsPromotions\src\Models\Coupon;
use App\Plugins\CouponsPromotions\src\Rules\CouponRule;
use Illuminate\Support\Facades\DB;

final class CouponService
{
    /**
     * @param  iterable<CouponRule>  $rules
     */
    public function __construct(
        private readonly iterable $rules,
    ) {}

    /**
     * Validate if a coupon can be applied to an order
     */
    public function validateCoupon(Coupon $coupon, CouponContext $context): CouponValidationResult
    {
        foreach ($this->rules as $rule) {
            $error = $rule->check($coupon, $context);
            if ($error !== null) {
                return CouponValidationResult::invalid($error);
            }
        }

        return CouponValidationResult::valid();
    }

    /**
     * Calculate discount amount based on coupon and order details
     *
     * @param  list<CartLine>  $cartLines
     */
    public function calculateDiscount(
        Coupon $coupon,
        array $cartLines,
        float $subTotal
    ): float {
        $conditions = $coupon->conditions;
        $applicableTo = ApplicableTo::fromConditions($conditions);
        $applicableAmount = $applicableTo->sumApplicableSubtotal($cartLines, $conditions, $subTotal);

        return $coupon->type->calculateDiscount($applicableAmount, (float) $coupon->value);
    }

    /**
     * Calculate shipping fee with coupon considerations
     */
    public function calculateShippingFee(
        ?Coupon $coupon,
        float $originalShippingFee,
        float $subTotal,
        float $discount
    ): float {
        if (! $coupon) {
            return $originalShippingFee;
        }

        $conditions = $coupon->conditions;

        // Check if free shipping is enabled
        if (isset($conditions['shipping']['free_shipping']) && $conditions['shipping']['free_shipping']) {
            // Check if there's a threshold
            if (isset($conditions['shipping']['free_shipping_threshold'])) {
                $orderTotal = $subTotal - $discount;
                if ($orderTotal >= (float) $conditions['shipping']['free_shipping_threshold']) {
                    return 0.0;
                }
            } else {
                // No threshold, free shipping applies
                return 0.0;
            }
        }

        return $originalShippingFee;
    }

    /**
     * Apply coupon to order (atomic usage and consumed increment)
     */
    public function applyCoupon(Coupon $coupon, float $discountAmount): void
    {
        $amount = number_format($discountAmount, 2, '.', '');

        Coupon::query()
            ->where('id', $coupon->getKey())
            ->update([
                'usage_count' => DB::raw('usage_count + 1'),
                'total_consumed' => DB::raw('total_consumed + '.$amount),
            ]);
    }

    /**
     * Find coupon by code
     */
    public function findByCode(string $code): ?Coupon
    {
        return Coupon::where('code', mb_strtoupper($code))
            ->where('is_active', true)
            ->first();
    }
}
