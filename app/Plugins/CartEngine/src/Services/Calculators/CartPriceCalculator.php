<?php

declare(strict_types=1);

namespace App\Plugins\CartEngine\src\Services\Calculators;

use App\Plugins\CartEngine\src\Models\CartItem;
use App\Plugins\CartEngine\src\Services\Contracts\CartPriceCalculatorInterface;

final class CartPriceCalculator implements CartPriceCalculatorInterface
{
    public function getEffectivePrice(CartItem $item): float
    {
        $productPrice = (float) ($item->product->price_after_discount ?? $item->product->base_price);

        if ($item->variant && $item->variant->price !== null) {
            return (float) $item->variant->price;
        }

        return $productPrice;
    }

    public function calculateExtrasTotal(CartItem $item): float
    {
        return (float) $item->extras->sum(fn ($extra) => $extra->extraOptionItem->price * $extra->quantity);
    }

    public function calculateFinalQuantity(CartItem $item): float
    {
        if ($item->product->sell_by_weight && $item->weightOptionValue) {
            return $item->weight_multiplier * (float) $item->weightOptionValue->value;
        }

        return (float) $item->quantity;
    }

    public function calculateSubtotal(CartItem $item): float
    {
        $price = $this->getEffectivePrice($item);
        $finalQuantity = $this->calculateFinalQuantity($item);
        $extrasTotal = $this->calculateExtrasTotal($item);

        return $item->product->sell_by_weight
            ? ($price * $finalQuantity) + ($extrasTotal * $item->weight_multiplier)
            : ($price + $extrasTotal) * $finalQuantity;
    }

    public function calculateTotal(array $items): float
    {
        return (float) array_reduce($items, fn ($total, $item) => $total + ($item['subtotal'] ?? 0), 0.0);
    }
}
