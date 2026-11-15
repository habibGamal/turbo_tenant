<?php

declare(strict_types=1);

use App\Core\Contracts\SettingsRepository;
use App\Enums\SettingKey;
use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\ProductVariant;
use App\Plugins\MetaMarketing\src\DTOs\MetaMarketingConfig;
use App\Plugins\MetaMarketing\src\Settings\MetaSettingKey;

beforeEach(function () {

    $settings = app(SettingsRepository::class);
    $settings->set(MetaSettingKey::CATALOG_ENABLED, true);
    $settings->set(MetaSettingKey::CATALOG_FEED_TOKEN, 'cachetoken123');
    $settings->set(MetaSettingKey::CATALOG_BRAND, 'Cache Brand');
    $settings->set(SettingKey::CURRENCY, 'EGP');
    app()->forgetInstance(MetaMarketingConfig::class);

    $this->category = Category::create([
        'name' => 'Original Category',
        'is_active' => true,
    ]);
});

test('feed reflects a product update immediately', function () {
    $product = Product::create([
        'name' => 'Initial Name',
        'base_price' => 100.00,
        'image' => 'products/item.jpg',
        'category_id' => $this->category->id,
        'is_active' => true,
    ]);

    $firstResponse = $this->get('/meta/catalog/cachetoken123.csv');
    $firstResponse->assertOk();
    expect($firstResponse->getContent())->toContain('Initial Name');

    // Update the product name
    $product->update(['name' => 'Updated Name']);

    // The feed is generated per request, so the next fetch shows the change
    $secondResponse = $this->get('/meta/catalog/cachetoken123.csv');
    $secondResponse->assertOk();
    expect($secondResponse->getContent())->toContain('Updated Name')
        ->and($secondResponse->getContent())->not->toContain('Initial Name');
});

test('feed drops a deleted product immediately', function () {
    $product = Product::create([
        'name' => 'Will Be Deleted',
        'base_price' => 100.00,
        'image' => 'products/item.jpg',
        'category_id' => $this->category->id,
        'is_active' => true,
    ]);

    $resp1 = $this->get('/meta/catalog/cachetoken123.csv');
    expect($resp1->getContent())->toContain('Will Be Deleted');

    $product->delete();

    $resp2 = $this->get('/meta/catalog/cachetoken123.csv');
    expect($resp2->getContent())->not->toContain('Will Be Deleted');
});

test('feed reflects a variant update immediately', function () {
    $product = Product::create([
        'name' => 'Shoe',
        'base_price' => 100.00,
        'image' => 'products/shoe.jpg',
        'category_id' => $this->category->id,
        'is_active' => true,
    ]);

    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'name' => 'Red 42',
        'price' => 110.00,
        'is_available' => true,
    ]);

    $resp1 = $this->get('/meta/catalog/cachetoken123.csv');
    expect($resp1->getContent())->toContain('Shoe - Red 42');

    $variant->update(['name' => 'Blue 42']);

    $resp2 = $this->get('/meta/catalog/cachetoken123.csv');
    expect($resp2->getContent())->toContain('Shoe - Blue 42')
        ->and($resp2->getContent())->not->toContain('Shoe - Red 42');
});

test('feed reflects a category update immediately', function () {
    $product = Product::create([
        'name' => 'Salad',
        'base_price' => 50.00,
        'image' => 'products/salad.jpg',
        'category_id' => $this->category->id,
        'is_active' => true,
    ]);

    $resp1 = $this->get('/meta/catalog/cachetoken123.csv');
    expect($resp1->getContent())->toContain('Original Category');

    $this->category->update(['name' => 'Fresh Greens']);

    $resp2 = $this->get('/meta/catalog/cachetoken123.csv');
    expect($resp2->getContent())->toContain('Fresh Greens')
        ->and($resp2->getContent())->not->toContain('Original Category');
});
