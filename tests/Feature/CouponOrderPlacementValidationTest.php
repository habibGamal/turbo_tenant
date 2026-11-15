<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CouponOrderPlacementValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_placing_an_order_with_non_existent_coupon_id_returns_422_with_coupon_id_error(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        $product = Product::factory()->create([
            'base_price' => 100.00,
            'is_active' => true,
        ]);

        $this->postJson(route('api.cart.store'), [
            'product_id' => $product->id,
            'quantity' => '1',
        ])->assertSuccessful();

        $response = $this->postJson(route('api.orders.place'), [
            'branch_id' => $branch->id,
            'payment_method' => 'cod',
            'type' => 'web_takeaway',
            'coupon_id' => 999999,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['coupon_id']);
    }
}
