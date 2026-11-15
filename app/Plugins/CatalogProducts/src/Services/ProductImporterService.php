<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Services;

use App\Plugins\CatalogProducts\src\Contracts\ImportedProduct;
use App\Plugins\CatalogProducts\src\Contracts\ProductImporter;
use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\Product;
use Illuminate\Support\Facades\DB;

final class ProductImporterService implements ProductImporter
{
    public function importProduct(ImportedProduct $product): Product
    {
        return DB::transaction(function () use ($product) {
            $category = Category::query()->firstOrCreate(
                ['name' => $product->categoryName],
                [
                    'description' => null,
                    'image' => $product->categoryImage,
                    'is_active' => true,
                ]
            );

            return Product::query()->create([
                'name' => $product->name,
                'description' => null,
                'image' => $product->image ?? '/images/default-product.png',
                'base_price' => $product->basePrice,
                'price_after_discount' => $product->priceAfterDiscount ?? $product->basePrice,
                'category_id' => $category->id,
                'is_active' => true,
                'sell_by_weight' => false,
            ]);
        });
    }
}
