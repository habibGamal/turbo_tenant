<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Services\Contracts;

use App\Plugins\CartEngine\src\DTOs\CartLine;
use App\Plugins\OrderManagement\src\DTOs\OrderTotals;
use App\Plugins\OrderManagement\src\DTOs\PlaceOrderData;

interface OrderPricingCalculatorInterface
{
    /**
     * @param  list<CartLine>  $lines
     * @param  array<string, mixed>  $attributes
     */
    public function calculate(PlaceOrderData $data, array $lines, array $attributes = []): OrderTotals;
}
