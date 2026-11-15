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
    $this->category = Category::factory()->create();

    $this->product1 = Product::factory()->create([
        'category_id' => $this->category->id,
        'name' => 'Burger Item',
        'base_price' => 50.0,
        'is_active' => true,
    ]);

    $this->product2 = Product::factory()->create([
        'category_id' => $this->category->id,
        'name' => 'Pizza Item',
        'base_price' => 80.0,
        'is_active' => true,
    ]);

    $this->branch = Branch::factory()->create();

    $this->package = Package::factory()->create([
        'name' => 'Full Combo',
        'is_active' => true,
        'max_per_order' => 5,
        'daily_limit' => 10,
        'allowed_order_types' => ['web_delivery'],
    ]);

    $this->group = PackageGroup::create([
        'package_id' => $this->package->id,
        'name' => 'Included',
        'selection_type' => 'all',
        'sort_order' => 0,
    ]);

    $this->item = PackageItem::create([
        'package_group_id' => $this->group->id,
        'product_id' => $this->product1->id,
        'quantity' => 1,
        'price_adjustment' => 0,
    ]);

    $this->package->branches()->attach($this->branch->id);

    PackageAvailability::create([
        'package_id' => $this->package->id,
        'day_of_week' => 1, // Monday
        'starts_at' => '12:00:00',
        'ends_at' => '16:00:00',
    ]);
});

it('returns every new merchandising contract field on show and index endpoints', function (): void {
    Carbon::setTestNow('2026-11-09 13:00:00'); // Monday inside window

    $response = $this->getJson("/api/packages/{$this->package->id}");

    $response->assertOk()
        ->assertJsonPath('data.available_now', true)
        ->assertJsonPath('data.unavailable_reason', null)
        ->assertJsonPath('data.next_available_at', null)
        ->assertJsonPath('data.branch_ids.0', $this->branch->id)
        ->assertJsonPath('data.order_types', ['web_delivery'])
        ->assertJsonPath('data.max_per_order', 5)
        ->assertJsonPath('data.remaining_today', 10)
        ->assertJsonPath('data.availability.0.day_of_week', 1)
        ->assertJsonPath('data.availability.0.starts_at', '12:00')
        ->assertJsonPath('data.availability.0.ends_at', '16:00');
});

it('sets available_now false and outside_hours reason with next_available_at when outside hours', function (): void {
    Carbon::setTestNow('2026-11-09 10:00:00'); // Monday before 12:00

    $response = $this->getJson("/api/packages/{$this->package->id}");

    $response->assertOk()
        ->assertJsonPath('data.available_now', false)
        ->assertJsonPath('data.unavailable_reason', 'outside_hours');

    $nextAvailableAt = $response->json('data.next_available_at');
    expect($nextAvailableAt)->not->toBeNull();
    $parsed = Carbon::parse($nextAvailableAt);
    expect($parsed->format('Y-m-d H:i'))->toBe('2026-11-09 12:00');
});

it('sets available_now false and sold_out_today when daily limit is exhausted', function (): void {
    Carbon::setTestNow('2026-11-09 13:00:00'); // Monday inside window

    $user = User::factory()->create();
    $order = Order::create([
        'order_number' => 'ORD-LIMIT-1',
        'user_id' => $user->id,
        'branch_id' => $this->branch->id,
        'status' => OrderStatus::DELIVERED->value,
        'payment_status' => PaymentStatus::COMPLETED->value,
        'type' => 'web_delivery',
        'created_at' => now(),
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'line_type' => 'package',
        'line_reference_id' => $this->package->id,
        'product_name' => 'Full Combo',
        'quantity' => 10, // Reached daily limit of 10
        'unit_price' => 50,
        'total' => 500,
    ]);

    $response = $this->getJson("/api/packages/{$this->package->id}");

    $response->assertOk()
        ->assertJsonPath('data.available_now', false)
        ->assertJsonPath('data.unavailable_reason', 'sold_out_today')
        ->assertJsonPath('data.remaining_today', 0)
        ->assertJsonPath('data.next_available_at', null);
});

it('filters packages by ?product_id on GET /api/packages', function (): void {
    Carbon::setTestNow('2026-11-09 13:00:00');

    // Create a second package containing product2
    $package2 = Package::factory()->create(['name' => 'Pizza Combo', 'is_active' => true]);
    $group2 = PackageGroup::create(['package_id' => $package2->id, 'name' => 'Pizza Group', 'selection_type' => 'all']);
    PackageItem::create(['package_group_id' => $group2->id, 'product_id' => $this->product2->id, 'quantity' => 1]);

    // Request with product1
    $res1 = $this->getJson("/api/packages?product_id={$this->product1->id}")->assertOk();
    $ids1 = collect($res1->json('data'))->pluck('id')->all();
    expect($ids1)->toContain($this->package->id)
        ->and($ids1)->not->toContain($package2->id);

    // Request with product2
    $res2 = $this->getJson("/api/packages?product_id={$this->product2->id}")->assertOk();
    $ids2 = collect($res2->json('data'))->pluck('id')->all();
    expect($ids2)->toContain($package2->id)
        ->and($ids2)->not->toContain($this->package->id);
});
