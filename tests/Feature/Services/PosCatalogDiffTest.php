<?php

declare(strict_types=1);

use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\PosIntegration\src\Models\ProductPosMapping;
use App\Plugins\PosIntegration\src\Services\PosCatalogDiff;
use App\Plugins\PosIntegration\src\Settings\PosSettingKey;
use App\Plugins\StoreSettings\src\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Setting::query()->updateOrCreate(
        ['key' => PosSettingKey::PRODUCTS_REPO_LINK->value],
        ['value' => 'https://master.example.com']
    );

    $this->diff = app(PosCatalogDiff::class);
});

describe('PosCatalogDiff Feature', function () {
    it('runs a bounded number of queries when diffing price changes', function () {
        $masterProducts = [];

        for ($i = 1; $i <= 10; $i++) {
            $product = Product::factory()->create([
                'base_price' => 100.0,
                'price_after_discount' => 90.0,
            ]);

            ProductPosMapping::factory()->create([
                'product_id' => $product->id,
                'pos_item_id' => "PROD-{$i}",
            ]);

            $masterProducts[] = [
                'id' => $i,
                'posRef' => "PROD-{$i}",
                'name' => "Product {$i}",
                'prices' => [
                    ['id' => $i, 'price' => 150.0, 'priceAfterDiscount' => 140.0],
                ],
            ];
        }

        Http::fake([
            'https://master.example.com/api/all-products-prices-master' => Http::response($masterProducts, 200),
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $changes = $this->diff->findProductsWithPriceChanges();

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        expect($changes)->toHaveCount(10)
            // Exactly 2 database queries: 1 for ProductPosMapping whereIn and 1 for Product eager load
            ->and($queryCount)->toBeLessThanOrEqual(2);
    });
});
