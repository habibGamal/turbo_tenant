<?php

declare(strict_types=1);

namespace App\Plugins\CouponsPromotions\src\Rules;

use App\Plugins\CouponsPromotions\src\DTOs\CouponContext;
use App\Plugins\CouponsPromotions\src\Models\Coupon;

final class ShippingLocationCouponRule implements CouponRule
{
    public function check(Coupon $coupon, CouponContext $context): ?string
    {
        $conditions = $coupon->conditions;

        // Check governorate restrictions
        $applicableGovernorates = $conditions['shipping']['applicable_governorates'] ?? [];
        if (! empty($applicableGovernorates) && $context->governorateId !== null) {
            if (! in_array($context->governorateId, $applicableGovernorates)) {
                return 'This coupon is not available for your delivery location';
            }
        }

        // Check area restrictions
        $applicableAreas = $conditions['shipping']['applicable_areas'] ?? [];
        if (! empty($applicableAreas) && $context->areaId !== null) {
            if (! in_array($context->areaId, $applicableAreas)) {
                return 'This coupon is not available for your delivery location';
            }
        }

        return null;
    }
}
