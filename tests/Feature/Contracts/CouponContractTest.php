<?php

declare(strict_types=1);

use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CouponsPromotions\src\Models\Coupon;

it('locks JSON shape for valid coupon validation endpoint', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create([
        'category_id' => $category->id,
        'base_price' => 100.00,
        'is_active' => true,
    ]);

    $this->postJson(route('api.cart.store'), [
        'product_id' => $product->id,
        'quantity' => '1',
    ])->assertSuccessful();

    $coupon = Coupon::factory()->create([
        'code' => 'SAVE20',
        'type' => 'percentage',
        'value' => 20.0,
        'is_active' => true,
        'expiry_date' => now()->addMonth(),
        'conditions' => [
            'shipping' => [
                'free_shipping' => false,
            ],
        ],
    ]);

    $response = $this->postJson(route('api.coupons.validate'), [
        'code' => 'SAVE20',
    ]);

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'status',
        'data' => [
            'coupon' => [
                'id',
                'code',
                'discount',
                'type',
                'value',
                'free_shipping',
            ],
        ],
        'message',
    ]);

    expect($response->json('status'))->toBe('success')
        ->and($response->json('message'))->toBe('Coupon applied successfully')
        ->and($response->json('data.coupon.id'))->toBe($coupon->id)
        ->and($response->json('data.coupon.code'))->toBe('SAVE20')
        ->and($response->json('data.coupon.discount'))->toBeNumeric()
        ->and($response->json('data.coupon.type'))->toBe('percentage')
        ->and($response->json('data.coupon.value'))->toBeNumeric()
        ->and($response->json('data.coupon.free_shipping'))->toBeBool();
});

it('locks JSON shape for invalid coupon validation endpoint', function () {
    $response = $this->postJson(route('api.coupons.validate'), [
        'code' => 'INVALID_CODE_XYZ',
    ]);

    $response->assertStatus(422);
    $response->assertJsonStructure([
        'status',
        'message',
        'errors' => [
            'code',
        ],
    ]);

    expect($response->json('status'))->toBe('error')
        ->and($response->json('message'))->toBe('Invalid coupon code')
        ->and($response->json('errors.code'))->toBeArray();
});

it('locks JSON shape for ineligible coupon validation endpoint (422)', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create([
        'category_id' => $category->id,
        'base_price' => 50.00,
        'is_active' => true,
    ]);

    $this->postJson(route('api.cart.store'), [
        'product_id' => $product->id,
        'quantity' => '1',
    ])->assertSuccessful();

    $coupon = Coupon::factory()->create([
        'code' => 'MIN100',
        'type' => 'fixed',
        'value' => 10.0,
        'is_active' => true,
        'conditions' => [
            'min_order_total' => 100.0,
        ],
    ]);

    $response = $this->postJson(route('api.coupons.validate'), [
        'code' => 'MIN100',
    ]);

    $response->assertStatus(422);
    $response->assertJsonStructure([
        'status',
        'message',
        'errors' => [
            'code',
        ],
    ]);

    expect($response->json('status'))->toBe('error')
        ->and($response->json('message'))->toContain('Minimum order total')
        ->and($response->json('errors.code.0'))->toContain('Minimum order total');
});

it('rejects coupon validation when address does not belong to user (403)', function () {
    $otherUser = App\Models\User::factory()->create();
    $governorate = App\Plugins\LocationsDelivery\src\Models\Governorate::factory()->create();
    $area = App\Plugins\LocationsDelivery\src\Models\Area::factory()->create(['governorate_id' => $governorate->id]);
    $address = App\Plugins\LocationsDelivery\src\Models\Address::factory()->create([
        'user_id' => $otherUser->id,
        'area_id' => $area->id,
    ]);

    $coupon = Coupon::factory()->create([
        'code' => 'VALID20',
        'is_active' => true,
    ]);

    $response = $this->postJson(route('api.coupons.validate'), [
        'code' => 'VALID20',
        'address_id' => $address->id,
    ]);

    $response->assertStatus(403);
});
