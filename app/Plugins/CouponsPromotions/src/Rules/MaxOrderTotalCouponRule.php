<?php

declare(strict_types=1);

namespace App\Plugins\CouponsPromotions\src\Rules;

use App\Plugins\CouponsPromotions\src\DTOs\CouponContext;
use App\Plugins\CouponsPromotions\src\Models\Coupon;

final class MaxOrderTotalCouponRule implements CouponRule
{
    public function check(Coupon $coupon, CouponContext $context): ?string
    {
        $conditions = $coupon->conditions;

        if (isset($conditions['max_order_total'])) {
            // Decision: Maximum order total checks against the full cart subtotal ($context->subtotal),
            // because the order total stays the order total regardless of line eligibility.
            if ($context->subtotal > (float) $conditions['max_order_total']) {
                return "Maximum order total of {$conditions['max_order_total']} exceeded";
            }
        }

        return null;
    }
}
