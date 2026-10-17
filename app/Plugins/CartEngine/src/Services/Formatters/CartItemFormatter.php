<?php

declare(strict_types=1);

namespace App\Plugins\CartEngine\src\Services\Formatters;

use App\Plugins\CartEngine\src\Models\Cart;
use App\Plugins\CartEngine\src\Models\CartItem;
use App\Plugins\CartEngine\src\Services\Contracts\CartItemFormatterInterface;
use App\Plugins\CartEngine\src\Services\Contracts\CartPriceCalculatorInterface;

final class CartItemFormatter implements CartItemFormatterInterface
{
    public function __construct(
        private readonly CartPriceCalculatorInterface $calculator
    ) {}

    public function formatItem(CartItem $item): array
    {
        $price = $this->calculator->getEffectivePrice($item);
        $extrasTotal = $this->calculator->calculateExtrasTotal($item);
        $finalQuantity = $this->calculator->calculateFinalQuantity($item);
        $subtotal = $this->calculator->calculateSubtotal($item);

        return [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'variant_id' => $item->variant_id,
            'weight_option_value_id' => $item->weight_option_value_id,
            'quantity' => $finalQuantity,
            'weight_multiplier' => $item->weight_multiplier,
            'product' => [
                'id' => $item->product->id,
                'name' => $item->product->name,
                'name_ar' => $item->product->name_ar,
                'image' => $item->product->image,
                'base_price' => $item->product->base_price,
                'price_after_discount' => $item->product->price_after_discount,
                'sell_by_weight' => $item->product->sell_by_weight,
                'weight_option' => $item->product->weightOption ? [
                    'id' => $item->product->weightOption->id,
                    'name' => $item->product->weightOption->name,
                    'unit' => $item->product->weightOption->unit,
                    'values' => $item->product->weightOption->values->map(fn ($val) => [
                        'id' => $val->id,
                        'value' => $val->value,
                        'label' => $val->label,
                        'sort_order' => $val->sort_order,
                    ])->toArray(),
                ] : null,
            ],
            'variant' => $item->variant ? [
                'id' => $item->variant->id,
                'name' => $item->variant->name,
                'name_ar' => $item->variant->name_ar,
                'price' => $item->variant->price,
            ] : null,
            'weight_option_value' => $item->weightOptionValue ? [
                'id' => $item->weightOptionValue->id,
                'value' => $item->weightOptionValue->value,
                'label' => $item->weightOptionValue->label,
            ] : null,
            'extras' => $item->extras->map(fn ($extra) => [
                'id' => $extra->extraOptionItem->id,
                'name' => $extra->extraOptionItem->name,
                'name_ar' => $extra->extraOptionItem->name_ar,
                'price' => $extra->extraOptionItem->price,
                'quantity' => $extra->quantity,
            ])->toArray(),
            'price' => $price,
            'extras_total' => $extrasTotal,
            'subtotal' => $subtotal,
        ];
    }

    public function formatCart(?Cart $cart): array
    {
        if (! $cart) {
            return ['items' => [], 'total' => 0];
        }

        $items = $cart->items->map(fn (CartItem $item) => $this->formatItem($item))->toArray();

        return [
            'items' => $items,
            'total' => $this->calculator->calculateTotal($items),
        ];
    }
}
