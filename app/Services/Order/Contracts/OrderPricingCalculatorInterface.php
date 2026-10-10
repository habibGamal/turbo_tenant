<?php

declare(strict_types=1);

namespace App\Services\Order\Contracts;

use App\DTOs\Order\OrderTotals;
use App\DTOs\Order\PlaceOrderData;
use App\Models\Coupon;

interface OrderPricingCalculatorInterface
{
    /**
     * @param  array<int, array<string, mixed>>  $cartItems
     */
    public function calculate(PlaceOrderData $data, array $cartItems, ?Coupon $coupon): OrderTotals;
}
