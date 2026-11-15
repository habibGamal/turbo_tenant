<?php

declare(strict_types=1);

namespace App\Plugins\CouponsPromotions\src\Rules;

use App\Plugins\CouponsPromotions\src\DTOs\CouponContext;
use App\Plugins\CouponsPromotions\src\Models\Coupon;
use App\Support\TimeWindow;
use Carbon\Carbon;

final class TimeRestrictionsCouponRule implements CouponRule
{
    public function check(Coupon $coupon, CouponContext $context): ?string
    {
        $conditions = $coupon->conditions;
        $now = Carbon::now();

        // Check valid days (0 = Sunday, 6 = Saturday)
        $validDays = $conditions['valid_days'] ?? null;
        if ($validDays !== null && is_array($validDays) && ! empty($validDays)) {
            if (! in_array($now->dayOfWeek, $validDays)) {
                return 'This coupon is not valid at this time';
            }
        }

        // Check valid hours
        $validHours = $conditions['valid_hours'] ?? [];
        $startTime = $validHours['start'] ?? null;
        $endTime = $validHours['end'] ?? null;

        if ($startTime !== null && $endTime !== null) {
            $window = TimeWindow::fromStrings((string) $startTime, (string) $endTime);
            if (! $window->contains($now)) {
                return 'This coupon is not valid at this time';
            }
        }

        return null;
    }
}
