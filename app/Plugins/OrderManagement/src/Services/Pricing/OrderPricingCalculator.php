<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Services\Pricing;

use App\Plugins\OrderManagement\src\DTOs\OrderTotals;
use App\Plugins\OrderManagement\src\DTOs\PlaceOrderData;
use App\Plugins\OrderManagement\src\Pipelines\Context\PricingCalculationContext;
use App\Plugins\OrderManagement\src\Pipelines\PricingCalculationPipeline;
use App\Plugins\OrderManagement\src\Services\Contracts\OrderPricingCalculatorInterface;

class OrderPricingCalculator implements OrderPricingCalculatorInterface
{
    public function __construct(
        private PricingCalculationPipeline $pricingPipeline
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $cartItems
     * @param  array<string, mixed>  $attributes
     */
    public function calculate(PlaceOrderData $data, array $cartItems, array $attributes = []): OrderTotals
    {
        $context = new PricingCalculationContext(
            data: $data,
            cartItems: $cartItems,
            attributes: $attributes
        );

        $processedContext = $this->pricingPipeline->process($context);

        return $processedContext->toOrderTotals();
    }
}
