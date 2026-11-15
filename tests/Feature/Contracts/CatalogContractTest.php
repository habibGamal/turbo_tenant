<?php

declare(strict_types=1);

use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\ExtraOption;
use App\Plugins\CatalogProducts\src\Models\ExtraOptionItem;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\ProductVariant;
use App\Plugins\CatalogProducts\src\Models\Section;
use App\Plugins\CatalogProducts\src\Models\WeightOption;
use App\Plugins\CatalogProducts\src\Models\WeightOptionValue;

it('locks JSON shape for catalog products index endpoint', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create([
        'category_id' => $category->id,
        'is_active' => true,
        'base_price' => 120.00,
        'price_after_discount' => 100.00,
    ]);
    ProductVariant::factory()->create([
        'product_id' => $product->id,
        'is_available' => true,
    ]);

    $response = $this->getJson(route('api.catalog-products.index'));

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'status',
        'data' => [
            '*' => [
                'id',
                'name',
                'name_ar',
                'description',
                'image',
                'base_price',
                'price_after_discount',
                'price',
                'is_active',
                'sell_by_weight',
                'has_variants',
                'has_extras',
                'has_options',
                'category' => [
                    'id',
                    'name',
                    'name_ar',
                ],
            ],
        ],
        'meta' => [
            'current_page',
            'last_page',
            'per_page',
            'total',
        ],
    ]);

    expect($response->json('status'))->toBeString();
    $firstItem = $response->json('data.0');
    expect($firstItem['id'])->toBeInt()
        ->and($firstItem['name'])->toBeString()
        ->and($firstItem['base_price'])->toBeNumeric()
        ->and($firstItem['price'])->toBeNumeric()
        ->and($firstItem['is_active'])->toBeBool()
        ->and($firstItem['sell_by_weight'])->toBeBool()
        ->and($firstItem['has_variants'])->toBeBool()
        ->and($firstItem['has_extras'])->toBeBool()
        ->and($firstItem['has_options'])->toBeBool()
        ->and($firstItem['category']['id'])->toBeInt()
        ->and($firstItem['category']['name'])->toBeString()
        ->and($response->json('meta.current_page'))->toBeInt()
        ->and($response->json('meta.last_page'))->toBeInt()
        ->and($response->json('meta.per_page'))->toBeInt()
        ->and($response->json('meta.total'))->toBeInt();
});

it('locks JSON shape for catalog product show endpoint', function () {
    $category = Category::factory()->create();
    $extraOption = ExtraOption::factory()->create();
    ExtraOptionItem::factory()->create([
        'extra_option_id' => $extraOption->id,
        'price' => 15.00,
    ]);
    $weightOption = WeightOption::factory()->create();
    WeightOptionValue::factory()->create([
        'weight_option_id' => $weightOption->id,
    ]);

    $product = Product::factory()->create([
        'category_id' => $category->id,
        'extra_option_id' => $extraOption->id,
        'weight_options_id' => $weightOption->id,
        'is_active' => true,
        'base_price' => 150.00,
        'price_after_discount' => 130.00,
    ]);

    ProductVariant::factory()->create([
        'product_id' => $product->id,
        'is_available' => true,
        'price' => 140.00,
    ]);

    $response = $this->getJson(route('api.catalog-products.show', $product->id));

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'status',
        'data' => [
            'id',
            'name',
            'name_ar',
            'description',
            'image',
            'base_price',
            'price_after_discount',
            'price',
            'is_active',
            'sell_by_weight',
            'category' => [
                'id',
                'name',
                'name_ar',
                'description',
            ],
            'variants' => [
                '*' => [
                    'id',
                    'name',
                    'name_ar',
                    'price',
                    'is_available',
                    'sort_order',
                ],
            ],
            'extra_option' => [
                'id',
                'name',
                'name_ar',
                'description',
                'min_selections',
                'max_selections',
                'allow_multiple',
                'items' => [
                    '*' => [
                        'id',
                        'name',
                        'name_ar',
                        'price',
                        'is_default',
                        'sort_order',
                        'allow_quantity',
                    ],
                ],
            ],
            'weight_option' => [
                'id',
                'name',
                'unit',
                'values' => [
                    '*' => [
                        'id',
                        'value',
                        'label',
                        'sort_order',
                    ],
                ],
            ],
            'reviews_count',
            'related_products',
            'promotional_products',
        ],
    ]);

    $data = $response->json('data');
    expect($response->json('status'))->toBeString()
        ->and($response->json())->not->toHaveKey('product')
        ->and($data['id'])->toBe($product->id)
        ->and($data['name'])->toBeString()
        ->and($data['base_price'])->toBeNumeric()
        ->and($data['price'])->toBeNumeric()
        ->and($data['is_active'])->toBeBool()
        ->and($data['sell_by_weight'])->toBeBool()
        ->and($data['category']['id'])->toBeInt()
        ->and($data['category']['name'])->toBeString()
        ->and($data['variants'])->toBeArray()
        ->and($data['variants'][0]['id'])->toBeInt()
        ->and($data['variants'][0]['name'])->toBeString()
        ->and($data['variants'][0]['price'])->toBeNumeric()
        ->and($data['variants'][0]['is_available'])->toBeBool()
        ->and($data['extra_option']['items'])->toBeArray()
        ->and($data['extra_option']['items'][0]['price'])->toBeNumeric()
        ->and($data['weight_option']['values'])->toBeArray()
        ->and($data['weight_option']['values'][0]['value'])->toBeNumeric()
        ->and($data['reviews_count'])->toBeInt()
        ->and($data['related_products'])->toBeArray()
        ->and($data['promotional_products'])->toBeArray();
});

it('locks JSON shape for catalog sections endpoint', function () {
    $section = Section::factory()->create([
        'is_active' => true,
        'sort_order' => 1,
    ]);
    $product = Product::factory()->create([
        'is_active' => true,
        'base_price' => 80.00,
    ]);
    $section->products()->attach($product->id, ['sort_order' => 1]);

    $response = $this->getJson(route('api.catalog-products.sections'));

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'status',
        'data' => [
            '*' => [
                'id',
                'title',
                'title_ar',
                'location',
                'sort_order',
                'products' => [
                    '*' => [
                        'id',
                        'name',
                        'name_ar',
                        'description',
                        'image',
                        'base_price',
                        'price_after_discount',
                        'price',
                        'is_active',
                        'sell_by_weight',
                        'has_variants',
                        'has_extras',
                        'has_options',
                    ],
                ],
            ],
        ],
    ]);

    expect($response->json('status'))->toBeString();
    $firstSection = $response->json('data.0');
    expect($firstSection['id'])->toBeInt()
        ->and($firstSection['title'])->toBeString()
        ->and($firstSection['sort_order'])->toBeInt()
        ->and($firstSection['products'])->toBeArray()
        ->and($firstSection['products'][0]['id'])->toBeInt()
        ->and($firstSection['products'][0]['name'])->toBeString()
        ->and($firstSection['products'][0]['price'])->toBeNumeric()
        ->and($firstSection['products'][0]['is_active'])->toBeBool();
});

it('locks JSON shape for catalog categories endpoint', function () {
    Category::factory()->create([
        'name' => 'Main Category',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $response = $this->getJson(route('api.catalog-products.categories'));

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'status',
        'data' => [
            '*' => [
                'id',
                'name',
                'name_ar',
                'description',
                'image',
                'sort_order',
            ],
        ],
    ]);

    expect($response->json('status'))->toBeString();
    $category = $response->json('data.0');
    expect($category['id'])->toBeInt()
        ->and($category['name'])->toBeString()
        ->and($category['sort_order'])->toBeInt();
});

it('locks JSON shape for catalog suggestions endpoint', function () {
    Product::factory()->create([
        'name' => 'Gourmet Beef Burger',
        'is_active' => true,
        'base_price' => 95.00,
    ]);

    $response = $this->getJson(route('api.catalog-products.suggestions', ['q' => 'Burger']));

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'status',
        'data' => [
            '*' => [
                'id',
                'name',
                'name_ar',
                'description',
                'image',
                'base_price',
                'price_after_discount',
                'price',
                'is_active',
                'sell_by_weight',
                'has_variants',
                'has_extras',
                'has_options',
            ],
        ],
    ]);

    expect($response->json('status'))->toBeString();
    $suggestion = $response->json('data.0');
    expect($suggestion['id'])->toBeInt()
        ->and($suggestion['name'])->toBeString()
        ->and($suggestion['base_price'])->toBeNumeric()
        ->and($suggestion['price'])->toBeNumeric()
        ->and($suggestion['is_active'])->toBeBool();
});

it('locks JSON shape for catalog products by-ids endpoint', function () {
    $product = Product::factory()->create([
        'name' => 'Signature Tacos',
        'is_active' => true,
        'base_price' => 70.00,
    ]);

    $response = $this->getJson(route('api.catalog-products.by-ids', ['ids' => [$product->id]]));

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'status',
        'data' => [
            '*' => [
                'id',
                'name',
                'name_ar',
                'description',
                'image',
                'base_price',
                'price_after_discount',
                'price',
                'is_active',
                'sell_by_weight',
                'has_variants',
                'has_extras',
                'has_options',
            ],
        ],
    ]);

    expect($response->json('status'))->toBeString();
    $item = $response->json('data.0');
    expect($item['id'])->toBe($product->id)
        ->and($item['name'])->toBeString()
        ->and($item['base_price'])->toBeNumeric()
        ->and($item['price'])->toBeNumeric()
        ->and($item['is_active'])->toBeBool();
});
