<?php

declare(strict_types=1);

use App\Core\Contracts\SettingsRepository;
use App\Enums\SettingKey;
use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\ProductVariant;
use App\Plugins\MetaMarketing\src\Catalog\CatalogFeedValidator;
use App\Plugins\MetaMarketing\src\DTOs\MetaMarketingConfig;
use App\Plugins\MetaMarketing\src\Settings\MetaSettingKey;

beforeEach(function () {
    $settings = app(SettingsRepository::class);
    $settings->set(MetaSettingKey::CATALOG_ENABLED, true);
    $settings->set(MetaSettingKey::CATALOG_FEED_TOKEN, 'valtoken123');
    $settings->set(MetaSettingKey::CATALOG_BRAND, 'Validator Brand');
    $settings->set(SettingKey::CURRENCY, 'EGP');
    app()->forgetInstance(MetaMarketingConfig::class);

    $this->category = Category::create([
        'name' => 'Test Cat',
        'is_active' => true,
    ]);
});

test('validator reports missing image and increments rowsSkipped', function () {
    Product::create([
        'name' => 'No Image Simple',
        'base_price' => 50.00,
        'image' => '',
        'category_id' => $this->category->id,
        'is_active' => true,
    ]);

    $pVarNoImg = Product::create([
        'name' => 'No Image Var Product',
        'base_price' => 60.00,
        'image' => '',
        'category_id' => $this->category->id,
        'is_active' => true,
    ]);
    ProductVariant::create([
        'product_id' => $pVarNoImg->id,
        'name' => 'V1',
        'price' => 60.00,
        'is_available' => true,
    ]);
    ProductVariant::create([
        'product_id' => $pVarNoImg->id,
        'name' => 'V2',
        'price' => 70.00,
        'is_available' => true,
    ]);

    $validator = app(CatalogFeedValidator::class);
    $report = $validator->validate();

    expect($report->totalProducts)->toBe(2)
        ->and($report->totalRows)->toBe(0)
        ->and($report->rowsSkipped)->toBe(3); // 1 simple + 2 variants

    $imageIssues = $report->issuesForField('image');
    expect($imageIssues)->toHaveCount(2);
});

test('validator reports empty title and title over 200 chars', function () {
    $pEmptyTitle = Product::create([
        'name' => '   ',
        'base_price' => 50.00,
        'image' => 'products/p1.jpg',
        'category_id' => $this->category->id,
        'is_active' => true,
    ]);

    $longTitle = str_repeat('A', 205);
    $pLongTitle = Product::create([
        'name' => $longTitle,
        'base_price' => 50.00,
        'image' => 'products/p2.jpg',
        'category_id' => $this->category->id,
        'is_active' => true,
    ]);

    $validator = app(CatalogFeedValidator::class);
    $report = $validator->validate();

    $titleIssues = $report->issuesForField('title');
    expect($titleIssues)->toHaveCount(2);

    $emptyIssue = collect($titleIssues)->firstWhere('id', (string) $pEmptyTitle->id);
    expect($emptyIssue)->not->toBeNull()
        ->and($emptyIssue->problem)->toContain('empty');

    $longIssue = collect($titleIssues)->firstWhere('id', (string) $pLongTitle->id);
    expect($longIssue)->not->toBeNull()
        ->and($longIssue->problem)->toContain('200');
});

test('validator reports missing or zero price', function () {
    $pZero = Product::create([
        'name' => 'Free Simple',
        'base_price' => 0.00,
        'image' => 'products/zero.jpg',
        'category_id' => $this->category->id,
        'is_active' => true,
    ]);

    $pVar = Product::create([
        'name' => 'Var Item',
        'base_price' => 50.00,
        'image' => 'products/var.jpg',
        'category_id' => $this->category->id,
        'is_active' => true,
    ]);
    $vZero = ProductVariant::create([
        'product_id' => $pVar->id,
        'name' => 'Zero Variant',
        'price' => 0.00,
        'is_available' => true,
    ]);

    $validator = app(CatalogFeedValidator::class);
    $report = $validator->validate();

    $priceIssues = $report->issuesForField('price');
    expect($priceIssues)->toHaveCount(2);

    expect(collect($priceIssues)->pluck('id')->all())->toContain((string) $pZero->id, $pVar->id.'-'.$vZero->id);
});

test('validator flags a discount price above the base price but not an equal one', function () {
    $pBadSale = Product::create([
        'name' => 'Bad Sale Price',
        'base_price' => 100.00,
        'price_after_discount' => 120.00,
        'image' => 'products/sale.jpg',
        'category_id' => $this->category->id,
        'is_active' => true,
    ]);

    $pEqualSale = Product::create([
        'name' => 'Equal Sale Price',
        'base_price' => 100.00,
        'price_after_discount' => 100.00,
        'image' => 'products/equal.jpg',
        'category_id' => $this->category->id,
        'is_active' => true,
    ]);

    $validator = app(CatalogFeedValidator::class);
    $report = $validator->validate();

    // Equal discount = feed sends that price with no sale_price (decision 10.5), so it is not a problem.
    expect($report->issuesForField('sale_price'))->toBeEmpty();

    $discountIssues = $report->issuesForField('price_after_discount');
    expect($discountIssues)->toHaveCount(1)
        ->and($discountIssues[0]->id)->toBe((string) $pBadSale->id)
        ->and($report->issuesForField('price_after_discount'))->not->toContain(fn ($issue) => $issue->id === (string) $pEqualSale->id);
});

test('variant rows priced by the variant are never flagged for the product discount', function () {
    $product = Product::create([
        'name' => 'Coffee',
        'base_price' => 100.00,
        'price_after_discount' => 100.00,
        'image' => 'products/coffee.jpg',
        'category_id' => $this->category->id,
        'is_active' => true,
    ]);
    ProductVariant::create(['product_id' => $product->id, 'name' => 'Light', 'price' => 680.00, 'is_available' => true]);
    ProductVariant::create(['product_id' => $product->id, 'name' => 'Dark', 'price' => null, 'is_available' => true]);

    $report = app(CatalogFeedValidator::class)->validate();

    expect($report->issuesForField('sale_price'))->toBeEmpty()
        ->and($report->issuesForField('price_after_discount'))->toBeEmpty();
});

test('validator reports missing brand', function () {
    $settings = app(SettingsRepository::class);
    $settings->set(MetaSettingKey::CATALOG_BRAND, '');
    $settings->set(SettingKey::SITE_NAME, '');
    config(['app.name' => '']);
    app()->forgetInstance(MetaMarketingConfig::class);

    Product::create([
        'name' => 'Item Without Brand',
        'base_price' => 50.00,
        'image' => 'products/item.jpg',
        'category_id' => $this->category->id,
        'is_active' => true,
    ]);

    $validator = app(CatalogFeedValidator::class);
    $report = $validator->validate();

    $brandIssues = $report->issuesForField('brand');
    expect($brandIssues)->not->toBeEmpty();
});

test('validator previewRows returns at most limit rows formatted as feed rows', function () {
    for ($i = 1; $i <= 25; $i++) {
        Product::create([
            'name' => "Product {$i}",
            'base_price' => 10.00 * $i,
            'image' => "products/{$i}.jpg",
            'category_id' => $this->category->id,
            'is_active' => true,
        ]);
    }

    $validator = app(CatalogFeedValidator::class);
    $preview = $validator->previewRows(5);

    expect($preview)->toHaveCount(5);
    expect($preview[0])->toHaveKeys([
        'id', 'item_group_id', 'title', 'description', 'availability',
        'condition', 'price', 'sale_price', 'link', 'image_link',
        'brand', 'product_type', 'custom_label_0',
    ]);
});
