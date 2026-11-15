<?php

declare(strict_types=1);

use App\Core\Contracts\SettingsRepository;
use App\Enums\SettingKey;
use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\ProductVariant;
use App\Plugins\MetaMarketing\src\Catalog\CatalogCsvWriter;
use App\Plugins\MetaMarketing\src\DTOs\MetaMarketingConfig;
use App\Plugins\MetaMarketing\src\Settings\MetaSettingKey;

beforeEach(function () {

    $settings = app(SettingsRepository::class);
    $settings->set(MetaSettingKey::CATALOG_ENABLED, true);
    $settings->set(MetaSettingKey::CATALOG_FEED_TOKEN, 'testtoken456');
    $settings->set(MetaSettingKey::CATALOG_BRAND, 'Acme Store');
    $settings->set(SettingKey::CURRENCY, 'EGP');
    app()->forgetInstance(MetaMarketingConfig::class);

    $this->defaultCategory = Category::create([
        'name' => 'General',
        'is_active' => true,
    ]);
});

test('csv feed header matches exactly the required Meta field names', function () {
    $response = $this->get('/meta/catalog/testtoken456.csv');

    $response->assertOk();
    $csv = $response->getContent();
    $lines = explode("\n", mb_trim(str_replace("\r\n", "\n", $csv)));
    $header = str_getcsv($lines[0]);

    expect($header)->toBe(CatalogCsvWriter::HEADERS);
});

test('simple product row has correct fields and no item_group_id', function () {
    $product = Product::create([
        'name' => 'Classic Burger',
        'description' => '<p>Juicy <strong>beef</strong> burger with lettuce.</p>',
        'base_price' => 100.00,
        'image' => 'products/burger.jpg',
        'category_id' => $this->defaultCategory->id,
        'is_active' => true,
        'sell_by_weight' => false,
    ]);

    $response = $this->get('/meta/catalog/testtoken456.csv');
    $response->assertOk();

    $rows = array_map('str_getcsv', explode("\n", mb_trim(str_replace("\r\n", "\n", $response->getContent()))));
    $header = $rows[0];
    $dataRows = array_slice($rows, 1);

    expect($dataRows)->toHaveCount(1);
    $row = array_combine($header, $dataRows[0]);

    expect($row['id'])->toBe((string) $product->id)
        ->and($row['item_group_id'])->toBe('')
        ->and($row['title'])->toBe('Classic Burger')
        ->and($row['description'])->toBe('Juicy beef burger with lettuce.')
        ->and($row['availability'])->toBe('in stock')
        ->and($row['condition'])->toBe('new')
        ->and($row['price'])->toBe('100.00 EGP')
        ->and($row['sale_price'])->toBe('')
        ->and($row['link'])->toBe(url('/products/'.$product->id))
        ->and($row['image_link'])->toStartWith('http')
        ->and($row['image_link'])->toContain('products/burger.jpg')
        ->and($row['brand'])->toBe('Acme Store')
        ->and($row['product_type'])->toBe('General')
        ->and($row['custom_label_0'])->toBe('');
});

test('variant rows produce one row per variant with item_group_id and hyphenated ids', function () {
    $product = Product::create([
        'name' => 'T-Shirt',
        'description' => 'Cotton shirt',
        'base_price' => 200.00,
        'image' => 'products/tshirt.jpg',
        'category_id' => $this->defaultCategory->id,
        'is_active' => true,
        'sell_by_weight' => false,
    ]);

    $v1 = ProductVariant::create([
        'product_id' => $product->id,
        'name' => 'Small',
        'price' => 190.00,
        'is_available' => true,
    ]);

    $v2 = ProductVariant::create([
        'product_id' => $product->id,
        'name' => 'Large',
        'price' => 220.00,
        'is_available' => false,
    ]);

    $response = $this->get('/meta/catalog/testtoken456.csv');
    $response->assertOk();

    $rows = array_map('str_getcsv', explode("\n", mb_trim(str_replace("\r\n", "\n", $response->getContent()))));
    $header = $rows[0];
    $dataRows = array_slice($rows, 1);

    expect($dataRows)->toHaveCount(2);

    $r1 = array_combine($header, $dataRows[0]);
    expect($r1['id'])->toBe($product->id.'-'.$v1->id)
        ->and($r1['item_group_id'])->toBe((string) $product->id)
        ->and($r1['title'])->toBe('T-Shirt - Small')
        ->and($r1['price'])->toBe('190.00 EGP')
        ->and($r1['sale_price'])->toBe('')
        ->and($r1['availability'])->toBe('in stock');

    $r2 = array_combine($header, $dataRows[1]);
    expect($r2['id'])->toBe($product->id.'-'.$v2->id)
        ->and($r2['item_group_id'])->toBe((string) $product->id)
        ->and($r2['title'])->toBe('T-Shirt - Large')
        ->and($r2['price'])->toBe('220.00 EGP')
        ->and($r2['sale_price'])->toBe('')
        ->and($r2['availability'])->toBe('out of stock');
});

test('pricing rules follow website cart pricing across all cases', function () {
    // Case 1: product without variants, discount < base_price
    $pDiscount = Product::create([
        'name' => 'Discounted Item',
        'base_price' => 200.00,
        'price_after_discount' => 150.00,
        'image' => 'products/item1.jpg',
        'category_id' => $this->defaultCategory->id,
        'is_active' => true,
    ]);

    // Case 2: product without variants, discount >= base_price
    $pInvertedDiscount = Product::create([
        'name' => 'Inverted Discount Item',
        'base_price' => 200.00,
        'price_after_discount' => 250.00,
        'image' => 'products/item2.jpg',
        'category_id' => $this->defaultCategory->id,
        'is_active' => true,
    ]);

    // Case 3: product with variants where variant price is null (falls back to product rule)
    $pVarNullPrice = Product::create([
        'name' => 'Var Null Price Item',
        'base_price' => 300.00,
        'price_after_discount' => 260.00,
        'image' => 'products/item3.jpg',
        'category_id' => $this->defaultCategory->id,
        'is_active' => true,
    ]);
    $vNull = ProductVariant::create([
        'product_id' => $pVarNullPrice->id,
        'name' => 'Standard',
        'price' => null,
        'is_available' => true,
    ]);

    $response = $this->get('/meta/catalog/testtoken456.csv');
    $rows = array_map('str_getcsv', explode("\n", mb_trim(str_replace("\r\n", "\n", $response->getContent()))));
    $header = $rows[0];
    $dataRows = array_map(fn ($r) => array_combine($header, $r), array_slice($rows, 1));
    $indexed = collect($dataRows)->keyBy('id');

    // Case 1: price = base, sale_price = price_after_discount
    $row1 = $indexed->get((string) $pDiscount->id);
    expect($row1['price'])->toBe('200.00 EGP')
        ->and($row1['sale_price'])->toBe('150.00 EGP');

    // Case 2: price = price_after_discount, no sale_price
    $row2 = $indexed->get((string) $pInvertedDiscount->id);
    expect($row2['price'])->toBe('250.00 EGP')
        ->and($row2['sale_price'])->toBe('');

    // Case 3: variant with null price falls back to product rule (discount < base)
    $row3 = $indexed->get($pVarNullPrice->id.'-'.$vNull->id);
    expect($row3['price'])->toBe('300.00 EGP')
        ->and($row3['sale_price'])->toBe('260.00 EGP');
});

test('availability mapping correctly includes inactive products as out of stock', function () {
    $inactive = Product::create([
        'name' => 'Archived Product',
        'base_price' => 50.00,
        'image' => 'products/archived.jpg',
        'category_id' => $this->defaultCategory->id,
        'is_active' => false,
    ]);

    $inactiveWithVar = Product::create([
        'name' => 'Archived With Variant',
        'base_price' => 80.00,
        'image' => 'products/var.jpg',
        'category_id' => $this->defaultCategory->id,
        'is_active' => false,
    ]);
    ProductVariant::create([
        'product_id' => $inactiveWithVar->id,
        'name' => 'Var 1',
        'price' => 80.00,
        'is_available' => true,
    ]);

    $response = $this->get('/meta/catalog/testtoken456.csv');
    $rows = array_map('str_getcsv', explode("\n", mb_trim(str_replace("\r\n", "\n", $response->getContent()))));
    $header = $rows[0];
    $dataRows = array_map(fn ($r) => array_combine($header, $r), array_slice($rows, 1));
    $indexed = collect($dataRows)->keyBy('id');

    expect($indexed->get((string) $inactive->id)['availability'])->toBe('out of stock');
    expect($indexed->first(fn ($r) => str_starts_with($r['id'], $inactiveWithVar->id.'-'))['availability'])->toBe('out of stock');
});

test('product with no image is skipped from the feed', function () {
    Product::create([
        'name' => 'Invisible Product',
        'base_price' => 50.00,
        'image' => '',
        'category_id' => $this->defaultCategory->id,
        'is_active' => true,
    ]);

    $visible = Product::create([
        'name' => 'Visible Product',
        'base_price' => 50.00,
        'image' => 'products/visible.jpg',
        'category_id' => $this->defaultCategory->id,
        'is_active' => true,
    ]);

    $response = $this->get('/meta/catalog/testtoken456.csv');
    $csv = $response->getContent();

    expect($csv)->toContain('Visible Product')
        ->and($csv)->not->toContain('Invisible Product');
});

test('sell_by_weight sets custom_label_0 to by_weight', function () {
    Product::create([
        'name' => 'Apples',
        'base_price' => 45.00,
        'image' => 'products/apples.jpg',
        'category_id' => $this->defaultCategory->id,
        'is_active' => true,
        'sell_by_weight' => true,
    ]);

    $response = $this->get('/meta/catalog/testtoken456.csv');
    $rows = array_map('str_getcsv', explode("\n", mb_trim(str_replace("\r\n", "\n", $response->getContent()))));
    $header = $rows[0];
    $dataRows = array_map(fn ($r) => array_combine($header, $r), array_slice($rows, 1));

    expect($dataRows[0]['custom_label_0'])->toBe('by_weight');
});

test('description falls back to title if empty or stripped to empty', function () {
    Product::create([
        'name' => 'No Description Item',
        'description' => '   <p>   </p>  ',
        'base_price' => 45.00,
        'image' => 'products/test.jpg',
        'category_id' => $this->defaultCategory->id,
        'is_active' => true,
    ]);

    $response = $this->get('/meta/catalog/testtoken456.csv');
    $rows = array_map('str_getcsv', explode("\n", mb_trim(str_replace("\r\n", "\n", $response->getContent()))));
    $header = $rows[0];
    $dataRows = array_map(fn ($r) => array_combine($header, $r), array_slice($rows, 1));

    expect($dataRows[0]['description'])->toBe('No Description Item');
});

test('xml feed is valid RSS 2.0 and produces identical item ids to CSV', function () {
    $p1 = Product::create([
        'name' => 'Product 1',
        'base_price' => 100.00,
        'image' => 'products/p1.jpg',
        'category_id' => $this->defaultCategory->id,
        'is_active' => true,
    ]);

    $p2 = Product::create([
        'name' => 'Product 2',
        'base_price' => 150.00,
        'image' => 'products/p2.jpg',
        'category_id' => $this->defaultCategory->id,
        'is_active' => true,
    ]);
    $v1 = ProductVariant::create([
        'product_id' => $p2->id,
        'name' => 'Option A',
        'price' => 160.00,
        'is_available' => true,
    ]);

    $csvResp = $this->get('/meta/catalog/testtoken456.csv');
    $csvRows = array_map('str_getcsv', explode("\n", mb_trim(str_replace("\r\n", "\n", $csvResp->getContent()))));
    $csvIds = collect(array_slice($csvRows, 1))->map(fn ($r) => $r[0])->sort()->values()->all();

    $xmlResp = $this->get('/meta/catalog/testtoken456.xml');
    $xmlResp->assertOk();
    $xmlContent = $xmlResp->getContent();

    $xml = simplexml_load_string($xmlContent);
    expect($xml)->not->toBeFalse();
    expect((string) $xml->channel->title)->toBe('Acme Store');

    $xmlIds = [];
    foreach ($xml->channel->item as $item) {
        $g = $item->children('http://base.google.com/ns/1.0');
        $xmlIds[] = (string) $g->id;
    }
    sort($xmlIds);

    expect($xmlIds)->toBe($csvIds);
});

test('arabic override feed contains id, override, title for products with arabic names', function () {
    Product::create([
        'name' => 'Burger',
        'name_ar' => 'برجر كلاسيك',
        'base_price' => 120.00,
        'image' => 'products/burger.jpg',
        'category_id' => $this->defaultCategory->id,
        'is_active' => true,
    ]);

    $pWithVars = Product::create([
        'name' => 'Pizza',
        'name_ar' => 'بيتزا',
        'base_price' => 150.00,
        'image' => 'products/pizza.jpg',
        'category_id' => $this->defaultCategory->id,
        'is_active' => true,
    ]);
    ProductVariant::create([
        'product_id' => $pWithVars->id,
        'name' => 'Small',
        'name_ar' => 'صغير',
        'price' => 130.00,
        'is_available' => true,
    ]);
    ProductVariant::create([
        'product_id' => $pWithVars->id,
        'name' => 'Large',
        'name_ar' => null, // falls back to english variant name
        'price' => 180.00,
        'is_available' => true,
    ]);

    // Product with no Arabic name: must be omitted
    Product::create([
        'name' => 'Fries',
        'name_ar' => null,
        'base_price' => 40.00,
        'image' => 'products/fries.jpg',
        'category_id' => $this->defaultCategory->id,
        'is_active' => true,
    ]);

    $response = $this->get('/meta/catalog/testtoken456.ar.csv');
    $response->assertOk();

    $lines = explode("\n", mb_trim(str_replace("\r\n", "\n", $response->getContent())));
    $rows = array_map('str_getcsv', $lines);
    $header = $rows[0];

    expect($header)->toBe(['id', 'override', 'title']);

    $data = array_map(fn ($r) => array_combine($header, $r), array_slice($rows, 1));
    expect($data)->toHaveCount(3);

    expect($data[0]['override'])->toBe('ar_AR')
        ->and($data[0]['title'])->toBe('برجر كلاسيك');

    expect($data[1]['override'])->toBe('ar_AR')
        ->and($data[1]['title'])->toBe('بيتزا - صغير');

    expect($data[2]['override'])->toBe('ar_AR')
        ->and($data[2]['title'])->toBe('بيتزا - Large');
});
