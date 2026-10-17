<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Cart\Repositories;

use App\Plugins\CartEngine\src\Models\Cart;
use App\Plugins\CartEngine\src\Models\CartItem;
use App\Plugins\CartEngine\src\Models\CartItemExtra;
use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\ExtraOption;
use App\Plugins\CatalogProducts\src\Models\ExtraOptionItem;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Models\User;
use App\Plugins\CartEngine\src\Services\Repositories\CartRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CartRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private CartRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new CartRepository();
    }

    public function test_find_cart_with_relations(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'is_active' => true]);
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id]);

        $found = $this->repository->findCartWithRelations('user_id', $user->id);

        $this->assertNotNull($found);
        $this->assertTrue($found->relationLoaded('items'));
        $this->assertCount(1, $found->items);
    }

    public function test_count_cart_items(): void
    {
        $this->assertSame(0, $this->repository->countCartItems(null));

        $cart = Cart::factory()->create();
        $this->assertSame(0, $this->repository->countCartItems($cart));

        $product = Product::factory()->create(['is_active' => true]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id]);

        $this->assertSame(1, $this->repository->countCartItems($cart));
    }

    public function test_create_cart_item_with_extras(): void
    {
        $cart = Cart::factory()->create();
        $product = Product::factory()->create(['is_active' => true]);
        $extraOption = ExtraOption::factory()->create();
        $extraItem = ExtraOptionItem::factory()->create(['extra_option_id' => $extraOption->id]);

        $item = $this->repository->createCartItem(
            cart: $cart,
            productId: $product->id,
            variantId: null,
            quantity: '3.000',
            extras: [$extraItem->id],
            weightOptionValueId: null,
            weightMultiplier: 1
        );

        $this->assertInstanceOf(CartItem::class, $item);
        $this->assertDatabaseHas('cart_items', ['id' => $item->id, 'quantity' => '3.000']);
        $this->assertDatabaseHas('cart_item_extras', ['cart_item_id' => $item->id, 'extra_option_item_id' => $extraItem->id]);
    }

    public function test_update_cart_item(): void
    {
        $cart = Cart::factory()->create();
        $product = Product::factory()->create(['is_active' => true]);
        $item = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => '1.000',
        ]);

        $updated = $this->repository->updateCartItem($item, ['quantity' => '5.000']);

        $this->assertEquals('5.000', $updated->quantity);
        $this->assertDatabaseHas('cart_items', ['id' => $item->id, 'quantity' => '5.000']);
    }

    public function test_delete_cart_item(): void
    {
        $cart = Cart::factory()->create();
        $product = Product::factory()->create(['is_active' => true]);
        $item = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
        ]);

        $this->repository->deleteCartItem($item);

        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    public function test_delete_cart(): void
    {
        $cart = Cart::factory()->create();
        $product = Product::factory()->create(['is_active' => true]);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
        ]);

        $this->repository->deleteCart($cart);

        $this->assertDatabaseMissing('carts', ['id' => $cart->id]);
        $this->assertDatabaseMissing('cart_items', ['cart_id' => $cart->id]);
    }

    public function test_migrate_cart(): void
    {
        $fromCart = Cart::factory()->create();
        $toCart = Cart::factory()->create();
        $product = Product::factory()->create(['is_active' => true]);
        $fromItem = CartItem::factory()->create([
            'cart_id' => $fromCart->id,
            'product_id' => $product->id,
            'quantity' => '2.000',
        ]);
        $extraOption = ExtraOption::factory()->create();
        $extraItem = ExtraOptionItem::factory()->create(['extra_option_id' => $extraOption->id]);
        CartItemExtra::create([
            'cart_item_id' => $fromItem->id,
            'extra_option_item_id' => $extraItem->id,
            'quantity' => 2,
        ]);

        $this->repository->migrateCart($fromCart, $toCart);

        $this->assertDatabaseMissing('carts', ['id' => $fromCart->id]);
        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $toCart->id,
            'product_id' => $product->id,
            'quantity' => '2.000',
        ]);
        $newItem = CartItem::where('cart_id', $toCart->id)->first();
        $this->assertDatabaseHas('cart_item_extras', [
            'cart_item_id' => $newItem->id,
            'extra_option_item_id' => $extraItem->id,
            'quantity' => 2,
        ]);
    }
}
