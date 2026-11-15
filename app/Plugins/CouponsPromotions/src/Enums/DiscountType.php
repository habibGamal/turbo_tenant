<?php

declare(strict_types=1);

namespace App\Plugins\CouponsPromotions\src\Enums;

enum DiscountType: string
{
    case PERCENTAGE = 'percentage';
    case FIXED = 'fixed';

    public function calculateDiscount(float $applicableAmount, float $value): float
    {
        $discount = match ($this) {
            self::PERCENTAGE => ($applicableAmount * $value) / 100,
            self::FIXED => min($value, $applicableAmount),
        };

        return round($discount, 2);
    }
}
