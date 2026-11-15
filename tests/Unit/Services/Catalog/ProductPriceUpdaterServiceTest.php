<?php

declare(strict_types=1);

use App\Plugins\CatalogProducts\src\Contracts\ProductPriceUpdater;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Services\ProductPriceUpdaterService;

uses()->group('unit');

beforeEach(function () {
    $this->updater = new ProductPriceUpdaterService();
});

describe('ProductPriceUpdaterService', function () {
    it('implements ProductPriceUpdater interface', function () {
        expect($this->updater)->toBeInstanceOf(ProductPriceUpdater::class);
    });

    it('updates product base price and discounted price', function () {
        $product = Product::factory()->create([
            'base_price' => 100.0,
            'price_after_discount' => 90.0,
        ]);

        $this->updater->updatePrices($product->id, 150.0, 130.0);

        $product->refresh();
        expect((float) $product->base_price)->toBe(150.0)
            ->and((float) $product->price_after_discount)->toBe(130.0);
    });

    it('falls back to base price when price after discount is null', function () {
        $product = Product::factory()->create([
            'base_price' => 100.0,
            'price_after_discount' => 90.0,
        ]);

        $this->updater->updatePrices($product->id, 160.0, null);

        $product->refresh();
        expect((float) $product->base_price)->toBe(160.0)
            ->and((float) $product->price_after_discount)->toBe(160.0);
    });
});
