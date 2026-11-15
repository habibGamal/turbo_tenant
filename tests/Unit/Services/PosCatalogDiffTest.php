<?php

declare(strict_types=1);

use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\PosIntegration\src\Models\ProductPosMapping;
use App\Plugins\PosIntegration\src\Services\POS\Contracts\PosMasterClient;
use App\Plugins\PosIntegration\src\Services\PosCatalogDiff;

uses()->group('unit');

beforeEach(function () {
    $this->client = mock(PosMasterClient::class);
    $this->diff = new PosCatalogDiff($this->client);
});

describe('PosCatalogDiff findNewProducts', function () {
    it('finds products that do not exist locally', function () {
        $this->client->shouldReceive('getAllProductReferences')
            ->once()
            ->andReturn([
                [
                    'id' => 1,
                    'posRef' => 'PROD-001',
                    'name' => 'Pizza Margherita',
                    'type' => 'manufactured',
                    'prices' => [
                        ['id' => 1, 'price' => 100.0, 'priceAfterDiscount' => 90.0],
                    ],
                ],
                [
                    'id' => 2,
                    'posRef' => 'PROD-002',
                    'name' => 'Pepperoni Pizza',
                    'type' => 'manufactured',
                    'prices' => [
                        ['id' => 2, 'price' => 120.0, 'priceAfterDiscount' => 110.0],
                    ],
                ],
            ]);

        ProductPosMapping::factory()->create([
            'pos_item_id' => 'PROD-001',
        ]);

        $result = $this->diff->findNewProducts();

        expect($result)->toHaveCount(1)
            ->and($result->first()['posRef'])->toBe('PROD-002');
    });

    it('filters out non-manufactured products and allows consumable', function () {
        $this->client->shouldReceive('getAllProductReferences')
            ->once()
            ->andReturn([
                [
                    'id' => 1,
                    'posRef' => 'PROD-001',
                    'name' => 'Pizza Margherita',
                    'type' => 'manufactured',
                    'prices' => [],
                ],
                [
                    'id' => 2,
                    'posRef' => 'PROD-002',
                    'name' => 'Consumable Item',
                    'type' => 'consumable',
                    'prices' => [],
                ],
                [
                    'id' => 3,
                    'posRef' => 'PROD-003',
                    'name' => 'Some Service',
                    'type' => 'service',
                    'prices' => [],
                ],
            ]);

        $result = $this->diff->findNewProducts();

        expect($result)->toHaveCount(2)
            ->and($result->pluck('type')->toArray())->toBe(['manufactured', 'consumable']);
    });
});

describe('PosCatalogDiff findProductsWithPriceChanges', function () {
    it('finds products with price differences and handles prices[0] fallback', function () {
        $this->client->shouldReceive('getAllProductPrices')
            ->once()
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

        $result = $this->diff->findProductsWithPriceChanges();

        expect($result)->toHaveCount(1)
            ->and($result->first())->toHaveKeys(['localPrice', 'masterPrice'])
            ->and($result->first()['localPrice'])->toBe(100.0)
            ->and($result->first()['masterPrice'])->toBe(150.0);
    });
});
