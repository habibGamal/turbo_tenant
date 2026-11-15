<?php

declare(strict_types=1);

namespace Tests\Feature\ComboPackages;

use App\Models\User;
use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\ComboPackages\src\Filament\Resources\Packages\Pages\ListPackages;
use App\Plugins\ComboPackages\src\Filament\Widgets\ComboStatsOverviewWidget;
use App\Plugins\ComboPackages\src\Filament\Widgets\PackageOptionPopularityTable;
use App\Plugins\ComboPackages\src\Filament\Widgets\TopPackagesTable;
use App\Plugins\ComboPackages\src\Models\Package;
use App\Plugins\ComboPackages\src\Reporting\ComboSalesReport;
use App\Plugins\OrderManagement\src\Enums\OrderStatus;
use App\Plugins\OrderManagement\src\Enums\PaymentStatus;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Models\OrderItem;
use App\Plugins\OrderManagement\src\Models\OrderItemComponent;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->admin = User::factory()->create([
        'is_admin' => true,
    ]);
    $this->actingAs($this->admin);

    $this->category = Category::factory()->create();
    $this->product = Product::factory()->create([
        'category_id' => $this->category->id,
        'name' => 'Burger',
        'is_active' => true,
    ]);

    $this->packageA = Package::factory()->create([
        'name' => 'Package Alpha',
        'name_ar' => 'باقة ألفا',
        'price' => 100.0,
        'is_active' => true,
    ]);

    $this->packageB = Package::factory()->create([
        'name' => 'Package Beta',
        'name_ar' => 'باقة بيتا',
        'price' => 200.0,
        'is_active' => true,
    ]);

    $this->report = app(ComboSalesReport::class);
});

it('calculates stats math correctly for valid combo orders', function (): void {
    $order1 = Order::factory()->create([
        'status' => OrderStatus::DELIVERED->value,
        'payment_status' => PaymentStatus::COMPLETED->value,
        'created_at' => now()->subDays(5),
    ]);

    OrderItem::create([
        'order_id' => $order1->id,
        'line_type' => 'package',
        'line_reference_id' => $this->packageA->id,
        'product_name' => $this->packageA->name,
        'quantity' => 2,
        'unit_price' => 100.0,
        'total' => 200.0,
    ]);

    $order2 = Order::factory()->create([
        'status' => OrderStatus::PREPARING->value,
        'payment_status' => PaymentStatus::PENDING->value,
        'created_at' => now()->subDays(2),
    ]);

    OrderItem::create([
        'order_id' => $order2->id,
        'line_type' => 'package',
        'line_reference_id' => $this->packageB->id,
        'product_name' => $this->packageB->name,
        'quantity' => 1,
        'unit_price' => 200.0,
        'total' => 200.0,
    ]);

    $stats = $this->report->getStats(now()->subDays(10), now());

    expect($stats['combos_sold'])->toBe(3)
        ->and($stats['units'])->toBe(3)
        ->and($stats['combo_revenue'])->toEqual(400.0)
        ->and($stats['revenue'])->toEqual(400.0)
        ->and($stats['average_price'])->toEqual(133.33)
        ->and($stats['all_order_revenue'])->toEqual(400.0)
        ->and($stats['combo_share'])->toEqual(1.0)
        ->and($stats['combo_share_percentage'])->toEqual(100.0);
});

it('excludes cancelled orders, failed payment orders, and orders outside range', function (): void {
    $validOrder = Order::factory()->create([
        'status' => OrderStatus::DELIVERED->value,
        'payment_status' => PaymentStatus::COMPLETED->value,
        'created_at' => now()->subDays(3),
    ]);
    OrderItem::create([
        'order_id' => $validOrder->id,
        'line_type' => 'package',
        'line_reference_id' => $this->packageA->id,
        'product_name' => $this->packageA->name,
        'quantity' => 2,
        'unit_price' => 100.0,
        'total' => 200.0,
    ]);

    // Cancelled order - excluded
    $cancelledOrder = Order::factory()->create([
        'status' => OrderStatus::CANCELLED->value,
        'payment_status' => PaymentStatus::COMPLETED->value,
        'created_at' => now()->subDays(3),
    ]);
    OrderItem::create([
        'order_id' => $cancelledOrder->id,
        'line_type' => 'package',
        'line_reference_id' => $this->packageA->id,
        'product_name' => $this->packageA->name,
        'quantity' => 5,
        'unit_price' => 100.0,
        'total' => 500.0,
    ]);

    // Failed payment order - excluded
    $failedPaymentOrder = Order::factory()->create([
        'status' => OrderStatus::PENDING->value,
        'payment_status' => PaymentStatus::FAILED->value,
        'created_at' => now()->subDays(3),
    ]);
    OrderItem::create([
        'order_id' => $failedPaymentOrder->id,
        'line_type' => 'package',
        'line_reference_id' => $this->packageA->id,
        'product_name' => $this->packageA->name,
        'quantity' => 3,
        'unit_price' => 100.0,
        'total' => 300.0,
    ]);

    // Outside date range - excluded
    $oldOrder = Order::factory()->create([
        'status' => OrderStatus::DELIVERED->value,
        'payment_status' => PaymentStatus::COMPLETED->value,
        'created_at' => now()->subDays(40),
    ]);
    OrderItem::create([
        'order_id' => $oldOrder->id,
        'line_type' => 'package',
        'line_reference_id' => $this->packageA->id,
        'product_name' => $this->packageA->name,
        'quantity' => 10,
        'unit_price' => 100.0,
        'total' => 1000.0,
    ]);

    $stats = $this->report->getStats(now()->subDays(10), now());

    expect($stats['combos_sold'])->toBe(2)
        ->and($stats['combo_revenue'])->toEqual(200.0);
});

it('ranks packages by revenue descending', function (): void {
    $order = Order::factory()->create([
        'status' => OrderStatus::DELIVERED->value,
        'payment_status' => PaymentStatus::COMPLETED->value,
        'created_at' => now()->subDays(2),
    ]);

    // Package A: 3 units, 300 total
    OrderItem::create([
        'order_id' => $order->id,
        'line_type' => 'package',
        'line_reference_id' => $this->packageA->id,
        'product_name' => $this->packageA->name,
        'quantity' => 3,
        'unit_price' => 100.0,
        'total' => 300.0,
    ]);

    // Package B: 4 units, 800 total
    OrderItem::create([
        'order_id' => $order->id,
        'line_type' => 'package',
        'line_reference_id' => $this->packageB->id,
        'product_name' => $this->packageB->name,
        'quantity' => 4,
        'unit_price' => 200.0,
        'total' => 800.0,
    ]);

    $sales = $this->report->getPackageSales(now()->subDays(10), now());

    expect($sales)->toHaveCount(2)
        ->and($sales->first()->package_id)->toBe($this->packageB->id)
        ->and($sales->first()->units)->toBe(4)
        ->and($sales->first()->revenue)->toEqual(800.0)
        ->and($sales->last()->package_id)->toBe($this->packageA->id)
        ->and($sales->last()->units)->toBe(3)
        ->and($sales->last()->revenue)->toEqual(300.0);
});

it('groups option popularity by group and option name with pick counts', function (): void {
    $order = Order::factory()->create([
        'status' => OrderStatus::DELIVERED->value,
        'payment_status' => PaymentStatus::COMPLETED->value,
        'created_at' => now()->subDays(2),
    ]);

    $item1 = OrderItem::create([
        'order_id' => $order->id,
        'line_type' => 'package',
        'line_reference_id' => $this->packageA->id,
        'product_name' => $this->packageA->name,
        'quantity' => 1,
        'unit_price' => 100.0,
        'total' => 100.0,
    ]);

    OrderItemComponent::create([
        'order_item_id' => $item1->id,
        'group_name' => 'Meals',
        'product_name' => 'Cheeseburger',
        'variant_name' => 'Double',
        'quantity' => 1,
    ]);

    OrderItemComponent::create([
        'order_item_id' => $item1->id,
        'group_name' => 'Drinks',
        'product_name' => 'Cola',
        'variant_name' => null,
        'quantity' => 1,
    ]);

    $item2 = OrderItem::create([
        'order_id' => $order->id,
        'line_type' => 'package',
        'line_reference_id' => $this->packageA->id,
        'product_name' => $this->packageA->name,
        'quantity' => 1,
        'unit_price' => 100.0,
        'total' => 100.0,
    ]);

    OrderItemComponent::create([
        'order_item_id' => $item2->id,
        'group_name' => 'Meals',
        'product_name' => 'Cheeseburger',
        'variant_name' => 'Double',
        'quantity' => 2,
    ]);

    OrderItemComponent::create([
        'order_item_id' => $item2->id,
        'group_name' => 'Drinks',
        'product_name' => 'Orange Juice',
        'variant_name' => null,
        'quantity' => 1,
    ]);

    $popularity = $this->report->getOptionPopularity($this->packageA->id, now()->subDays(10), now());

    expect($popularity)->toHaveKeys(['Meals', 'Drinks'])
        ->and($popularity['Meals']['Cheeseburger (Double)'])->toBe(3)
        ->and($popularity['Drinks']['Cola'])->toBe(1)
        ->and($popularity['Drinks']['Orange Juice'])->toBe(1);
});

it('counts option picks once per combo unit when a line has quantity above one', function (): void {
    $order = Order::factory()->create([
        'status' => OrderStatus::DELIVERED->value,
        'payment_status' => PaymentStatus::COMPLETED->value,
        'created_at' => now()->subDay(),
    ]);

    $item = OrderItem::create([
        'order_id' => $order->id,
        'line_type' => 'package',
        'line_reference_id' => $this->packageA->id,
        'product_name' => $this->packageA->name,
        'quantity' => 3,
        'unit_price' => 100.0,
        'total' => 300.0,
    ]);

    OrderItemComponent::create(['order_item_id' => $item->id, 'group_name' => 'Drinks', 'product_name' => 'Cola', 'variant_name' => null, 'quantity' => 1]);

    $popularity = $this->report->getOptionPopularity($this->packageA->id, now()->subDays(10), now());

    expect($popularity['Drinks']['Cola'])->toBe(3);
});

it('calculates combo share when there are also product orders', function (): void {
    $order1 = Order::factory()->create([
        'status' => OrderStatus::DELIVERED->value,
        'payment_status' => PaymentStatus::COMPLETED->value,
        'created_at' => now()->subDays(2),
    ]);

    // Combo line: 100 EGP
    OrderItem::create([
        'order_id' => $order1->id,
        'line_type' => 'package',
        'line_reference_id' => $this->packageA->id,
        'product_name' => $this->packageA->name,
        'quantity' => 1,
        'unit_price' => 100.0,
        'total' => 100.0,
    ]);

    $order2 = Order::factory()->create([
        'status' => OrderStatus::DELIVERED->value,
        'payment_status' => PaymentStatus::COMPLETED->value,
        'created_at' => now()->subDays(1),
    ]);

    // Product line: 300 EGP
    OrderItem::create([
        'order_id' => $order2->id,
        'line_type' => 'product',
        'product_id' => $this->product->id,
        'product_name' => $this->product->name,
        'quantity' => 3,
        'unit_price' => 100.0,
        'total' => 300.0,
    ]);

    $stats = $this->report->getStats(now()->subDays(10), now());

    expect($stats['combo_revenue'])->toEqual(100.0)
        ->and($stats['all_order_revenue'])->toEqual(400.0)
        ->and($stats['combo_share'])->toEqual(0.25)
        ->and($stats['combo_share_percentage'])->toEqual(25.0);
});

it('renders widgets inside ListPackages when database has data and when empty', function (): void {
    // 1. When empty:
    Package::query()->delete();
    OrderItem::query()->delete();
    Order::query()->delete();

    livewire(ListPackages::class)
        ->assertSuccessful();

    livewire(ComboStatsOverviewWidget::class)
        ->assertSuccessful();

    livewire(TopPackagesTable::class)
        ->assertSuccessful();

    livewire(PackageOptionPopularityTable::class)
        ->assertSuccessful();

    // 2. With data:
    $pkg = Package::factory()->create(['name' => 'Special Combo', 'price' => 150]);
    $order = Order::factory()->create([
        'status' => OrderStatus::DELIVERED->value,
        'payment_status' => PaymentStatus::COMPLETED->value,
    ]);
    $item = OrderItem::create([
        'order_id' => $order->id,
        'line_type' => 'package',
        'line_reference_id' => $pkg->id,
        'product_name' => $pkg->name,
        'quantity' => 2,
        'unit_price' => 150.0,
        'total' => 300.0,
    ]);
    OrderItemComponent::create([
        'order_item_id' => $item->id,
        'group_name' => 'Combo Choice',
        'product_name' => 'Burger',
        'quantity' => 2,
    ]);

    livewire(ListPackages::class)
        ->assertSuccessful();

    livewire(ComboStatsOverviewWidget::class)
        ->assertSuccessful();

    livewire(TopPackagesTable::class)
        ->assertSuccessful();

    livewire(PackageOptionPopularityTable::class)
        ->assertSuccessful();
});
