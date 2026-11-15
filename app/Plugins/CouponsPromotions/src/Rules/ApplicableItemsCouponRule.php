<?php

declare(strict_types=1);

namespace App\Plugins\CouponsPromotions\src\Rules;

use App\Plugins\CouponsPromotions\src\DTOs\CouponContext;
use App\Plugins\CouponsPromotions\src\Enums\ApplicableTo;
use App\Plugins\CouponsPromotions\src\Models\Coupon;

final class ApplicableItemsCouponRule implements CouponRule
{
    public function check(Coupon $coupon, CouponContext $context): ?string
    {
        $conditions = $coupon->conditions;
        $applicableTo = ApplicableTo::fromConditions($conditions);

        if (! $applicableTo->appliesTo($context->lines, $conditions)) {
            return 'This coupon is not applicable to items in your cart';
        }

        return null;
    }
}
