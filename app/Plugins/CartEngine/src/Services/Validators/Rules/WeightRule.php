<?php

declare(strict_types=1);

namespace App\Plugins\CartEngine\src\Services\Validators\Rules;

use App\Plugins\CatalogProducts\src\Models\Product;

final class WeightRule
{
    /**
     * @return array<string, list<string>>
     */
    public function validate(Product $product, ?int $weightOptionValueId): array
    {
        if ($product->sell_by_weight) {
            if ($weightOptionValueId === null) {
                return [
                    'weight_option_value_id' => ['Weight option is required for this product.'],
                ];
            }

            $isValidWeight = $product->weight_options_id
                && $product->weightOption?->values->contains('id', $weightOptionValueId);

            if (! $isValidWeight) {
                return [
                    'weight_option_value_id' => ['Selected weight option does not belong to this product.'],
                ];
            }
        } elseif ($weightOptionValueId !== null) {
            return [
                'weight_option_value_id' => ['This product is not sold by weight.'],
            ];
        }

        return [];
    }
}
