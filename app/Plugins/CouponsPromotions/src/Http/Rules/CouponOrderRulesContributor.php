<?php

declare(strict_types=1);

namespace App\Plugins\CouponsPromotions\src\Http\Rules;

use App\Plugins\OrderManagement\src\Contracts\PlaceOrderRulesContributor;
use Illuminate\Http\Request;

final class CouponOrderRulesContributor implements PlaceOrderRulesContributor
{
    public function pluginId(): string
    {
        return 'coupons-promotions';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(Request $request): array
    {
        return [
            'coupon_id' => ['nullable', 'integer', 'exists:coupons,id'],
        ];
    }
}
