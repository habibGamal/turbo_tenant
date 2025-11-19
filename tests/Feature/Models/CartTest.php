<?php

declare(strict_types=1);

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CartItemExtra;
use App\Models\ExtraOptionItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can create a cart', function () {
    $cart = Cart::factory()->create();

    expect($cart)->toBeInstanceOf(Cart::class)
        ->and($cart->user)->toBeInstanceOf(User::class);
});

it('belongs to a user', function () {
    $user = User::factory()->create();
    $cart = Cart::factory()->for($user)->create();

    expect($cart->user->id)->toBe($user->id);
});

it('has many items', function () {
    $cart = Cart::factory()->create();
    $items = CartItem::factory()->count(3)->for($cart)->create();

    expect($cart->items)->toHaveCount(3)
        ->and($cart->items->first())->toBeInstanceOf(CartItem::class);
});

it('can create cart items with products', function () {
    $cart = Cart::factory()->create();
    $product = Product::factory()->create();
    $cartItem = CartItem::factory()
        ->for($cart)
        ->for($product)
        ->create();

    expect($cartItem->cart->id)->toBe($cart->id)
        ->and($cartItem->product->id)->toBe($product->id);
});

it('can create cart items with variants', function () {
    $cart = Cart::factory()->create();
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->for($product)->create();

    $cartItem = CartItem::factory()
        ->for($cart)
        ->for($product)
        ->for($variant, 'variant')
        ->create();

    expect($cartItem->variant)->toBeInstanceOf(ProductVariant::class)
        ->and($cartItem->variant->id)->toBe($variant->id);
});

it('cart items can have extras', function () {
    $cartItem = CartItem::factory()->create();
    $extras = CartItemExtra::factory()->count(2)->for($cartItem)->create();

    expect($cartItem->extras)->toHaveCount(2)
        ->and($cartItem->extras->first())->toBeInstanceOf(CartItemExtra::class);
});

it('cart item extras belong to extra option items', function () {
    $extraOptionItem = ExtraOptionItem::factory()->create();
    $cartItem = CartItem::factory()->create();

    $cartItemExtra = CartItemExtra::factory()
        ->for($cartItem)
        ->for($extraOptionItem)
        ->create();

    expect($cartItemExtra->extraOptionItem)->toBeInstanceOf(ExtraOptionItem::class)
        ->and($cartItemExtra->extraOptionItem->id)->toBe($extraOptionItem->id);
});

it('stores quantity correctly', function () {
    $cartItem = CartItem::factory()->create([
        'quantity' => 2.500,
    ]);

    expect($cartItem->quantity)->toBe('2.500');
});

it('casts dates properly', function () {
    $cart = Cart::factory()->create();

    expect($cart->last_activity_at)->toBeInstanceOf(DateTimeInterface::class)
        ->and($cart->created_at)->toBeInstanceOf(DateTimeInterface::class);
});
