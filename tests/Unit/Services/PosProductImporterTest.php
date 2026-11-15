<?php

declare(strict_types=1);

use App\Plugins\CatalogProducts\src\Contracts\ProductImporter;
use App\Plugins\CatalogProducts\src\Contracts\ProductPriceUpdater;
use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\PosIntegration\src\DTOs\PosImportResult;
use App\Plugins\PosIntegration\src\Models\ProductPosMapping;
use App\Plugins\PosIntegration\src\Services\POS\Contracts\PosMasterClient;
use App\Plugins\PosIntegration\src\Services\PosProductImporter;

uses()->group('unit');

beforeEach(function () {
    $this->client = mock(PosMasterClient::class);
    $this->productImporter = app(ProductImporter::class);
    $this->productPriceUpdater = app(ProductPriceUpdater::class);
    $this->importer = new PosProductImporter(
        $this->client,
        $this->productImporter,
        $this->productPriceUpdater
    );
});

describe('PosProductImporter importProducts', function () {
    it('imports products successfully returning PosImportResult', function () {
        $this->client->shouldReceive('getProductsByIds')
            ->once()
            ->with([1])
            ->andReturn([
                [
                    'id' => 1,
                    'posRef' => 'PROD-001',
                    'name' => 'Pizza Margherita',
                    'price' => 100.0,
                    'priceAfterDiscount' => 90.0,
                    'category' => [
                        'id' => 1,
                        'name' => 'Pizza',
                        'image' => 'pizza.jpg',
                    ],
                ],
            ]);

        $result = $this->importer->importProducts([1]);

        expect($result)->toBeInstanceOf(PosImportResult::class)
            ->and($result->count)->toBe(1)
            ->and($result->failedCount)->toBe(0)
            ->and($result->errors)->toBeEmpty();

        $this->assertDatabaseHas('products', [
            'name' => 'Pizza Margherita',
            'base_price' => 100.0,
            'price_after_discount' => 90.0,
        ]);

        $this->assertDatabaseHas('categories', [
            'name' => 'Pizza',
        ]);

        $this->assertDatabaseHas('product_pos_mappings', [
            'pos_item_id' => 'PROD-001',
        ]);
    });

    it('creates category if it does not exist', function () {
        $this->client->shouldReceive('getProductsByIds')
            ->once()
            ->with([1])
            ->andReturn([
                [
                    'id' => 1,
                    'posRef' => 'PROD-001',
                    'name' => 'Pizza Margherita',
                    'price' => 100.0,
                    'priceAfterDiscount' => 90.0,
                    'category' => [
                        'id' => 1,
                        'name' => 'Pizza',
                        'image' => 'pizza.jpg',
                    ],
                ],
            ]);

        expect(Category::count())->toBe(0);

        $this->importer->importProducts([1]);

        expect(Category::count())->toBe(1);
    });

    it('reuses existing category', function () {
        $this->client->shouldReceive('getProductsByIds')
            ->once()
            ->with([1, 2])
            ->andReturn([
                [
                    'id' => 1,
                    'posRef' => 'PROD-001',
                    'name' => 'Pizza Margherita',
                    'price' => 100.0,
                    'priceAfterDiscount' => 90.0,
                    'category' => [
                        'id' => 1,
                        'name' => 'Pizza',
                        'image' => 'pizza.jpg',
                    ],
                ],
                [
                    'id' => 2,
                    'posRef' => 'PROD-002',
                    'name' => 'Pepperoni Pizza',
                    'price' => 120.0,
                    'priceAfterDiscount' => 110.0,
                    'category' => [
                        'id' => 1,
                        'name' => 'Pizza',
                        'image' => 'pizza.jpg',
                    ],
                ],
            ]);

        $this->importer->importProducts([1, 2]);

        expect(Category::count())->toBe(1);
    });
});

describe('PosProductImporter updateProductPrices', function () {
    it('updates product prices successfully returning PosImportResult', function () {
        $this->client->shouldReceive('getProductPricesByIds')
            ->once()
            ->with([1])
            ->andReturn([
                [
                    'id' => 1,
                    'posRef' => 'PROD-001',
                    'name' => 'Pizza Margherita',
                    'prices' => [
                        ['id' => 1, 'price' => 150.0, 'priceAfterDiscount' => 140.0],
                    ],
                ],
            ]);

        $product = Product::factory()->create([
            'base_price' => 100.0,
            'price_after_discount' => 90.0,
        ]);

        ProductPosMapping::factory()->create([
            'product_id' => $product->id,
            'pos_item_id' => 'PROD-001',
        ]);

        $result = $this->importer->updateProductPrices([1]);

        expect($result)->toBeInstanceOf(PosImportResult::class)
            ->and($result->count)->toBe(1)
            ->and($result->failedCount)->toBe(0)
            ->and($result->errors)->toBeEmpty();

        $product->refresh();

        expect((float) $product->base_price)->toBe(150.0)
            ->and((float) $product->price_after_discount)->toBe(140.0);
    });

    it('handles missing product mappings', function () {
        $this->client->shouldReceive('getProductPricesByIds')
            ->once()
            ->with([1])
            ->andReturn([
                [
                    'id' => 1,
                    'posRef' => 'PROD-MISSING',
                    'name' => 'Missing Product',
                    'prices' => [
                        ['id' => 1, 'price' => 150.0, 'priceAfterDiscount' => 140.0],
                    ],
                ],
            ]);

        $result = $this->importer->updateProductPrices([1]);

        expect($result->count)->toBe(0)
            ->and($result->failedCount)->toBe(1)
            ->and($result->errors)->toHaveCount(1);
    });
});
