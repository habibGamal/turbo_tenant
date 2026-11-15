<?php

declare(strict_types=1);

namespace App\Plugins\CartEngine\src\Services\Validators;

use App\Plugins\CartEngine\src\Services\Contracts\ProductValidatorInterface;
use App\Plugins\CartEngine\src\Services\Validators\Rules\ExtrasRule;
use App\Plugins\CartEngine\src\Services\Validators\Rules\VariantRule;
use App\Plugins\CartEngine\src\Services\Validators\Rules\WeightRule;
use App\Plugins\CatalogProducts\src\Models\Product;
use Illuminate\Validation\ValidationException;

final class ProductValidator implements ProductValidatorInterface
{
    public function __construct(
        private readonly VariantRule $variantRule,
        private readonly WeightRule $weightRule,
        private readonly ExtrasRule $extrasRule,
    ) {}

    /**
     * @param  list<array{id: int, quantity: int}>  $extras
     */
    public function validateOptions(?int $productId, ?int $variantId, ?int $weightOptionValueId, array $extras = []): void
    {
        if ($productId === null) {
            throw ValidationException::withMessages([
                'product_id' => ['Selected product is unavailable.'],
            ]);
        }

        $product = Product::with([
            'variants',
            'extraOption.items',
            'weightOption.values',
        ])->find($productId);

        if (! $product || ! $product->is_active) {
            throw ValidationException::withMessages([
                'product_id' => ['Selected product is unavailable.'],
            ]);
        }

        $errors = array_merge(
            $this->variantRule->validate($product, $variantId),
            $this->weightRule->validate($product, $weightOptionValueId),
            $this->extrasRule->validate($product, $extras),
        );

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    public function validateUpdateWeightOption(?int $productId, ?int $weightOptionValueId): void
    {
        if ($productId === null) {
            throw ValidationException::withMessages([
                'product_id' => ['Selected product is unavailable.'],
            ]);
        }

        $product = Product::with('weightOption.values')->find($productId);

        if (! $product) {
            throw ValidationException::withMessages([
                'product_id' => ['Selected product is unavailable.'],
            ]);
        }

        $errors = $this->weightRule->validate($product, $weightOptionValueId);

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }
}
