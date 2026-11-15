<?php

declare(strict_types=1);

use App\Plugins\CatalogProducts\src\Models\ExtraOption;
use App\Plugins\CatalogProducts\src\Models\ExtraOptionItem;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\ProductVariant;
use App\Plugins\CatalogProducts\src\Models\WeightOption;
use App\Plugins\CatalogProducts\src\Models\WeightOptionValue;

it('locks JSON shape for empty cart index endpoint', function () {
    $response = $this->getJson(route('api.cart.index'));

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'status',
        'data' => [
            'items',
            'total',
        ],
    ]);

    expect($response->json('status'))->toBe('success')
        ->and($response->json('data.items'))->toBeArray()
        ->and($response->json('data.items'))->toBeEmpty()
        ->and($response->json('data.total'))->toBeNumeric();
});

it('locks JSON shape for populated cart index endpoint', function () {
    $extraOption = ExtraOption::factory()->create();
    $extraItem = ExtraOptionItem::factory()->create([
        'extra_option_id' => $extraOption->id,
        'price' => 10.00,
    ]);

    $product = Product::factory()->create([
        'is_active' => true,
        'base_price' => 50.00,
        'extra_option_id' => $extraOption->id,
    ]);

    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'is_available' => true,
        'price' => 55.00,
    ]);

    $this->postJson(route('api.cart.store'), [
        'product_id' => $product->id,
        'variant_id' => $variant->id,
        'quantity' => '2',
        'extras' => [
            ['id' => $extraItem->id, 'quantity' => 1],
        ],
    ]);

    $response = $this->getJson(route('api.cart.index'));

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'status',
        'data' => [
            'items' => [
                '*' => [
                    'id',
                    'product_id',
                    'quantity',
                    'weight_multiplier',
                    'product' => [
                        'id',
                        'name',
                        'name_ar',
                        'image',
                        'sell_by_weight',
                        'weight_option',
                    ],
                    'variant' => [
                        'id',
                        'name',
                        'name_ar',
                    ],
                    'weight_option_value',
                    'extras' => [
                        '*' => [
                            'id',
                            'name',
                            'name_ar',
                            'price',
                            'quantity',
                        ],
                    ],
                    'price',
                    'extras_total',
                    'subtotal',
                ],
            ],
            'total',
        ],
    ]);

    expect($response->json('status'))->toBe('success');
    $firstItem = $response->json('data.items.0');
    expect($firstItem['id'])->not->toBeNull()
        ->and($firstItem['product_id'])->toBeInt()
        ->and($firstItem['quantity'])->toBeNumeric()
        ->and($firstItem['weight_multiplier'])->toBeInt()
        ->and($firstItem['product']['id'])->toBeInt()
        ->and($firstItem['product']['name'])->toBeString()
        ->and($firstItem['variant']['id'])->toBeInt()
        ->and($firstItem['variant']['name'])->toBeString()
        ->and($firstItem['extras'])->toBeArray()
        ->and($firstItem['extras'][0]['id'])->toBeInt()
        ->and($firstItem['extras'][0]['price'])->toBeNumeric()
        ->and($firstItem['price'])->toBeNumeric()
        ->and($firstItem['extras_total'])->toBeNumeric()
        ->and($firstItem['subtotal'])->toBeNumeric()
        ->and($response->json('data.total'))->toBeNumeric();

    // Fields with no reader dropped per review §9.3
    expect(array_key_exists('variant_id', $firstItem))->toBeFalse()
        ->and(array_key_exists('weight_option_value_id', $firstItem))->toBeFalse()
        ->and(array_key_exists('base_price', $firstItem['product']))->toBeFalse()
        ->and(array_key_exists('price_after_discount', $firstItem['product']))->toBeFalse()
        ->and(array_key_exists('price', $firstItem['variant']))->toBeFalse();
});

it('locks JSON shape for weight-based cart items', function () {
    $weightOption = WeightOption::factory()->create(['unit' => 'kg']);
    $value = WeightOptionValue::factory()->create([
        'weight_option_id' => $weightOption->id,
        'value' => '0.5',
        'label' => '500g',
        'sort_order' => 1,
    ]);

    $product = Product::factory()->create([
        'is_active' => true,
        'base_price' => 60.00,
        'sell_by_weight' => true,
        'weight_options_id' => $weightOption->id,
    ]);

    $this->postJson(route('api.cart.store'), [
        'product_id' => $product->id,
        'weight_option_value_id' => $value->id,
        'weight_multiplier' => 2,
        'quantity' => '1',
    ]);

    $response = $this->getJson(route('api.cart.index'));
    $response->assertSuccessful();

    $item = $response->json('data.items.0');
    expect($item['product']['sell_by_weight'])->toBeTrue()
        ->and($item['product']['weight_option']['id'])->toBe($weightOption->id)
        ->and($item['product']['weight_option']['unit'])->toBe('kg')
        ->and($item['product']['weight_option']['values'])->toBeArray()
        ->and($item['product']['weight_option']['values'][0]['id'])->toBe($value->id)
        ->and($item['weight_option_value']['id'])->toBe($value->id)
        ->and((float) $item['weight_option_value']['value'])->toBe(0.5)
        ->and($item['weight_option_value']['label'])->toBe('500g');
});

it('locks JSON shape for cart store endpoint', function () {
    $product = Product::factory()->create([
        'is_active' => true,
        'base_price' => 35.00,
    ]);

    $response = $this->postJson(route('api.cart.store'), [
        'product_id' => $product->id,
        'quantity' => '2',
    ]);

    $response->assertStatus(201);
    $response->assertJsonStructure([
        'status',
        'message',
        'data' => [
            'items' => [
                '*' => [
                    'id',
                    'product_id',
                    'quantity',
                    'weight_multiplier',
                    'product' => [
                        'id',
                        'name',
                        'name_ar',
                        'image',
                        'sell_by_weight',
                        'weight_option',
                    ],
                    'variant',
                    'weight_option_value',
                    'extras',
                    'price',
                    'extras_total',
                    'subtotal',
                ],
            ],
            'total',
        ],
    ]);

    expect($response->json('status'))->toBe('success')
        ->and($response->json('message'))->toBeString()
        ->and($response->json('data.items'))->toBeArray()
        ->and($response->json('data.items'))->toHaveCount(1);

    $item = $response->json('data.items.0');
    expect($item['id'])->not->toBeNull()
        ->and($item['product_id'])->toBe($product->id)
        ->and($item['quantity'])->toBeNumeric()
        ->and($item['weight_multiplier'])->toBeInt()
        ->and($item['price'])->toBeNumeric()
        ->and($item['extras_total'])->toBeNumeric()
        ->and($item['subtotal'])->toBeNumeric()
        ->and($response->json('data.total'))->toBeNumeric();
});

it('locks JSON shape for cart update endpoint', function () {
    $product = Product::factory()->create([
        'is_active' => true,
        'base_price' => 40.00,
    ]);

    $addResponse = $this->postJson(route('api.cart.store'), [
        'product_id' => $product->id,
        'quantity' => '1',
    ]);
    $itemId = $addResponse->json('data.items.0.id');

    $response = $this->patchJson(route('api.cart.update', $itemId), [
        'quantity' => '4',
    ]);

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'status',
        'message',
        'data' => [
            'items' => [
                '*' => [
                    'id',
                    'product_id',
                    'quantity',
                    'weight_multiplier',
                    'product' => [
                        'id',
                        'name',
                        'name_ar',
                        'image',
                        'sell_by_weight',
                        'weight_option',
                    ],
                    'variant',
                    'weight_option_value',
                    'extras',
                    'price',
                    'extras_total',
                    'subtotal',
                ],
            ],
            'total',
        ],
    ]);

    expect($response->json('status'))->toBe('success')
        ->and($response->json('message'))->toBeString()
        ->and($response->json('data.items'))->toBeArray()
        ->and($response->json('data.items.0.quantity'))->toBeNumeric()
        ->and((float) $response->json('data.items.0.quantity'))->toBe(4.0)
        ->and($response->json('data.items.0.subtotal'))->toBeNumeric()
        ->and($response->json('data.total'))->toBeNumeric();
});
