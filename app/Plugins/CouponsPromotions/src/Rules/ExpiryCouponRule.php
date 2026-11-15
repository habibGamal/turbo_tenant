<?php

declare(strict_types=1);

namespace App\Plugins\CouponsPromotions\src\Rules;

use App\Plugins\CouponsPromotions\src\DTOs\CouponContext;
use App\Plugins\CouponsPromotions\src\Models\Coupon;
use Carbon\Carbon;

final class ExpiryCouponRule implements CouponRule
{
    public function check(Coupon $coupon, CouponContext $context): ?string
    {
        if ($coupon->expiry_date && Carbon::parse($coupon->expiry_date)->isPast()) {
            return 'This coupon has expired';
        }

        return null;
    }
}
