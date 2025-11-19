<?php

declare(strict_types=1);

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ExtraOptionItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->cartService = app(CartService::class);
});

it('can view cart page', function () {
    $response = $this->get(route('cart.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('Cart'));
});

it('can add item to cart as guest', function () {
    $product = Product::factory()->create(['base_price' => 10.00]);

    $response = $this->postJson(route('cart.store'), [
        'product_id' => $product->id,
        'quantity' => '2',
    ]);

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'message',
        'cart' => [
            'items',
            'total',
        ],
    ]);
});

it('can add item to cart as authenticated user', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['base_price' => 15.00]);

    $response = $this->actingAs($user)
        ->postJson(route('cart.store'), [
            'product_id' => $product->id,
            'quantity' => '1',
        ]);

    $response->assertSuccessful();

    $this->assertDatabaseHas('carts', [
        'user_id' => $user->id,
    ]);

    $this->assertDatabaseHas('cart_items', [
        'product_id' => $product->id,
        'quantity' => '1.000',
    ]);
});

it('can add item with variant to cart', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'price' => 20.00,
    ]);

    $response = $this->actingAs($user)
        ->postJson(route('cart.store'), [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity' => '1',
        ]);

    $response->assertSuccessful();

    $this->assertDatabaseHas('cart_items', [
        'product_id' => $product->id,
        'variant_id' => $variant->id,
    ]);
});

it('can add item with extras to cart', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $extra = ExtraOptionItem::factory()->create(['price' => 2.50]);

    $response = $this->actingAs($user)
        ->postJson(route('cart.store'), [
            'product_id' => $product->id,
            'quantity' => '1',
            'extras' => [$extra->id],
        ]);

    $response->assertSuccessful();

    $cart = Cart::where('user_id', $user->id)->first();
    $cartItem = CartItem::where('cart_id', $cart->id)->first();

    $this->assertDatabaseHas('cart_item_extras', [
        'cart_item_id' => $cartItem->id,
        'extra_option_item_id' => $extra->id,
    ]);
});

it('can update cart item quantity', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $cart = Cart::factory()->create(['user_id' => $user->id]);
    $cartItem = CartItem::factory()->create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'quantity' => '1.000',
    ]);

    $response = $this->actingAs($user)
        ->patchJson(route('cart.update', $cartItem->id), [
            'quantity' => '3',
        ]);

    $response->assertSuccessful();

    $this->assertDatabaseHas('cart_items', [
        'id' => $cartItem->id,
        'quantity' => '3.000',
    ]);
});

it('can remove item from cart', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $cart = Cart::factory()->create(['user_id' => $user->id]);
    $cartItem = CartItem::factory()->create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
    ]);

    $response = $this->actingAs($user)
        ->deleteJson(route('cart.destroy', $cartItem->id));

    $response->assertSuccessful();

    $this->assertDatabaseMissing('cart_items', [
        'id' => $cartItem->id,
    ]);
});

it('can clear entire cart', function () {
    $user = User::factory()->create();
    $product1 = Product::factory()->create();
    $product2 = Product::factory()->create();
    $cart = Cart::factory()->create(['user_id' => $user->id]);
    CartItem::factory()->create([
        'cart_id' => $cart->id,
        'product_id' => $product1->id,
    ]);
    CartItem::factory()->create([
        'cart_id' => $cart->id,
        'product_id' => $product2->id,
    ]);

    $response = $this->actingAs($user)
        ->deleteJson(route('cart.clear'));

    $response->assertSuccessful();

    $this->assertDatabaseMissing('carts', [
        'id' => $cart->id,
    ]);
});

it('syncs guest cart to user cart on login', function () {
    // Create a product
    $product = Product::factory()->create(['base_price' => 10.00]);

    // Add item to guest cart
    $this->postJson(route('cart.store'), [
        'product_id' => $product->id,
        'quantity' => '2',
    ]);

    // Create and login as user
    $user = User::factory()->create();

    // Sync cart
    $response = $this->actingAs($user)
        ->postJson(route('cart.sync'));

    $response->assertSuccessful();

    // Verify cart was synced to database
    $this->assertDatabaseHas('carts', [
        'user_id' => $user->id,
    ]);

    $cart = Cart::where('user_id', $user->id)->first();

    $this->assertDatabaseHas('cart_items', [
        'cart_id' => $cart->id,
        'product_id' => $product->id,
    ]);
});

it('validates product_id is required when adding to cart', function () {
    $response = $this->postJson(route('cart.store'), [
        'quantity' => '1',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['product_id']);
});

it('validates quantity is required when adding to cart', function () {
    $product = Product::factory()->create();

    $response = $this->postJson(route('cart.store'), [
        'product_id' => $product->id,
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['quantity']);
});

it('validates quantity minimum value', function () {
    $product = Product::factory()->create();

    $response = $this->postJson(route('cart.store'), [
        'product_id' => $product->id,
        'quantity' => '0',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['quantity']);
});

it('validates extras must be array', function () {
    $product = Product::factory()->create();

    $response = $this->postJson(route('cart.store'), [
        'product_id' => $product->id,
        'quantity' => '1',
        'extras' => 'not-an-array',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['extras']);
});

it('validates extra option items exist', function () {
    $product = Product::factory()->create();

    $response = $this->postJson(route('cart.store'), [
        'product_id' => $product->id,
        'quantity' => '1',
        'extras' => [999999],
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['extras.0']);
});

it('updates cart last_activity_at when adding items', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $cart = Cart::factory()->create([
        'user_id' => $user->id,
        'last_activity_at' => now()->subHours(2),
    ]);

    $oldActivityTime = $cart->last_activity_at;

    $this->actingAs($user)
        ->postJson(route('cart.store'), [
            'product_id' => $product->id,
            'quantity' => '1',
        ]);

    $cart->refresh();

    expect($cart->last_activity_at)->not->toBe($oldActivityTime);
});

it('increments quantity when adding same item again', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();

    // Add item first time
    $this->actingAs($user)
        ->postJson(route('cart.store'), [
            'product_id' => $product->id,
            'quantity' => '2',
        ]);

    // Add same item again
    $this->actingAs($user)
        ->postJson(route('cart.store'), [
            'product_id' => $product->id,
            'quantity' => '3',
        ]);

    $cart = Cart::where('user_id', $user->id)->first();
    $cartItem = CartItem::where('cart_id', $cart->id)
        ->where('product_id', $product->id)
        ->first();

    expect($cartItem->quantity)->toBe('5.000');
});
