<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Plugins\CartEngine\src\Models\Cart;
use App\Plugins\CartEngine\src\Models\CartItem;
use App\Plugins\CartEngine\src\Models\CartItemExtra;
use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\ExtraOption;
use App\Plugins\CatalogProducts\src\Models\ExtraOptionItem;
use App\Plugins\GuestCheckout\src\Models\GuestUser;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\ProductVariant;
use App\Models\User;
use App\Plugins\CatalogProducts\src\Models\WeightOption;
use App\Plugins\CatalogProducts\src\Models\WeightOptionValue;
use App\Plugins\CartEngine\src\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class CartServiceTest extends TestCase
{
    use RefreshDatabase;

    private CartService $cartService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cartService = app(CartService::class);
        Session::flush();
    }

    public function test_get_cart_count_returns_zero_when_no_cart_exists(): void
    {
        $user = User::factory()->create();

        $this->assertSame(0, $this->cartService->getCartCount(null));
        $this->assertSame(0, $this->cartService->getCartCount(new GuestUser()));
        $this->assertSame(0, $this->cartService->getCartCount($user));
    }

    public function test_get_cart_count_returns_correct_item_count_for_database_cart(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $product1 = Product::factory()->create(['category_id' => $category->id, 'is_active' => true]);
        $product2 = Product::factory()->create(['category_id' => $category->id, 'is_active' => true]);

        $cart = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product1->id]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product2->id]);

        $this->assertSame(2, $this->cartService->getCartCount($user));
    }

    public function test_get_cart_count_returns_correct_item_count_for_session_cart(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'is_active' => true]);

        Session::put('guest_cart_id', 'guest_test_session_123');
        $cart = Cart::create(['session_id' => 'guest_test_session_123']);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id]);

        $this->assertSame(1, $this->cartService->getCartCount(null));
        $this->assertSame(1, $this->cartService->getCartCount(new GuestUser()));
    }

    public function test_get_cart_returns_empty_structure_when_no_cart_exists(): void
    {
        $user = User::factory()->create();

        $resultUser = $this->cartService->getCart($user);
        $this->assertSame(['items' => [], 'total' => 0], $resultUser);

        $resultGuest = $this->cartService->getCart(null);
        $this->assertSame(['items' => [], 'total' => 0], $resultGuest);
    }

    public function test_add_item_for_authenticated_user_persists_to_database(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'base_price' => 25.00,
            'price_after_discount' => null,
            'is_active' => true,
            'sell_by_weight' => false,
        ]);

        $result = $this->cartService->addItem(
            user: $user,
            productId: $product->id,
            variantId: null,
            quantity: '2'
        );

        $this->assertDatabaseHas('carts', ['user_id' => $user->id]);
        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'quantity' => '2.000',
        ]);

        $this->assertCount(1, $result['items']);
        $this->assertEquals(50.00, $result['total']);
        $this->assertEquals(25.00, $result['items'][0]['price']);
        $this->assertEquals(2.0, $result['items'][0]['quantity']);
        $this->assertEquals(50.00, $result['items'][0]['subtotal']);
    }

    public function test_add_item_for_guest_persists_with_session_identifier(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'base_price' => 15.00,
            'price_after_discount' => null,
            'is_active' => true,
            'sell_by_weight' => false,
        ]);

        $result = $this->cartService->addItem(
            user: null,
            productId: $product->id,
            variantId: null,
            quantity: '3'
        );

        $this->assertTrue(Session::has('guest_cart_id'));
        $sessionId = Session::get('guest_cart_id');

        $this->assertDatabaseHas('carts', ['session_id' => $sessionId, 'user_id' => null]);
        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'quantity' => '3.000',
        ]);

        $this->assertCount(1, $result['items']);
        $this->assertEquals(45.00, $result['total']);
    }

    public function test_add_item_throws_validation_exception_when_product_is_inactive(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $inactiveProduct = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => false,
        ]);

        $this->expectException(ValidationException::class);

        $this->cartService->addItem(
            user: $user,
            productId: $inactiveProduct->id,
            variantId: null,
            quantity: '1'
        );
    }

    public function test_add_item_throws_validation_exception_when_product_does_not_exist(): void
    {
        $user = User::factory()->create();

        $this->expectException(ValidationException::class);

        $this->cartService->addItem(
            user: $user,
            productId: 999999,
            variantId: null,
            quantity: '1'
        );
    }

    public function test_add_item_with_variant_uses_variant_price(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'base_price' => 20.00,
            'is_active' => true,
            'sell_by_weight' => false,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 30.00,
        ]);

        $result = $this->cartService->addItem(
            user: $user,
            productId: $product->id,
            variantId: $variant->id,
            quantity: '2'
        );

        $this->assertCount(1, $result['items']);
        $this->assertEquals(30.00, $result['items'][0]['price']);
        $this->assertEquals(60.00, $result['items'][0]['subtotal']);
        $this->assertEquals(60.00, $result['total']);
        $this->assertSame($variant->id, $result['items'][0]['variant']['id']);
    }

    public function test_add_item_uses_discounted_price_when_available(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'base_price' => 40.00,
            'price_after_discount' => 32.00,
            'is_active' => true,
            'sell_by_weight' => false,
        ]);

        $result = $this->cartService->addItem(
            user: $user,
            productId: $product->id,
            variantId: null,
            quantity: '2'
        );

        $this->assertEquals(32.00, $result['items'][0]['price']);
        $this->assertEquals(64.00, $result['items'][0]['subtotal']);
        $this->assertEquals(64.00, $result['total']);
    }

    public function test_add_item_with_extras_as_ids_and_arrays(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $extraOption = ExtraOption::factory()->create();
        $extraItem1 = ExtraOptionItem::factory()->create([
            'extra_option_id' => $extraOption->id,
            'price' => 5.00,
        ]);
        $extraItem2 = ExtraOptionItem::factory()->create([
            'extra_option_id' => $extraOption->id,
            'price' => 3.00,
        ]);

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'base_price' => 20.00,
            'price_after_discount' => null,
            'extra_option_id' => $extraOption->id,
            'is_active' => true,
            'sell_by_weight' => false,
        ]);

        // Pass extras mixed: one plain ID and one array with quantity
        $result = $this->cartService->addItem(
            user: $user,
            productId: $product->id,
            variantId: null,
            quantity: '2',
            extras: [
                $extraItem1->id,
                ['id' => $extraItem2->id, 'quantity' => 2],
            ]
        );

        // Subtotal calculation for regular product:
        // extras total = (5.00 * 1) + (3.00 * 2) = 11.00
        // item subtotal = (base_price 20 + extras 11) * quantity 2 = 62.00
        $this->assertCount(1, $result['items']);
        $this->assertEquals(11.00, $result['items'][0]['extras_total']);
        $this->assertEquals(62.00, $result['items'][0]['subtotal']);
        $this->assertEquals(62.00, $result['total']);
        $this->assertCount(2, $result['items'][0]['extras']);
    }

    public function test_add_weight_based_item_calculates_weight_and_subtotal_correctly(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $weightOption = WeightOption::factory()->create(['unit' => 'kg']);
        $weightValue = WeightOptionValue::factory()->create([
            'weight_option_id' => $weightOption->id,
            'value' => '0.500',
            'label' => '500g',
        ]);

        $extraOption = ExtraOption::factory()->create();
        $extraItem = ExtraOptionItem::factory()->create([
            'extra_option_id' => $extraOption->id,
            'price' => 4.00,
        ]);

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'base_price' => 100.00,
            'price_after_discount' => null,
            'is_active' => true,
            'sell_by_weight' => true,
            'weight_options_id' => $weightOption->id,
            'extra_option_id' => $extraOption->id,
        ]);

        $result = $this->cartService->addItem(
            user: $user,
            productId: $product->id,
            variantId: null,
            quantity: '1',
            extras: [$extraItem->id],
            weightOptionValueId: $weightValue->id,
            weightMultiplier: 3
        );

        // For weight-based product:
        // finalQuantity = weight_multiplier (3) * weight_value (0.500) = 1.5
        // extrasTotal = 4.00 * 1 = 4.00
        // subtotal = (price 100.00 * finalQuantity 1.5) + (extrasTotal 4.00 * weight_multiplier 3) = 150 + 12 = 162.00
        $this->assertCount(1, $result['items']);
        $item = $result['items'][0];
        $this->assertEquals(1.5, $item['quantity']);
        $this->assertSame(3, $item['weight_multiplier']);
        $this->assertEquals(4.00, $item['extras_total']);
        $this->assertEquals(162.00, $item['subtotal']);
        $this->assertEquals(162.00, $result['total']);
        $this->assertNotNull($item['weight_option_value']);
        $this->assertSame('500g', $item['weight_option_value']['label']);
    }

    public function test_update_item_in_database_cart(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'base_price' => 10.00,
            'price_after_discount' => null,
            'is_active' => true,
            'sell_by_weight' => false,
        ]);

        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $cartItem = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'variant_id' => null,
            'quantity' => '1.000',
            'weight_multiplier' => 1,
        ]);

        $result = $this->cartService->updateItem(
            user: $user,
            itemId: $cartItem->id,
            quantity: '4'
        );

        $this->assertDatabaseHas('cart_items', [
            'id' => $cartItem->id,
            'quantity' => '4.000',
        ]);
        $this->assertEquals(40.00, $result['total']);
    }

    public function test_update_item_in_session_cart(): void
    {
        $sessionId = 'guest_session_update_test';
        Session::put('guest_cart_id', $sessionId);

        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'base_price' => 12.00,
            'price_after_discount' => null,
            'is_active' => true,
            'sell_by_weight' => false,
        ]);

        $cart = Cart::create(['session_id' => $sessionId]);
        $cartItem = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'variant_id' => null,
            'quantity' => '2.000',
            'weight_multiplier' => 1,
        ]);

        $result = $this->cartService->updateItem(
            user: null,
            itemId: $cartItem->id,
            quantity: '5'
        );

        $this->assertDatabaseHas('cart_items', [
            'id' => $cartItem->id,
            'quantity' => '5.000',
        ]);
        $this->assertEquals(60.00, $result['total']);
    }

    public function test_remove_item_from_database_cart(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'is_active' => true]);

        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $cartItem = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
        ]);

        $result = $this->cartService->removeItem(user: $user, itemId: $cartItem->id);

        $this->assertDatabaseMissing('cart_items', ['id' => $cartItem->id]);
        $this->assertEquals(['items' => [], 'total' => 0], $result);
    }

    public function test_remove_item_from_session_cart(): void
    {
        $sessionId = 'guest_session_remove_test';
        Session::put('guest_cart_id', $sessionId);

        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'is_active' => true]);

        $cart = Cart::create(['session_id' => $sessionId]);
        $cartItem = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
        ]);

        $result = $this->cartService->removeItem(user: null, itemId: $cartItem->id);

        $this->assertDatabaseMissing('cart_items', ['id' => $cartItem->id]);
        $this->assertEquals(['items' => [], 'total' => 0], $result);
    }

    public function test_clear_cart_for_user_deletes_cart_and_items(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'is_active' => true]);

        $cart = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
        ]);

        $result = $this->cartService->clearCart($user);

        $this->assertDatabaseMissing('carts', ['id' => $cart->id]);
        $this->assertDatabaseMissing('cart_items', ['cart_id' => $cart->id]);
        $this->assertSame(['items' => [], 'total' => 0], $result);
    }

    public function test_clear_cart_for_guest_deletes_cart_and_forgets_session(): void
    {
        $sessionId = 'guest_session_clear_test';
        Session::put('guest_cart_id', $sessionId);

        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'is_active' => true]);

        $cart = Cart::create(['session_id' => $sessionId]);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
        ]);

        $result = $this->cartService->clearCart(null);

        $this->assertFalse(Session::has('guest_cart_id'));
        $this->assertDatabaseMissing('carts', ['id' => $cart->id]);
        $this->assertDatabaseMissing('cart_items', ['cart_id' => $cart->id]);
        $this->assertSame(['items' => [], 'total' => 0], $result);
    }

    public function test_sync_guest_cart_to_user_migrates_items_and_extras(): void
    {
        $sessionId = 'guest_sync_session_999';
        Session::put('guest_cart_id', $sessionId);

        $user = User::factory()->create();
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'is_active' => true]);

        $extraOption = ExtraOption::factory()->create();
        $extraItem = ExtraOptionItem::factory()->create(['extra_option_id' => $extraOption->id]);

        $guestCart = Cart::create(['session_id' => $sessionId]);
        $guestCartItem = CartItem::factory()->create([
            'cart_id' => $guestCart->id,
            'product_id' => $product->id,
            'quantity' => '2.000',
            'weight_multiplier' => 1,
        ]);
        CartItemExtra::create([
            'cart_item_id' => $guestCartItem->id,
            'extra_option_item_id' => $extraItem->id,
            'quantity' => 1,
        ]);

        $this->cartService->syncGuestCartToUser($user);

        // Verify guest cart deleted and session forgotten
        $this->assertDatabaseMissing('carts', ['id' => $guestCart->id]);
        $this->assertFalse(Session::has('guest_cart_id'));

        // Verify user cart created with item and extra
        $userCart = Cart::where('user_id', $user->id)->first();
        $this->assertNotNull($userCart);

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $userCart->id,
            'product_id' => $product->id,
            'quantity' => '2.000',
        ]);

        $userCartItem = CartItem::where('cart_id', $userCart->id)->first();
        $this->assertDatabaseHas('cart_item_extras', [
            'cart_item_id' => $userCartItem->id,
            'extra_option_item_id' => $extraItem->id,
            'quantity' => 1,
        ]);
    }

    public function test_sync_guest_cart_noop_when_no_guest_cart_exists(): void
    {
        $user = User::factory()->create();

        // Should not throw or create any cart
        $this->cartService->syncGuestCartToUser($user);

        $this->assertDatabaseMissing('carts', ['user_id' => $user->id]);
    }

    public function test_update_item_multiple_fields_simultaneously(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $weightOption = WeightOption::factory()->create();
        $val1 = WeightOptionValue::factory()->create(['weight_option_id' => $weightOption->id, 'value' => '0.25']);
        $val2 = WeightOptionValue::factory()->create(['weight_option_id' => $weightOption->id, 'value' => '0.50']);

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'base_price' => 50.00,
            'price_after_discount' => null,
            'is_active' => true,
            'sell_by_weight' => true,
            'weight_options_id' => $weightOption->id,
        ]);

        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $cartItem = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'variant_id' => null,
            'weight_option_value_id' => $val1->id,
            'quantity' => '1.000',
            'weight_multiplier' => 1,
        ]);

        $result = $this->cartService->updateItem(
            user: $user,
            itemId: $cartItem->id,
            quantity: '2.000',
            weightMultiplier: 4,
            weightOptionValueId: $val2->id
        );

        $this->assertDatabaseHas('cart_items', [
            'id' => $cartItem->id,
            'quantity' => '2.000',
            'weight_multiplier' => 4,
            'weight_option_value_id' => $val2->id,
        ]);

        // finalQuantity = 4 * 0.5 = 2.0
        // subtotal = 50 * 2.0 = 100.00
        $this->assertEquals(100.00, $result['total']);
    }

    public function test_update_item_throws_not_found_exception_for_invalid_id(): void
    {
        $user = User::factory()->create();

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->cartService->updateItem(
            user: $user,
            itemId: 999999,
            quantity: '2'
        );
    }

    public function test_remove_item_throws_not_found_exception_for_invalid_id(): void
    {
        $user = User::factory()->create();

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->cartService->removeItem(user: $user, itemId: 999999);
    }
}
