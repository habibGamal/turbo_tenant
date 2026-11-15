<?php

declare(strict_types=1);

use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\PosIntegration\src\Models\ProductPosMapping;
use App\Plugins\PosIntegration\src\Services\PosProductImporter;
use App\Plugins\PosIntegration\src\Settings\PosSettingKey;
use App\Plugins\StoreSettings\src\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Setting::query()->updateOrCreate(
        ['key' => PosSettingKey::PRODUCTS_REPO_LINK->value],
        ['value' => 'https://master.example.com']
    );

    $this->importer = app(PosProductImporter::class);
});

describe('PosProductImporter Feature', function () {
    it('rolls back the imported product when mapping write fails', function () {
        Http::fake([
            'https://master.example.com/api/get-products-master?ids=,999' => Http::response([
                [
                    'id' => 999,
                    'posRef' => 'FAIL-REF-999',
                    'name' => 'Rollback Pizza',
                    'price' => 150.0,
                    'priceAfterDiscount' => 130.0,
                    'category' => [
                        'id' => 1,
                        'name' => 'Pizza',
                        'image' => null,
                    ],
                ],
            ], 200),
        ]);

        ProductPosMapping::saving(function ($mapping) {
            if ($mapping->pos_item_id === 'FAIL-REF-999') {
                throw new RuntimeException('Simulated database write failure for mapping');
            }
        });

        $result = $this->importer->importProducts([999]);

        expect($result->failedCount)->toBe(1)
            ->and($result->count)->toBe(0)
            ->and($result->errors)->toHaveCount(1);

        // Crucial check: the product imported by ProductImporter inside the transaction was rolled back
        expect(Product::query()->where('name', 'Rollback Pizza')->exists())->toBeFalse()
            ->and(ProductPosMapping::query()->where('pos_item_id', 'FAIL-REF-999')->exists())->toBeFalse();
    });

    it('successfully imports and persists product and mapping inside transaction', function () {
        Http::fake([
            'https://master.example.com/api/get-products-master?ids=,100' => Http::response([
                [
                    'id' => 100,
                    'posRef' => 'SUCCESS-REF-100',
                    'name' => 'Success Burger',
                    'price' => 80.0,
                    'priceAfterDiscount' => 70.0,
                    'category' => [
                        'id' => 2,
                        'name' => 'Burgers',
                        'image' => null,
                    ],
                ],
            ], 200),
        ]);

        $result = $this->importer->importProducts([100]);

        expect($result->count)->toBe(1)
            ->and($result->failedCount)->toBe(0);

        expect(Product::query()->where('name', 'Success Burger')->exists())->toBeTrue()
            ->and(ProductPosMapping::query()->where('pos_item_id', 'SUCCESS-REF-100')->exists())->toBeTrue();
    });
});
