<?php

declare(strict_types=1);

namespace Tests\Feature\ComboPackages;

use App\Models\User;
use App\Plugins\ComboPackages\src\Models\Package;
use App\Plugins\ComboPackages\src\Services\PackageAvailabilityService;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\OrderManagement\src\Enums\OrderStatus;
use App\Plugins\OrderManagement\src\Enums\PaymentStatus;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Models\OrderItem;
use Carbon\Carbon;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->branch = Branch::factory()->create();
    $this->service = app(PackageAvailabilityService::class);

    $this->package1 = Package::factory()->create(['name' => 'Combo 1', 'is_active' => true]);
    $this->package2 = Package::factory()->create(['name' => 'Combo 2', 'is_active' => true]);
});

it("counts today's package lines in soldOn", function (): void {
    $today = Carbon::parse('2026-11-09 12:00:00');

    $order = Order::create([
        'order_number' => 'ORD-101',
        'user_id' => $this->user->id,
        'branch_id' => $this->branch->id,
        'status' => OrderStatus::PENDING->value,
        'payment_status' => PaymentStatus::PENDING->value,
        'type' => 'web_delivery',
        'sub_total' => 100,
        'total' => 100,
        'created_at' => $today,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'line_type' => 'package',
        'line_reference_id' => $this->package1->id,
        'product_name' => 'Combo 1',
        'quantity' => 3,
        'unit_price' => 50,
        'total' => 150,
    ]);

    expect($this->service->soldOn($this->package1, $today))->toBe(3)
        ->and($this->service->soldOn($this->package2, $today))->toBe(0);
});

it('excludes cancelled orders, failed-payment orders, other days, and other packages', function (): void {
    $today = Carbon::parse('2026-11-09 12:00:00');
    $yesterday = Carbon::parse('2026-11-08 15:00:00');

    // 1. Valid order today for package 1 (qty 2)
    $validOrder = Order::create([
        'order_number' => 'ORD-V1',
        'user_id' => $this->user->id,
        'branch_id' => $this->branch->id,
        'status' => OrderStatus::PREPARING->value,
        'payment_status' => PaymentStatus::COMPLETED->value,
        'type' => 'web_delivery',
        'created_at' => $today,
    ]);
    OrderItem::create([
        'order_id' => $validOrder->id,
        'line_type' => 'package',
        'line_reference_id' => $this->package1->id,
        'product_name' => 'Combo 1',
        'quantity' => 2,
        'unit_price' => 50,
        'total' => 100,
    ]);

    // 2. Cancelled order today for package 1 (should be excluded)
    $cancelledOrder = Order::create([
        'order_number' => 'ORD-C1',
        'user_id' => $this->user->id,
        'branch_id' => $this->branch->id,
        'status' => OrderStatus::CANCELLED->value,
        'payment_status' => PaymentStatus::COMPLETED->value,
        'type' => 'web_delivery',
        'created_at' => $today,
    ]);
    OrderItem::create([
        'order_id' => $cancelledOrder->id,
        'line_type' => 'package',
        'line_reference_id' => $this->package1->id,
        'product_name' => 'Combo 1',
        'quantity' => 5,
        'unit_price' => 50,
        'total' => 250,
    ]);

    // 3. Failed payment order today for package 1 (should be excluded)
    $failedPaymentOrder = Order::create([
        'order_number' => 'ORD-F1',
        'user_id' => $this->user->id,
        'branch_id' => $this->branch->id,
        'status' => OrderStatus::PENDING->value,
        'payment_status' => PaymentStatus::FAILED->value,
        'type' => 'web_delivery',
        'created_at' => $today,
    ]);
    OrderItem::create([
        'order_id' => $failedPaymentOrder->id,
        'line_type' => 'package',
        'line_reference_id' => $this->package1->id,
        'product_name' => 'Combo 1',
        'quantity' => 4,
        'unit_price' => 50,
        'total' => 200,
    ]);

    // 4. Yesterday order for package 1 (should be excluded from today)
    $yesterdayOrder = Order::create([
        'order_number' => 'ORD-Y1',
        'user_id' => $this->user->id,
        'branch_id' => $this->branch->id,
        'status' => OrderStatus::DELIVERED->value,
        'payment_status' => PaymentStatus::COMPLETED->value,
        'type' => 'web_delivery',
        'created_at' => $yesterday,
    ]);
    OrderItem::create([
        'order_id' => $yesterdayOrder->id,
        'line_type' => 'package',
        'line_reference_id' => $this->package1->id,
        'product_name' => 'Combo 1',
        'quantity' => 10,
        'unit_price' => 50,
        'total' => 500,
    ]);

    // 5. Valid order today for package 2 (should count for package 2, not package 1)
    $package2Order = Order::create([
        'order_number' => 'ORD-P2',
        'user_id' => $this->user->id,
        'branch_id' => $this->branch->id,
        'status' => OrderStatus::DELIVERED->value,
        'payment_status' => PaymentStatus::COMPLETED->value,
        'type' => 'web_delivery',
        'created_at' => $today,
    ]);
    OrderItem::create([
        'order_id' => $package2Order->id,
        'line_type' => 'package',
        'line_reference_id' => $this->package2->id,
        'product_name' => 'Combo 2',
        'quantity' => 7,
        'unit_price' => 60,
        'total' => 420,
    ]);

    // Assert counts for package 1 and package 2 today
    expect($this->service->soldOn($this->package1, $today))->toBe(2)
        ->and($this->service->soldOn($this->package2, $today))->toBe(7)
        ->and($this->service->soldOn($this->package1, $yesterday))->toBe(10);
});

it('computes batch sold counts correctly in soldOnMany', function (): void {
    $today = Carbon::parse('2026-11-09 12:00:00');

    $order = Order::create([
        'order_number' => 'ORD-BATCH-1',
        'user_id' => $this->user->id,
        'branch_id' => $this->branch->id,
        'status' => OrderStatus::PREPARING->value,
        'payment_status' => PaymentStatus::COMPLETED->value,
        'type' => 'web_delivery',
        'created_at' => $today,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'line_type' => 'package',
        'line_reference_id' => $this->package1->id,
        'product_name' => 'Combo 1',
        'quantity' => 3,
        'unit_price' => 50,
        'total' => 150,
    ]);
    OrderItem::create([
        'order_id' => $order->id,
        'line_type' => 'package',
        'line_reference_id' => $this->package2->id,
        'product_name' => 'Combo 2',
        'quantity' => 5,
        'unit_price' => 60,
        'total' => 300,
    ]);

    $counts = $this->service->soldOnMany([$this->package1->id, $this->package2->id], $today);

    expect($counts)->toBe([
        $this->package1->id => 3,
        $this->package2->id => 5,
    ]);
});
