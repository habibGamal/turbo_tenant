<?php

declare(strict_types=1);

namespace App\Plugins\ComboPackages\src\Services;

use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\ProductVariant;
use App\Plugins\ComboPackages\src\Models\Package;
use App\Plugins\ComboPackages\src\Models\PackageItem;

final class PackageLinePricer
{
    /**
     * Compute the base effective price for a product:
     * price_after_discount when > 0, else base_price.
     */
    public function productBaseEffectivePrice(Product $product): float
    {
        if ($product->price_after_discount !== null && (float) $product->price_after_discount > 0) {
            return (float) $product->price_after_discount;
        }

        return (float) $product->base_price;
    }

    /**
     * Compute the variant upsize delta relative to the product's effective price.
     * Never returns negative deltas (minimum 0).
     */
    public function calculateVariantPriceDelta(Product $product, ?ProductVariant $variant): float
    {
        if ($variant === null) {
            return 0.0;
        }

        $baseEffectivePrice = $this->productBaseEffectivePrice($product);
        $delta = (float) $variant->price - $baseEffectivePrice;

        return max(0.0, round($delta, 2));
    }

    /**
     * Compute a component's unitPriceAdjustment for ONE combo unit:
     * (package_item.price_adjustment + variant upsize delta) × selection quantity.
     * ('all' items: 0 unless they have a price_adjustment).
     */
    public function calculateComponentAdjustment(
        PackageItem $item,
        ?ProductVariant $variant,
        int $selectionQuantity = 1,
        ?Product $product = null
    ): float {
        $prod = $product ?? $item->product;

        $variantDelta = 0.0;
        if ($item->variant_mode === 'customer_choice' && $variant !== null && $prod !== null) {
            $variantDelta = $this->calculateVariantPriceDelta($prod, $variant);
        }

        $singleExtra = (float) $item->price_adjustment + $variantDelta;

        return round($singleExtra * $selectionQuantity, 2);
    }

    /**
     * Compute the unit price of one combo:
     * package.price + Σ component.unitPriceAdjustment.
     *
     * @param  iterable<object|array<string, mixed>>  $components
     */
    public function calculateComboUnitPrice(Package $package, iterable $components): float
    {
        $adjustmentsSum = 0.0;

        foreach ($components as $component) {
            if (is_object($component)) {
                $adj = (float) ($component->unitPriceAdjustment ?? 0);
                $extras = method_exists($component, 'extrasTotal')
                    ? (float) $component->extrasTotal()
                    : 0.0;
                $adjustmentsSum += ($adj + $extras);
            } elseif (is_array($component)) {
                $adj = (float) ($component['unit_price_adjustment'] ?? $component['unitPriceAdjustment'] ?? 0);
                $extrasTotal = 0.0;
                if (! empty($component['extras']) && is_array($component['extras'])) {
                    foreach ($component['extras'] as $extra) {
                        $ePrice = (float) ($extra['price'] ?? $extra['unitPrice'] ?? $extra['unit_price'] ?? 0);
                        $eQty = (int) ($extra['quantity'] ?? 1);
                        $extrasTotal += $ePrice * $eQty;
                    }
                }
                $adjustmentsSum += ($adj + $extrasTotal);
            }
        }

        return round((float) $package->price + $adjustmentsSum, 2);
    }
}
