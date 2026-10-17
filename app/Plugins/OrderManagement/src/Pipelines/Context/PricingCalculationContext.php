<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Pipelines\Context;

use App\Plugins\OrderManagement\src\DTOs\OrderTotals;
use App\Plugins\OrderManagement\src\DTOs\PlaceOrderData;
use App\Plugins\OrderManagement\src\Pipelines\DTOs\PricingAdjustment;

final class PricingCalculationContext
{
    public float $subTotal = 0.0;

    /** @var array<string, PricingAdjustment> */
    private array $adjustments = [];

    /** @var array<string, mixed> */
    private array $attributes = [];

    /**
     * @param  array<int, array<string, mixed>>  $cartItems
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public readonly PlaceOrderData $data,
        public readonly array $cartItems,
        public readonly array $payload = [],
        array $attributes = []
    ) {
        $this->attributes = $attributes;
    }

    public function set(string $key, mixed $value): self
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    public function addAdjustment(PricingAdjustment $adjustment): self
    {
        $this->adjustments[$adjustment->identifier] = $adjustment;

        return $this;
    }

    public function removeAdjustment(string $identifier): self
    {
        unset($this->adjustments[$identifier]);

        return $this;
    }

    /**
     * @return array<string, PricingAdjustment>
     */
    public function getAdjustments(): array
    {
        return $this->adjustments;
    }

    public function getAdjustmentTotal(string $type): float
    {
        $total = 0.0;
        foreach ($this->adjustments as $adjustment) {
            if ($adjustment->type === $type) {
                $total += $adjustment->amount;
            }
        }

        return $total;
    }

    public function calculateTotal(): float
    {
        $adjustmentsSum = 0.0;
        foreach ($this->adjustments as $adjustment) {
            $adjustmentsSum += $adjustment->amount;
        }

        return max(0.0, round($this->subTotal + $adjustmentsSum, 2));
    }

    public function toOrderTotals(): OrderTotals
    {
        $discount = abs($this->getAdjustmentTotal(PricingAdjustment::TYPE_DISCOUNT));
        $tax = $this->getAdjustmentTotal(PricingAdjustment::TYPE_TAX);
        $service = $this->getAdjustmentTotal(PricingAdjustment::TYPE_FEE);
        $deliveryFee = $this->getAdjustmentTotal(PricingAdjustment::TYPE_SHIPPING);
        $total = $this->calculateTotal();

        return new OrderTotals(
            subTotal: round($this->subTotal, 2),
            discount: round($discount, 2),
            tax: round($tax, 2),
            service: round($service, 2),
            deliveryFee: round($deliveryFee, 2),
            total: $total
        );
    }
}
