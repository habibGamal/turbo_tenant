<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Services\Contracts;

use App\Plugins\OrderManagement\src\DTOs\OrderTotals;
use App\Plugins\OrderManagement\src\DTOs\PlaceOrderData;

interface OrderPricingCalculatorInterface
{
    /**
     * @param  array<int, array<string, mixed>>  $cartItems
     * @param  array<string, mixed>  $attributes
     */
    public function calculate(PlaceOrderData $data, array $cartItems, array $attributes = []): OrderTotals;
}
