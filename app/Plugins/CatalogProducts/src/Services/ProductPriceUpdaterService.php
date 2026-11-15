<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Services;

use App\Plugins\CatalogProducts\src\Contracts\ProductPriceUpdater;
use App\Plugins\CatalogProducts\src\Models\Product;
use Illuminate\Support\Facades\DB;

final class ProductPriceUpdaterService implements ProductPriceUpdater
{
    public function updatePrices(int $productId, float $basePrice, ?float $priceAfterDiscount): void
    {
        DB::transaction(function () use ($productId, $basePrice, $priceAfterDiscount) {
            $product = Product::query()->findOrFail($productId);

            $product->update([
                'base_price' => $basePrice,
                'price_after_discount' => $priceAfterDiscount ?? $basePrice,
            ]);
        });
    }
}
