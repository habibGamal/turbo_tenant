<?php

declare(strict_types=1);

use App\Models\User;
use App\Plugins\OrderManagement\src\Enums\OrderPosStatus;
use App\Plugins\OrderManagement\src\Enums\OrderStatus;
use App\Plugins\OrderManagement\src\Events\OrderCreated;
use App\Plugins\OrderManagement\src\Filament\Resources\Orders\OrderResource;
use App\Plugins\OrderManagement\src\Filament\Resources\Orders\Pages\ListOrders;
use App\Plugins\OrderManagement\src\Filament\Resources\Orders\Pages\ViewOrder;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Models\OrderItem;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

it('can render the index page', function () {
    livewire(ListOrders::class)
        ->assertOk();
});

it('renders status tabs on the list page and filters correctly for pending tab', function () {
    Order::query()->delete();

    $pendingOrder = Order::factory()->create([
        'status' => OrderStatus::PENDING,
        'order_number' => 'ORD-PENDING-001',
    ]);

    $deliveredOrder = Order::factory()->create([
        'status' => OrderStatus::DELIVERED,
        'order_number' => 'ORD-DELIVERED-001',
    ]);

    $test = livewire(ListOrders::class)
        ->assertOk()
        ->assertSee('الكل')
        ->assertSee('قيد الانتظار')
        ->assertSee('قيد التحضير')
        ->assertSee('خارج للتوصيل')
        ->assertSee('مكتمل')
        ->assertSee('ملغي');

    if (class_exists(OrderPosStatus::class)) {
        $test->assertSee('فشل الإرسال لنقطة البيع');
    }

    $test->assertCanSeeTableRecords([$pendingOrder, $deliveredOrder])
        ->set('activeTab', 'pending')
        ->assertCanSeeTableRecords([$pendingOrder])
        ->assertCanNotSeeTableRecords([$deliveredOrder]);
});

it('returns correct navigation badge count, color and tooltip', function () {
    Order::query()->delete();

    expect(OrderResource::getNavigationBadge())->toBeNull()
        ->and(OrderResource::getNavigationBadgeColor())->toBe('warning')
        ->and(OrderResource::getNavigationBadgeTooltip())->toBe('طلبات قيد الانتظار');

    Order::factory()->count(3)->create([
        'status' => OrderStatus::PENDING,
    ]);

    Order::factory()->count(2)->create([
        'status' => OrderStatus::DELIVERED,
    ]);

    expect(OrderResource::getNavigationBadge())->toBe('3');
});

it('notifies admin users in database when an order is created', function () {
    DB::table('notifications')->truncate();

    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $customer = User::factory()->create([
        'is_admin' => false,
    ]);

    $order = Order::factory()->create([
        'order_number' => 'ORD-NOTIF-1234',
        'customer_name' => 'Ahmed Ali',
        'total' => 350.00,
    ]);

    OrderCreated::dispatch($order);

    assertDatabaseHas('notifications', [
        'notifiable_type' => $admin->getMorphClass(),
        'notifiable_id' => $admin->id,
    ]);

    assertDatabaseMissing('notifications', [
        'notifiable_type' => $customer->getMorphClass(),
        'notifiable_id' => $customer->id,
    ]);

    $notification = DB::table('notifications')->where('notifiable_id', $admin->id)->first();
    expect($notification)->not->toBeNull();

    $data = json_decode((string) $notification->data, true);
    expect($data['title'])->toBe('طلب جديد #ORD-NOTIF-1234');
});

it('removes dead create action from ListOrders and dead edit action from ViewOrder', function () {
    $order = Order::factory()->create();

    livewire(ListOrders::class)
        ->assertActionDoesNotExist('create');

    livewire(ViewOrder::class, [
        'record' => $order->id,
    ])
        ->assertActionDoesNotExist('edit');
});

it('can render the view page with all order details and translated resend to pos action', function () {
    $order = Order::factory()->create([
        'merchant_order_id' => 'MRC-1001',
        'transaction_id' => 'TXN-2001',
        'paymob_order_id' => 'PMB-3001',
        'payment_status' => 'completed',
        'payment_method' => 'card',
        'payment_data' => '{"gateway":"paymob","rrn":"123456"}',
        'pos_status' => 'failed',
        'pos_failure_reason' => 'Temporary POS timeout',
    ]);

    OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_name' => 'Signature Burger',
        'variant_name' => 'Large',
    ]);

    livewire(ViewOrder::class, [
        'record' => $order->id,
    ])
        ->assertOk()
        ->assertSee((string) $order->id)
        ->assertSee($order->order_number)
        ->assertSee('MRC-1001')
        ->assertSee('TXN-2001')
        ->assertSee('PMB-3001')
        // Payment enums render their Arabic labels
        ->assertSee('مكتمل (مدفوع)')
        ->assertSee('بطاقة ائتمانية / بنكية')
        ->assertSee('{"gateway":"paymob","rrn":"123456"}')
        ->assertSee('Temporary POS timeout')
        ->assertSee('Signature Burger')
        ->assertActionVisible('resend_to_pos')
        ->assertSee('إعادة الإرسال لنقطة البيع');
});
