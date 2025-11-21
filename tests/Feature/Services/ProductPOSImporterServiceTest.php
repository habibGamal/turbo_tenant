<?php

declare(strict_types=1);

use App\Enums\SettingKey;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPosMapping;
use App\Models\Setting;
use App\Services\ProductPOSImporterService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->settingService = app(SettingService::class);
    $this->service = new ProductPOSImporterService($this->settingService);

    // Set the products repo link for tests
    Setting::query()->updateOrCreate(
        ['key' => SettingKey::PRODUCTS_REPO_LINK->value],
        ['value' => 'https://master.example.com']
    );
});

describe('getProductsByIds', function () {
    it('retrieves products by IDs from master repository', function () {
        Http::fake([
            'https://master.example.com/api/get-products-master?ids=,1,2' => Http::response([
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
            ], 200),
        ]);

        $result = $this->service->getProductsByIds([1, 2]);

        expect($result)->toBeArray()
            ->toHaveCount(2)
            ->and($result[0])->toHaveKeys(['id', 'posRef', 'name', 'price', 'priceAfterDiscount', 'category'])
            ->and($result[0]['name'])->toBe('Pizza Margherita');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://master.example.com/api/get-products-master?ids=,1,2';
        });
    });

    it('throws exception when master repository URL is not configured', function () {
        Setting::query()->where('key', SettingKey::PRODUCTS_REPO_LINK->value)->delete();

        expect(fn () => $this->service->getProductsByIds([1, 2]))
            ->toThrow(Exception::class, 'لا يمكن الاتصال بالنقطة الرئيسية');
    });

    it('throws exception when API request fails', function () {
        Http::fake([
            'https://master.example.com/api/get-products-master?ids=,1,2' => Http::response([], 500),
        ]);

        expect(fn () => $this->service->getProductsByIds([1, 2]))
            ->toThrow(Exception::class, 'لا يمكن الاتصال بالنقطة الرئيسية');
    });
});

describe('getAllProductReferences', function () {
    it('retrieves all product references from master repository', function () {
        Http::fake([
            'https://master.example.com/api/all-products-refs-master' => Http::response([
                [
                    'id' => 1,
                    'posRef' => 'PROD-001',
                    'name' => 'Pizza Margherita',
                    'type' => 'manufactured',
                    'prices' => [
                        ['id' => 1, 'price' => 100.0, 'priceAfterDiscount' => 90.0],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->service->getAllProductReferences();

        expect($result)->toBeArray()
            ->toHaveCount(1)
            ->and($result[0])->toHaveKeys(['id', 'posRef', 'name', 'type', 'prices']);
    });
});

describe('getAllProductPrices', function () {
    it('retrieves all product prices from master repository', function () {
        Http::fake([
            'https://master.example.com/api/all-products-prices-master' => Http::response([
                [
                    'id' => 1,
                    'posRef' => 'PROD-001',
                    'name' => 'Pizza Margherita',
                    'prices' => [
                        ['id' => 1, 'price' => 100.0, 'priceAfterDiscount' => 90.0],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->service->getAllProductPrices();

        expect($result)->toBeArray()
            ->toHaveCount(1)
            ->and($result[0])->toHaveKeys(['id', 'posRef', 'name', 'prices']);
    });
});

describe('getProductPricesByIds', function () {
    it('retrieves product prices by IDs from master repository', function () {
        Http::fake([
            'https://master.example.com/api/get-products-prices-master?ids=,1,2' => Http::response([
                [
                    'id' => 1,
                    'posRef' => 'PROD-001',
                    'name' => 'Pizza Margherita',
                    'prices' => [
                        ['id' => 1, 'price' => 100.0, 'priceAfterDiscount' => 90.0],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->service->getProductPricesByIds([1, 2]);

        expect($result)->toBeArray()
            ->toHaveCount(1);
    });
});

describe('getProductsByReferences', function () {
    it('retrieves products by references from master repository', function () {
        Http::fake([
            'https://master.example.com/api/get-products-master-by-refs?refs=,PROD-001,PROD-002' => Http::response([
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
            ], 200),
        ]);

        $result = $this->service->getProductsByReferences(['PROD-001', 'PROD-002']);

        expect($result)->toBeArray()
            ->toHaveCount(1)
            ->and($result[0]['posRef'])->toBe('PROD-001');
    });
});

describe('findNewProducts', function () {
    it('finds products that do not exist locally', function () {
        Http::fake([
            'https://master.example.com/api/all-products-refs-master' => Http::response([
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
            ], 200),
        ]);

        // Create one existing product mapping
        ProductPosMapping::factory()->create([
            'pos_item_id' => 'PROD-001',
        ]);

        $result = $this->service->findNewProducts();

        expect($result)->toHaveCount(1)
            ->and($result->first()['posRef'])->toBe('PROD-002');
    });

    it('filters out non-manufactured products', function () {
        Http::fake([
            'https://master.example.com/api/all-products-refs-master' => Http::response([
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
                    'name' => 'Some Service',
                    'type' => 'service',
                    'prices' => [],
                ],
            ], 200),
        ]);

        $result = $this->service->findNewProducts();

        expect($result)->toHaveCount(1)
            ->and($result->first()['type'])->toBe('manufactured');
    });
});

describe('findProductsWithPriceChanges', function () {
    it('finds products with price differences', function () {
        Http::fake([
            'https://master.example.com/api/all-products-prices-master' => Http::response([
                [
                    'id' => 1,
                    'posRef' => 'PROD-001',
                    'name' => 'Pizza Margherita',
                    'prices' => [
                        ['id' => 1, 'price' => 150.0, 'priceAfterDiscount' => 140.0],
                    ],
                ],
            ], 200),
        ]);

        $product = Product::factory()->create([
            'base_price' => 100.0,
            'price_after_discount' => 90.0,
        ]);

        ProductPosMapping::factory()->create([
            'product_id' => $product->id,
            'pos_item_id' => 'PROD-001',
        ]);

        $result = $this->service->findProductsWithPriceChanges();

        expect($result)->toHaveCount(1)
            ->and($result->first())->toHaveKeys(['localPrice', 'masterPrice'])
            ->and($result->first()['localPrice'])->toBe(100.0)
            ->and($result->first()['masterPrice'])->toBe(150.0);
    });
});

describe('importProducts', function () {
    it('imports products successfully', function () {
        Http::fake([
            'https://master.example.com/api/get-products-master?ids=,1' => Http::response([
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
            ], 200),
        ]);

        $result = $this->service->importProducts([1]);

        expect($result)->toHaveKeys(['imported', 'failed', 'errors'])
            ->and($result['imported'])->toBe(1)
            ->and($result['failed'])->toBe(0);

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
        Http::fake([
            'https://master.example.com/api/get-products-master?ids=,1' => Http::response([
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
            ], 200),
        ]);

        expect(Category::count())->toBe(0);

        $this->service->importProducts([1]);

        expect(Category::count())->toBe(1);
    });

    it('reuses existing category', function () {
        Http::fake([
            'https://master.example.com/api/get-products-master?ids=,1,2' => Http::response([
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
            ], 200),
        ]);

        $this->service->importProducts([1, 2]);

        expect(Category::count())->toBe(1);
    });
});

describe('updateProductPrices', function () {
    it('updates product prices successfully', function () {
        Http::fake([
            'https://master.example.com/api/get-products-prices-master?ids=,1' => Http::response([
                [
                    'id' => 1,
                    'posRef' => 'PROD-001',
                    'name' => 'Pizza Margherita',
                    'prices' => [
                        ['id' => 1, 'price' => 150.0, 'priceAfterDiscount' => 140.0],
                    ],
                ],
            ], 200),
        ]);

        $product = Product::factory()->create([
            'base_price' => 100.0,
            'price_after_discount' => 90.0,
        ]);

        ProductPosMapping::factory()->create([
            'product_id' => $product->id,
            'pos_item_id' => 'PROD-001',
        ]);

        $result = $this->service->updateProductPrices([1]);

        expect($result)->toHaveKeys(['updated', 'failed', 'errors'])
            ->and($result['updated'])->toBe(1)
            ->and($result['failed'])->toBe(0);

        $product->refresh();

        expect($product->base_price)->toBe(150.0)
            ->and($product->price_after_discount)->toBe(140.0);
    });

    it('handles missing product mappings', function () {
        Http::fake([
            'https://master.example.com/api/get-products-prices-master?ids=,1' => Http::response([
                [
                    'id' => 1,
                    'posRef' => 'PROD-MISSING',
                    'name' => 'Missing Product',
                    'prices' => [
                        ['id' => 1, 'price' => 150.0, 'priceAfterDiscount' => 140.0],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->service->updateProductPrices([1]);

        expect($result['updated'])->toBe(0)
            ->and($result['failed'])->toBe(1)
            ->and($result['errors'])->toHaveCount(1);
    });
});
