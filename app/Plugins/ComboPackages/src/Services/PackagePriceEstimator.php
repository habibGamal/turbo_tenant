<?php

declare(strict_types=1);

namespace App\Plugins\ComboPackages\src\Services;

use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\ProductVariant;

final class PackagePriceEstimator
{
    /**
     * Compute the "separately priced" (à-la-carte) estimate of the combo from form state.
     *
     * - unit price = variant's price when variant_id is set, else product's price_after_discount
     *   if set and > 0, else base_price; multiplied by quantity
     * - 'all' groups: sum every item
     * - conditional groups: sum defaults if count >= min, otherwise cheapest `min` items
     *
     * @param  array<string, mixed>  $state
     */
    public function estimate(array $state): float
    {
        $groups = $state['groups'] ?? [];
        if (! is_array($groups) || empty($groups)) {
            return 0.0;
        }

        // Collect product and variant IDs for bulk loading
        $productIds = [];
        $variantIds = [];
        foreach ($groups as $group) {
            $items = $group['items'] ?? [];
            if (is_array($items)) {
                foreach ($items as $item) {
                    if (! empty($item['product_id'])) {
                        $productIds[] = (int) $item['product_id'];
                    }
                    if (! empty($item['variant_id'])) {
                        $variantIds[] = (int) $item['variant_id'];
                    }
                }
            }
        }

        $products = ! empty($productIds)
            ? Product::whereIn('id', array_unique($productIds))->get()->keyBy('id')
            : collect();

        $variants = ! empty($variantIds)
            ? ProductVariant::whereIn('id', array_unique($variantIds))->get()->keyBy('id')
            : collect();

        $grandTotal = 0.0;

        foreach ($groups as $group) {
            $items = $group['items'] ?? [];
            if (! is_array($items) || empty($items)) {
                continue;
            }

            $selectionType = $group['selection_type'] ?? 'all';
            $evaluatedItems = [];

            foreach ($items as $item) {
                $productId = isset($item['product_id']) && $item['product_id'] !== '' ? (int) $item['product_id'] : null;
                $variantId = isset($item['variant_id']) && $item['variant_id'] !== '' ? (int) $item['variant_id'] : null;
                $quantity = max(1, (int) ($item['quantity'] ?? 1));
                $isDefault = ! empty($item['is_default']);
                $variantMode = $item['variant_mode'] ?? 'fixed';

                $unitPrice = 0.0;
                if ($variantMode === 'customer_choice') {
                    if ($productId !== null && $products->has($productId)) {
                        $product = $products->get($productId);
                        if ($product->price_after_discount !== null && (float) $product->price_after_discount > 0) {
                            $unitPrice = (float) $product->price_after_discount;
                        } else {
                            $unitPrice = (float) $product->base_price;
                        }
                    }
                } elseif ($variantId !== null && $variants->has($variantId)) {
                    $unitPrice = (float) $variants->get($variantId)->price;
                } elseif ($productId !== null && $products->has($productId)) {
                    $product = $products->get($productId);
                    if ($product->price_after_discount !== null && (float) $product->price_after_discount > 0) {
                        $unitPrice = (float) $product->price_after_discount;
                    } else {
                        $unitPrice = (float) $product->base_price;
                    }
                }

                $total = $unitPrice * $quantity;

                $evaluatedItems[] = [
                    'unit_price' => $unitPrice,
                    'quantity' => $quantity,
                    'total' => $total,
                    'is_default' => $isDefault,
                ];
            }

            if (empty($evaluatedItems)) {
                continue;
            }

            if ($selectionType === 'all') {
                $groupTotal = array_sum(array_column($evaluatedItems, 'total'));
            } else {
                // Conditional group: choose_one or choose_multiple
                $min = $selectionType === 'choose_one'
                    ? 1
                    : max(0, (int) ($group['min_selections'] ?? 0));

                $defaults = array_values(array_filter($evaluatedItems, fn ($i) => $i['is_default']));
                $defaultCount = count($defaults);

                if ($defaultCount >= $min && $min > 0) {
                    $groupTotal = array_sum(array_column($defaults, 'total'));
                } elseif ($min === 0 && $defaultCount > 0) {
                    $groupTotal = array_sum(array_column($defaults, 'total'));
                } elseif ($min === 0) {
                    $groupTotal = 0.0;
                } else {
                    // Fallback to the cheapest `min` items
                    usort($evaluatedItems, fn ($a, $b) => $a['total'] <=> $b['total']);
                    $cheapest = array_slice($evaluatedItems, 0, $min);
                    $groupTotal = array_sum(array_column($cheapest, 'total'));
                }
            }

            $grandTotal += $groupTotal;
        }

        return round($grandTotal, 2);
    }

    /**
     * Alias for estimate().
     *
     * @param  array<string, mixed>  $state
     */
    public function calculate(array $state): float
    {
        return $this->estimate($state);
    }
}
