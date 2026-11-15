<?php

declare(strict_types=1);

use App\Filament\Widgets\StoreSetupChecklist;
use App\Models\User;
use App\Plugins\OrderManagement\src\Enums\OrderPosStatus;
use App\Plugins\OrderManagement\src\Enums\OrderStatus;
use App\Plugins\OrderManagement\src\Filament\Widgets\LatestOrdersTable;
use App\Plugins\OrderManagement\src\Filament\Widgets\OrdersStatsOverview;
use App\Plugins\OrderManagement\src\Filament\Widgets\TopProductsChart;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Models\OrderItem;
use App\Plugins\PosIntegration\src\Filament\Widgets\PosFailedOrdersTable;
use App\Plugins\ProductReviews\src\Filament\Widgets\PendingReviewsStat;
use App\Plugins\ProductReviews\src\Models\Review;
use Filament\Pages\Dashboard;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
    ]);
    $this->actingAs($this->admin);
});

it('can render the dashboard page for an admin', function () {
    livewire(Dashboard::class)
        ->assertOk();
});

it('computes today count and revenue excluding cancelled orders in OrdersStatsOverview', function () {
    Order::query()->delete();

    // 1 completed order today with total 150.00
    Order::factory()->create([
        'status' => OrderStatus::DELIVERED,
        'total' => 150.00,
        'created_at' => now(),
    ]);

    // 1 cancelled order today with total 200.00 (must be excluded from count & revenue)
    Order::factory()->create([
        'status' => OrderStatus::CANCELLED,
        'total' => 200.00,
        'created_at' => now(),
    ]);

    // 1 pending order today with total 75.00
    Order::factory()->create([
        'status' => OrderStatus::PENDING,
        'total' => 75.00,
        'created_at' => now(),
    ]);

    // 1 order yesterday with total 50.00
    Order::factory()->create([
        'status' => OrderStatus::DELIVERED,
        'total' => 50.00,
        'created_at' => now()->subDay(),
    ]);

    // Non-cancelled orders today = 2 (1 delivered + 1 pending), total revenue = 225.00 (150 + 75)
    livewire(OrdersStatsOverview::class)
        ->assertOk()
        ->assertSee('طلبات اليوم')
        ->assertSee('2')
        ->assertSee('إيرادات اليوم')
        ->assertSee('225.00')
        ->assertSee('طلبات قيد الانتظار')
        ->assertSee('متوسط قيمة الطلب')
        ->assertDontSee('425.00');
});

it('flips pos_status to ready when resend action is called in PosFailedOrdersTable', function () {
    $failedStatus = class_exists(OrderPosStatus::class) ? OrderPosStatus::FAILED->value : 'failed';
    $readyStatus = class_exists(OrderPosStatus::class) ? OrderPosStatus::READY->value : 'ready';

    $order = Order::factory()->create([
        'order_number' => 'ORD-POS-FAIL-01',
        'pos_status' => $failedStatus,
        'pos_failure_reason' => 'Connection timed out',
    ]);

    expect(PosFailedOrdersTable::canView())->toBeTrue();

    livewire(PosFailedOrdersTable::class)
        ->assertOk()
        ->assertSee('ORD-POS-FAIL-01')
        ->assertSee('Connection timed out')
        ->assertTableActionExists('resend')
        ->callTableAction('resend', $order)
        ->assertHasNoTableActionErrors();

    $order->refresh();
    $actualStatus = $order->pos_status instanceof OrderPosStatus ? $order->pos_status->value : $order->pos_status;
    expect($actualStatus)->toBe($readyStatus);
});

it('hides PosFailedOrdersTable when there are no failed orders', function () {
    Order::query()->delete();

    expect(PosFailedOrdersTable::canView())->toBeFalse();
});

it('renders latest orders in LatestOrdersTable', function () {
    Order::query()->delete();

    $order = Order::factory()->create([
        'order_number' => 'ORD-LATEST-99',
        'status' => OrderStatus::DELIVERED,
        'total' => 99.99,
    ]);

    livewire(LatestOrdersTable::class)
        ->assertOk()
        ->assertSee('ORD-LATEST-99')
        ->assertSee('99.99')
        ->assertTableActionExists('view');
});

it('renders TopProductsChart with top sold products excluding cancelled', function () {
    Order::query()->delete();

    $order = Order::factory()->create([
        'status' => OrderStatus::DELIVERED,
        'created_at' => now(),
    ]);

    OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_name' => 'Burger',
        'product_name_ar' => 'برجر كلاسيك',
        'quantity' => 5,
        'total' => 250,
    ]);

    livewire(TopProductsChart::class)
        ->assertOk();
});

it('controls PendingReviewsStat visibility based on pending review count', function () {
    Review::query()->delete();

    expect(PendingReviewsStat::canView())->toBeFalse();

    Review::factory()->create([
        'is_approved' => false,
    ]);

    expect(PendingReviewsStat::canView())->toBeTrue();

    livewire(PendingReviewsStat::class)
        ->assertOk()
        ->assertSee('تقييمات بانتظار الموافقة')
        ->assertSee('1');
});

it('renders StoreSetupChecklist', function () {
    expect(StoreSetupChecklist::canView())->toBeBool();

    livewire(StoreSetupChecklist::class)
        ->assertOk()
        ->assertSee('دليل إعداد المتجر');
});

it('renders OrdersStatsOverview without error when there are orders today and none yesterday', function () {
    Order::query()->delete();

    // 1 completed order today, none yesterday (float 0.0 for revenue and AOV)
    Order::factory()->create([
        'status' => OrderStatus::DELIVERED,
        'total' => 120.00,
        'created_at' => now(),
    ]);

    livewire(OrdersStatsOverview::class)
        ->assertOk()
        ->assertSee('طلبات اليوم')
        ->assertSee('1')
        ->assertSee('120.00')
        ->assertSee('+100% عن أمس');
});

it('uses warning color and neutral description for pending orders stat when pending orders exist', function () {
    Order::query()->delete();

    Order::factory()->create([
        'status' => OrderStatus::PENDING,
        'created_at' => now(),
    ]);

    livewire(OrdersStatsOverview::class)
        ->assertOk()
        ->assertSee('طلبات قيد الانتظار')
        ->assertSee('1')
        ->assertSee('بانتظار التأكيد');
});
