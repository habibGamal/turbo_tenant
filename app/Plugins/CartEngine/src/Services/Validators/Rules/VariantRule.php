<?php

declare(strict_types=1);

namespace App\Plugins\CartEngine\src\Services\Validators\Rules;

use App\Plugins\CatalogProducts\src\Models\Product;

final class VariantRule
{
    /**
     * @return array<string, list<string>>
     */
    public function validate(Product $product, ?int $variantId): array
    {
        $hasVariants = $product->variants->isNotEmpty();

        if ($hasVariants) {
            if ($variantId === null) {
                return [
                    'variant_id' => ['Variant is required for this product.'],
                ];
            }

            $variant = $product->variants->firstWhere('id', $variantId);

            if (! $variant) {
                return [
                    'variant_id' => ['Selected variant does not belong to this product.'],
                ];
            }

            if (! $variant->is_available) {
                return [
                    'variant_id' => ['Selected variant is currently unavailable.'],
                ];
            }
        } elseif ($variantId !== null) {
            return [
                'variant_id' => ['This product does not have variants.'],
            ];
        }

        return [];
    }
}
