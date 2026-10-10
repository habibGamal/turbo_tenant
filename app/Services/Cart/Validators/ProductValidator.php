<?php

declare(strict_types=1);

namespace App\Services\Cart\Validators;

use App\Models\Product;
use App\Services\Cart\Contracts\ProductValidatorInterface;
use Illuminate\Validation\ValidationException;

final class ProductValidator implements ProductValidatorInterface
{
    public function ensureProductIsActive(int $productId): void
    {
        $isActive = Product::query()
            ->whereKey($productId)
            ->where('is_active', true)
            ->exists();

        if (! $isActive) {
            throw ValidationException::withMessages([
                'product_id' => ['Selected product is unavailable.'],
            ]);
        }
    }
}
