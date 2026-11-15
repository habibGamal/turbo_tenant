<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CouponsPromotions\src\Models\Coupon;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\OrderManagement\src\Models\Order;
use Tests\TestCase;

final class CategoryCouponCheckoutTest extends TestCase
{
    public function test_successfully_places_an_order_with_a_category_scoped_coupon(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'base_price' => 100.00,
            'is_active' => true,
        ]);
        $branch = Branch::factory()->create(['is_active' => true]);

        $coupon = Coupon::factory()->create([
            'code' => 'CAT20',
            'type' => 'percentage',
            'value' => 20.0,
            'is_active' => true,
            'expiry_date' => now()->addMonth(),
            'conditions' => [
                'applicable_to' => [
                    'type' => 'categories',
                    'category_ids' => [$category->id],
                ],
                'shipping' => [
                    'free_shipping' => false,
                ],
            ],
        ]);

        $cartResponse = $this->postJson(route('api.cart.store'), [
            'product_id' => $product->id,
            'quantity' => '1',
        ]);
        $cartResponse->assertSuccessful();

        $response = $this->postJson(route('api.orders.place'), [
            'branch_id' => $branch->id,
            'payment_method' => 'cod',
            'type' => 'web_takeaway',
            'coupon_id' => $coupon->id,
        ]);

        $response->assertSuccessful();
        $this->assertSame('success', $response->json('status'));

        $orderId = $response->json('data.order_id');
        /** @var Order $order */
        $order = Order::findOrFail($orderId);

        $this->assertGreaterThan(0.0, (float) $order->getAttribute('discount'));
        $this->assertSame($coupon->id, $order->getAttribute('coupon_id'));
    }

    public function test_rejects_order_placement_with_category_coupon_if_cart_items_do_not_belong_to_category(): void
    {
        $category1 = Category::factory()->create();
        $category2 = Category::factory()->create();

        $product = Product::factory()->create([
            'category_id' => $category2->id,
            'base_price' => 100.00,
            'is_active' => true,
        ]);
        $branch = Branch::factory()->create(['is_active' => true]);

        $coupon = Coupon::factory()->create([
            'code' => 'CATONLY',
            'type' => 'percentage',
            'value' => 20.0,
            'is_active' => true,
            'expiry_date' => now()->addMonth(),
            'conditions' => [
                'applicable_to' => [
                    'type' => 'categories',
                    'category_ids' => [$category1->id],
                ],
            ],
        ]);

        $cartResponse = $this->postJson(route('api.cart.store'), [
            'product_id' => $product->id,
            'quantity' => '1',
        ]);
        $cartResponse->assertSuccessful();

        $response = $this->postJson(route('api.orders.place'), [
            'branch_id' => $branch->id,
            'payment_method' => 'cod',
            'type' => 'web_takeaway',
            'coupon_id' => $coupon->id,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'status' => 'error',
            'errors' => [
                'order' => [
                    'This coupon is not applicable to items in your cart',
                ],
            ],
        ]);
    }
}
