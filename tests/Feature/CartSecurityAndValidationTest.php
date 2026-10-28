<?php

declare(strict_types=1);

use App\Models\User;
use App\Plugins\CartEngine\src\Models\Cart;
use App\Plugins\CartEngine\src\Models\CartItem;
use App\Plugins\CatalogProducts\src\Models\ExtraOption;
use App\Plugins\CatalogProducts\src\Models\ExtraOptionItem;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\ProductVariant;
use App\Plugins\CatalogProducts\src\Models\WeightOption;
use App\Plugins\CatalogProducts\src\Models\WeightOptionValue;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Cart Security (IDOR)', function () {
    it('prevents user from updating another user cart item and returns 404', function () {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $product = Product::factory()->create(['base_price' => 20.00, 'is_active' => true]);
        $cartB = Cart::factory()->create(['user_id' => $userB->id]);
        $itemB = CartItem::factory()->create([
            'cart_id' => $cartB->id,
            'product_id' => $product->id,
            'quantity' => '1.000',
        ]);

        $response = $this->actingAs($userA)
            ->patchJson(route('api.cart.update', $itemB->id), [
                'quantity' => '5.000',
            ]);

        $response->assertNotFound();

        expect($itemB->fresh()->quantity)->toBe('1.000');
    });

    it('prevents user from deleting another user cart item and returns 404', function () {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $product = Product::factory()->create(['base_price' => 20.00, 'is_active' => true]);
        $cartB = Cart::factory()->create(['user_id' => $userB->id]);
        $itemB = CartItem::factory()->create([
            'cart_id' => $cartB->id,
            'product_id' => $product->id,
            'quantity' => '1.000',
        ]);

        $response = $this->actingAs($userA)
            ->deleteJson(route('api.cart.destroy', $itemB->id));

        $response->assertNotFound();

        $this->assertDatabaseHas('cart_items', ['id' => $itemB->id]);
    });

    it('prevents guest from updating another cart item and returns 404', function () {
        auth()->logout();
        $user = User::factory()->create();
        $product = Product::factory()->create(['base_price' => 20.00, 'is_active' => true]);
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $item = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => '1.000',
        ]);

        $response = $this->patchJson(route('api.cart.update', $item->id), [
            'quantity' => '3.000',
        ]);

        $response->assertNotFound();
    });
});

describe('Cart Option Validation - Variants', function () {
    it('requires variant when product has variants', function () {
        $user = User::factory()->create();
        $product = Product::factory()->create(['base_price' => 20.00, 'is_active' => true]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'is_available' => true]);

        $response = $this->actingAs($user)
            ->postJson(route('api.cart.store'), [
                'product_id' => $product->id,
                'quantity' => '1',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['variant_id']);
    });

    it('rejects variant belonging to another product', function () {
        $user = User::factory()->create();
        $productA = Product::factory()->create(['base_price' => 20.00, 'is_active' => true]);
        $productB = Product::factory()->create(['base_price' => 10.00, 'is_active' => true]);
        $variantB = ProductVariant::factory()->create(['product_id' => $productB->id, 'is_available' => true]);

        $response = $this->actingAs($user)
            ->postJson(route('api.cart.store'), [
                'product_id' => $productA->id,
                'variant_id' => $variantB->id,
                'quantity' => '1',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['variant_id']);
    });

    it('rejects unavailable variant', function () {
        $user = User::factory()->create();
        $product = Product::factory()->create(['base_price' => 20.00, 'is_active' => true]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_available' => false,
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('api.cart.store'), [
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'quantity' => '1',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['variant_id']);
    });

    it('rejects variant_id for product without variants', function () {
        $user = User::factory()->create();
        $product = Product::factory()->create(['base_price' => 20.00, 'is_active' => true]);
        $otherProduct = Product::factory()->create(['base_price' => 15.00, 'is_active' => true]);
        $otherVariant = ProductVariant::factory()->create(['product_id' => $otherProduct->id, 'is_available' => true]);

        $response = $this->actingAs($user)
            ->postJson(route('api.cart.store'), [
                'product_id' => $product->id,
                'variant_id' => $otherVariant->id,
                'quantity' => '1',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['variant_id']);
    });
});

describe('Cart Option Validation - Weight Options', function () {
    it('requires weight_option_value_id when sell_by_weight is true', function () {
        $user = User::factory()->create();
        $weightOption = WeightOption::factory()->create();
        $product = Product::factory()->create([
            'base_price' => 30.00,
            'is_active' => true,
            'sell_by_weight' => true,
            'weight_options_id' => $weightOption->id,
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('api.cart.store'), [
                'product_id' => $product->id,
                'quantity' => '1',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['weight_option_value_id']);
    });

    it('rejects weight_option_value_id when sell_by_weight is false', function () {
        $user = User::factory()->create();
        $weightOption = WeightOption::factory()->create();
        $weightValue = WeightOptionValue::factory()->create(['weight_option_id' => $weightOption->id]);
        $product = Product::factory()->create([
            'base_price' => 30.00,
            'is_active' => true,
            'sell_by_weight' => false,
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('api.cart.store'), [
                'product_id' => $product->id,
                'weight_option_value_id' => $weightValue->id,
                'quantity' => '1',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['weight_option_value_id']);
    });

    it('rejects weight_option_value_id belonging to another weight option', function () {
        $user = User::factory()->create();
        $weightOptionA = WeightOption::factory()->create();
        $weightOptionB = WeightOption::factory()->create();
        $weightValueB = WeightOptionValue::factory()->create(['weight_option_id' => $weightOptionB->id]);
        $product = Product::factory()->create([
            'base_price' => 30.00,
            'is_active' => true,
            'sell_by_weight' => true,
            'weight_options_id' => $weightOptionA->id,
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('api.cart.store'), [
                'product_id' => $product->id,
                'weight_option_value_id' => $weightValueB->id,
                'quantity' => '1',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['weight_option_value_id']);
    });

    it('enforces weight option validation on update', function () {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'base_price' => 30.00,
            'is_active' => true,
            'sell_by_weight' => false,
        ]);
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $cartItem = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => '1.000',
        ]);

        $weightOption = WeightOption::factory()->create();
        $weightValue = WeightOptionValue::factory()->create(['weight_option_id' => $weightOption->id]);

        $response = $this->actingAs($user)
            ->patchJson(route('api.cart.update', $cartItem->id), [
                'weight_option_value_id' => $weightValue->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['weight_option_value_id']);
    });
});

describe('Cart Option Validation - Extras', function () {
    it('rejects extras when product has no extra option', function () {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'base_price' => 25.00,
            'is_active' => true,
            'extra_option_id' => null,
        ]);
        $extra = ExtraOptionItem::factory()->create();

        $response = $this->actingAs($user)
            ->postJson(route('api.cart.store'), [
                'product_id' => $product->id,
                'quantity' => '1',
                'extras' => [$extra->id],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['extras']);
    });

    it('rejects duplicate extra ids in one request', function () {
        $user = User::factory()->create();
        $extraOption = ExtraOption::factory()->create(['allow_multiple' => true]);
        $extraItem = ExtraOptionItem::factory()->create(['extra_option_id' => $extraOption->id]);
        $product = Product::factory()->create([
            'base_price' => 25.00,
            'is_active' => true,
            'extra_option_id' => $extraOption->id,
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('api.cart.store'), [
                'product_id' => $product->id,
                'quantity' => '1',
                'extras' => [$extraItem->id, $extraItem->id],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['extras']);
    });

    it('rejects multiple extras when allow_multiple is false', function () {
        $user = User::factory()->create();
        $extraOption = ExtraOption::factory()->create(['allow_multiple' => false]);
        $item1 = ExtraOptionItem::factory()->create(['extra_option_id' => $extraOption->id]);
        $item2 = ExtraOptionItem::factory()->create(['extra_option_id' => $extraOption->id]);
        $product = Product::factory()->create([
            'base_price' => 25.00,
            'is_active' => true,
            'extra_option_id' => $extraOption->id,
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('api.cart.store'), [
                'product_id' => $product->id,
                'quantity' => '1',
                'extras' => [$item1->id, $item2->id],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['extras']);
    });

    it('enforces min_selections and max_selections rules', function () {
        $user = User::factory()->create();
        $extraOption = ExtraOption::factory()->create([
            'allow_multiple' => true,
            'min_selections' => 2,
            'max_selections' => 3,
        ]);
        $item1 = ExtraOptionItem::factory()->create(['extra_option_id' => $extraOption->id]);
        $item2 = ExtraOptionItem::factory()->create(['extra_option_id' => $extraOption->id]);
        $item3 = ExtraOptionItem::factory()->create(['extra_option_id' => $extraOption->id]);
        $item4 = ExtraOptionItem::factory()->create(['extra_option_id' => $extraOption->id]);
        $product = Product::factory()->create([
            'base_price' => 25.00,
            'is_active' => true,
            'extra_option_id' => $extraOption->id,
        ]);

        // Too few
        $responseLow = $this->actingAs($user)
            ->postJson(route('api.cart.store'), [
                'product_id' => $product->id,
                'quantity' => '1',
                'extras' => [$item1->id],
            ]);
        $responseLow->assertStatus(422)->assertJsonValidationErrors(['extras']);

        // Too many
        $responseHigh = $this->actingAs($user)
            ->postJson(route('api.cart.store'), [
                'product_id' => $product->id,
                'quantity' => '1',
                'extras' => [$item1->id, $item2->id, $item3->id, $item4->id],
            ]);
        $responseHigh->assertStatus(422)->assertJsonValidationErrors(['extras']);
    });

    it('rejects extra quantity > 1 when allow_quantity is false', function () {
        $user = User::factory()->create();
        $extraOption = ExtraOption::factory()->create(['allow_multiple' => true]);
        $item = ExtraOptionItem::factory()->create([
            'extra_option_id' => $extraOption->id,
            'allow_quantity' => false,
        ]);
        $product = Product::factory()->create([
            'base_price' => 25.00,
            'is_active' => true,
            'extra_option_id' => $extraOption->id,
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('api.cart.store'), [
                'product_id' => $product->id,
                'quantity' => '1',
                'extras' => [
                    ['id' => $item->id, 'quantity' => 2],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['extras']);
    });

    it('allows extra quantity > 1 when allow_quantity is true', function () {
        $user = User::factory()->create();
        $extraOption = ExtraOption::factory()->create(['allow_multiple' => true]);
        $item = ExtraOptionItem::factory()->create([
            'extra_option_id' => $extraOption->id,
            'allow_quantity' => true,
        ]);
        $product = Product::factory()->create([
            'base_price' => 25.00,
            'is_active' => true,
            'extra_option_id' => $extraOption->id,
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('api.cart.store'), [
                'product_id' => $product->id,
                'quantity' => '1',
                'extras' => [
                    ['id' => $item->id, 'quantity' => 2],
                ],
            ]);

        $response->assertCreated();
    });
});
