<?php

declare(strict_types=1);

namespace Tests\Feature\ComboPackages;

use App\Models\User;
use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\ComboPackages\src\Models\Package;
use App\Plugins\ComboPackages\src\Models\PackageAvailability;
use App\Plugins\ComboPackages\src\Models\PackageGroup;
use App\Plugins\ComboPackages\src\Models\PackageItem;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\OrderManagement\src\Enums\OrderStatus;
use App\Plugins\OrderManagement\src\Enums\PaymentStatus;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Models\OrderItem;
use Carbon\Carbon;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->category = Category::factory()->create();

    $this->product = Product::factory()->create([
        'category_id' => $this->category->id,
        'name' => 'Burger',
        'base_price' => 50.0,
        'is_active' => true,
    ]);

    $this->package = Package::factory()->create([
        'name' => 'Lunch Combo',
        'is_active' => true,
        'price' => 60.0,
        'max_per_order' => 3,
        'daily_limit' => 5,
    ]);

    $this->group = PackageGroup::create([
        'package_id' => $this->package->id,
        'name' => 'Included',
        'selection_type' => 'all',
        'sort_order' => 0,
    ]);

    $this->item = PackageItem::create([
        'package_group_id' => $this->group->id,
        'product_id' => $this->product->id,
        'quantity' => 1,
        'price_adjustment' => 0,
    ]);

    // Monday 12:00 - 16:00
    PackageAvailability::create([
        'package_id' => $this->package->id,
        'day_of_week' => 1,
        'starts_at' => '12:00:00',
        'ends_at' => '16:00:00',
    ]);

    $this->payload = fn (array $overrides = []): array => array_merge([
        'type' => 'package',
        'quantity' => 1,
        'package_id' => $this->package->id,
        'selections' => [],
    ], $overrides);
});

it('rejects adding to cart outside availability hours', function (): void {
    Carbon::setTestNow('2026-11-09 10:00:00'); // Monday before 12:00

    $response = $this->actingAs($this->user)->postJson('/api/cart/items', ($this->payload)());

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['package_id']);
});

it('rejects adding to cart when quantity already in cart + new quantity exceeds max_per_order', function (): void {
    Carbon::setTestNow('2026-11-09 13:00:00'); // Inside window

    // First add: quantity 2 (allowed since max_per_order = 3)
    $this->actingAs($this->user)->postJson('/api/cart/items', ($this->payload)(['quantity' => 2]))
        ->assertStatus(201);

    // Second add: quantity 2 (total would be 4 > 3, rejected!)
    $response = $this->actingAs($this->user)->postJson('/api/cart/items', ($this->payload)(['quantity' => 2]));

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['quantity']);
});

it('rejects adding to cart when new quantity exceeds remaining_today', function (): void {
    Carbon::setTestNow('2026-11-09 13:00:00'); // Inside window

    $branch = Branch::factory()->create();

    // Already sold 4 out of daily_limit 5 today
    $order = Order::create([
        'order_number' => 'ORD-SOLD-4',
        'user_id' => $this->user->id,
        'branch_id' => $branch->id,
        'status' => OrderStatus::DELIVERED->value,
        'payment_status' => PaymentStatus::COMPLETED->value,
        'type' => 'web_delivery',
        'created_at' => now(),
    ]);
    OrderItem::create([
        'order_id' => $order->id,
        'line_type' => 'package',
        'line_reference_id' => $this->package->id,
        'product_name' => 'Lunch Combo',
        'quantity' => 4,
        'unit_price' => 60,
        'total' => 240,
    ]);

    // Remaining today is 1. Trying to add 2 must be rejected!
    $response = $this->actingAs($this->user)->postJson('/api/cart/items', ($this->payload)(['quantity' => 2]));

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['quantity']);
});
