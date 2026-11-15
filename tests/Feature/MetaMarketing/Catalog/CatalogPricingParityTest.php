<?php

declare(strict_types=1);

use App\Core\Contracts\SettingsRepository;
use App\Enums\SettingKey;
use App\Plugins\CartEngine\src\Models\CartItem;
use App\Plugins\CartEngine\src\Services\Calculators\CartPriceCalculator;
use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\ProductVariant;
use App\Plugins\MetaMarketing\src\DTOs\MetaMarketingConfig;
use App\Plugins\MetaMarketing\src\Settings\MetaSettingKey;

beforeEach(function () {

    $settings = app(SettingsRepository::class);
    $settings->set(MetaSettingKey::CATALOG_ENABLED, true);
    $settings->set(MetaSettingKey::CATALOG_FEED_TOKEN, 'paritytoken123');
    $settings->set(MetaSettingKey::CATALOG_BRAND, 'Parity Brand');
    $settings->set(SettingKey::CURRENCY, 'EGP');
    app()->forgetInstance(MetaMarketingConfig::class);

    $this->category = Category::create([
        'name' => 'Parity Category',
        'is_active' => true,
    ]);

    $this->calculator = new CartPriceCalculator();
});

test('feed effective price strictly equals CartPriceCalculator getEffectivePrice across all pricing scenarios', function () {
    // 1. Simple product without discount
    $pNoDiscount = Product::create([
        'name' => 'Simple No Discount',
        'base_price' => 120.00,
        'price_after_discount' => null,
        'image' => 'products/p1.jpg',
        'category_id' => $this->category->id,
        'is_active' => true,
    ]);

    // 2. Simple product with discount
    $pDiscounted = Product::create([
        'name' => 'Simple Discounted',
        'base_price' => 200.00,
        'price_after_discount' => 170.00,
        'image' => 'products/p2.jpg',
        'category_id' => $this->category->id,
        'is_active' => true,
    ]);

    // 3. Simple product with inverted discount (discount >= base)
    $pInvertedDiscount = Product::create([
        'name' => 'Simple Inverted',
        'base_price' => 150.00,
        'price_after_discount' => 180.00,
        'image' => 'products/p3.jpg',
        'category_id' => $this->category->id,
        'is_active' => true,
    ]);

    // 4. Product with variants (some with price, some with null price)
    $pWithVariants = Product::create([
        'name' => 'Product With Variants',
        'base_price' => 250.00,
        'price_after_discount' => 210.00,
        'image' => 'products/p4.jpg',
        'category_id' => $this->category->id,
        'is_active' => true,
    ]);

    $vWithPrice = ProductVariant::create([
        'product_id' => $pWithVariants->id,
        'name' => 'Custom Price Variant',
        'price' => 310.00,
        'is_available' => true,
    ]);

    $vNullPrice = ProductVariant::create([
        'product_id' => $pWithVariants->id,
        'name' => 'Null Price Variant',
        'price' => null,
        'is_available' => true,
    ]);

    // Fetch the feed
    $response = $this->get('/meta/catalog/paritytoken123.csv');
    $response->assertOk();

    $lines = explode("\n", mb_trim(str_replace("\r\n", "\n", $response->getContent())));
    $rows = array_map('str_getcsv', $lines);
    $header = $rows[0];
    $feedRows = array_map(fn ($r) => array_combine($header, $r), array_slice($rows, 1));
    $indexedFeed = collect($feedRows)->keyBy('id');

    // Test cases matrix: [product, variant, rowId]
    $matrix = [
        [$pNoDiscount, null, (string) $pNoDiscount->id],
        [$pDiscounted, null, (string) $pDiscounted->id],
        [$pInvertedDiscount, null, (string) $pInvertedDiscount->id],
        [$pWithVariants, $vWithPrice, $pWithVariants->id.'-'.$vWithPrice->id],
        [$pWithVariants, $vNullPrice, $pWithVariants->id.'-'.$vNullPrice->id],
    ];

    foreach ($matrix as [$product, $variant, $rowId]) {
        $feedRow = $indexedFeed->get($rowId);
        expect($feedRow)->not->toBeNull();

        // Feed effective price is sale_price if present and not empty, otherwise price
        $priceString = ! empty($feedRow['sale_price']) ? $feedRow['sale_price'] : $feedRow['price'];
        $feedEffectivePrice = (float) explode(' ', $priceString)[0];

        // CartPriceCalculator effective price
        $cartItem = new CartItem();
        $cartItem->setRelation('product', $product);
        if ($variant !== null) {
            $cartItem->setRelation('variant', $variant);
        }
        $expectedEffectivePrice = $this->calculator->getEffectivePrice($cartItem);

        expect($feedEffectivePrice)->toBe($expectedEffectivePrice);
    }
});
