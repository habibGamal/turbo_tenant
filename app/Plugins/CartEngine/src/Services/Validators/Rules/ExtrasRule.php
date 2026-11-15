<?php

declare(strict_types=1);

namespace App\Plugins\CartEngine\src\Services\Validators\Rules;

use App\Plugins\CatalogProducts\src\Models\ExtraOptionItem;
use App\Plugins\CatalogProducts\src\Models\Product;

final class ExtrasRule
{
    /**
     * @param  list<array{id: int, quantity: int}>  $extras
     * @return array<string, list<string>>
     */
    public function validate(Product $product, array $extras): array
    {
        $error = $this->firstError($product, $extras);

        return $error === null ? [] : ['extras' => [$error]];
    }

    /**
     * @param  list<array{id: int, quantity: int}>  $extras
     */
    private function firstError(Product $product, array $extras): ?string
    {
        $rules = $product->effectiveSelectionRules();
        $count = count($extras);
        $minSelections = $rules->minSelections ?? 0;
        $maxSelections = $rules->maxSelections ?? 0;

        if ($count === 0) {
            return $minSelections > 0 ? "Minimum {$minSelections} extra selection(s) required." : null;
        }

        if ($rules === null) {
            return 'This product does not allow extras.';
        }

        $ids = array_column($extras, 'id');
        if (count($ids) !== count(array_unique($ids))) {
            return 'Duplicate extras are not allowed.';
        }

        if (! $rules->allowMultiple && $count > 1) {
            return 'Multiple extras are not allowed for this product.';
        }

        if ($minSelections > 0 && $count < $minSelections) {
            return "Minimum {$minSelections} extra selection(s) required.";
        }

        if ($maxSelections > 0 && $count > $maxSelections) {
            return "Maximum {$maxSelections} extra selection(s) allowed.";
        }

        foreach ($extras as $extra) {
            /** @var ExtraOptionItem|null $item */
            $item = $product->extraOption->items->firstWhere('id', $extra['id']);

            if ($item === null) {
                return 'One or more selected extras do not belong to this product.';
            }

            if ($extra['quantity'] > 1 && ! $item->getAttribute('allow_quantity')) {
                return "Quantity greater than 1 is not allowed for extra '{$item->name}'.";
            }
        }

        return null;
    }
}
