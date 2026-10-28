<?php

declare(strict_types=1);

use App\Models\User;
use App\Plugins\CatalogProducts\src\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can fetch cart via rest api GET /api/cart', function () {
    $response = $this->getJson('/api/cart');

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'status',
        'cart' => [
            'items',
            'total',
        ],
    ]);
    expect($response->json('status'))->toBe('success');
});

it('can add item to cart via rest api POST /api/cart/items', function () {
    $product = Product::factory()->create(['base_price' => 25.00, 'is_active' => true]);

    $response = $this->postJson('/api/cart/items', [
        'product_id' => $product->id,
        'quantity' => '2',
    ]);

    $response->assertCreated();
    $response->assertJsonStructure([
        'status',
        'message',
        'cart' => [
            'items',
            'total',
        ],
    ]);
    expect($response->json('status'))->toBe('success');
    expect($response->json('cart.items'))->toHaveCount(1);
});

it('can update item via rest api PATCH /api/cart/items/{id}', function () {
    $product = Product::factory()->create(['base_price' => 15.00, 'is_active' => true]);

    $addResponse = $this->postJson('/api/cart/items', [
        'product_id' => $product->id,
        'quantity' => '1',
    ]);
    $itemId = $addResponse->json('cart.items.0.id');

    $response = $this->patchJson("/api/cart/items/{$itemId}", [
        'quantity' => '4',
    ]);

    $response->assertSuccessful();
    expect($response->json('status'))->toBe('success');
    expect((float) ($response->json('cart.items.0.quantity')))->toBe(4.0);
});

it('can remove item via rest api DELETE /api/cart/items/{id}', function () {
    $product = Product::factory()->create(['base_price' => 10.00, 'is_active' => true]);

    $addResponse = $this->postJson('/api/cart/items', [
        'product_id' => $product->id,
        'quantity' => '1',
    ]);
    $itemId = $addResponse->json('cart.items.0.id');

    $response = $this->deleteJson("/api/cart/items/{$itemId}");

    $response->assertSuccessful();
    expect($response->json('status'))->toBe('success');
    expect($response->json('cart.items'))->toHaveCount(0);
});

it('can clear cart via rest api DELETE /api/cart', function () {
    $product = Product::factory()->create(['base_price' => 30.00, 'is_active' => true]);

    $this->postJson('/api/cart/items', [
        'product_id' => $product->id,
        'quantity' => '3',
    ]);

    $response = $this->deleteJson('/api/cart');

    $response->assertSuccessful();
    expect($response->json('status'))->toBe('success');
    expect($response->json('cart.items'))->toBeEmpty();
    expect($response->json('cart.total'))->toBe(0);
});

it('can sync guest cart to user via rest api POST /api/cart/sync', function () {
    auth()->logout();
    $product = Product::factory()->create(['base_price' => 50.00, 'is_active' => true]);

    $this->postJson('/api/cart/items', [
        'product_id' => $product->id,
        'quantity' => '1',
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/cart/sync');

    $response->assertSuccessful();
    expect($response->json('status'))->toBe('success');
    expect($response->json('cart.items'))->toHaveCount(1);
});
