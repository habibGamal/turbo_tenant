<?php

declare(strict_types=1);

use App\Models\User;
use App\Plugins\OrderManagement\src\Enums\OrderPosStatus;
use App\Plugins\OrderManagement\src\Filament\Resources\Orders\Pages\ViewOrder;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\PosIntegration\src\Jobs\SendOrderToPosJob;
use App\Plugins\PosIntegration\src\Services\OrderDeliveryDispatcher;
use App\Plugins\PosIntegration\src\Services\OrderDeliveryTracker;
use Illuminate\Support\Facades\Bus;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Bus::fake([SendOrderToPosJob::class]);
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $this->order = Order::factory()->create([
        'status' => 'pending',
        'type' => 'web_takeaway',
        'payment_method' => 'cod',
        'payment_status' => 'pending',
        'pos_status' => 'ready',
    ]);
});

it('resolves the delivery history by its browser component name before tenancy and on refresh', function () {
    $tenant = tenant();
    $alias = 'app.plugins.pos-integration.src.filament.relation-managers.order-delivery-attempts-relation-manager';
    $class = App\Plugins\PosIntegration\src\Filament\RelationManagers\OrderDeliveryAttemptsRelationManager::class;

    // Reproduce a fresh request's component registration before tenant middleware.
    tenancy()->end();
    $registry = new Livewire\Mechanisms\ComponentRegistry;
    app()->instance(Livewire\Mechanisms\ComponentRegistry::class, $registry);
    Filament\Panel::make()->id('pos-registration-test')
        ->plugin(App\Plugins\PosIntegration\src\Filament\PosIntegrationFilamentPlugin::make())
        ->register();

    try {
        expect($registry->getClass($alias))->toBe($class);
    } finally {
        tenancy()->initialize($tenant);
    }

    // Passing the class would let Livewire's test helper register it for us.
    livewire($alias, [
        'ownerRecord' => $this->order,
        'pageClass' => ViewOrder::class,
    ])->assertOk()->call('$refresh')->assertOk()->assertSee('لا توجد محاولات مسجلة');
});

it('renders unavailable historic counts and queues an unsent order from the page', function () {
    livewire(ViewOrder::class, ['record' => $this->order->id])
        ->assertSee('متابعة الإرسال لنقطة البيع')
        ->assertSee('سجل المحاولات السابقة غير متاح')
        ->assertActionVisible('send_to_pos')
        ->callAction('send_to_pos')
        ->assertHasNoActionErrors()
        ->assertSee('في قائمة الإرسال')
        ->assertNotified('تم إدراج الطلب في قائمة الإرسال');

    Bus::assertDispatchedTimes(SendOrderToPosJob::class, 1);
});

it('retries a failed delivery and keeps its failure counters and history', function () {
    $delivery = app(OrderDeliveryDispatcher::class)->enqueue($this->order);
    $tracker = app(OrderDeliveryTracker::class);
    $attempt = $tracker->start($this->order, $delivery->generation, 1);
    $tracker->finish($attempt, OrderPosStatus::FAILED, 'failed', 'المنتج غير موجود بالفرع');

    livewire(ViewOrder::class, ['record' => $this->order->id])
        ->assertSee('المنتج غير موجود بالفرع')
        ->assertSee('المحاولات الفاشلة')
        ->assertActionVisible('resend_to_pos')
        ->callAction('resend_to_pos')
        ->assertHasNoActionErrors()
        ->assertSee('في قائمة الإرسال');

    expect($delivery->fresh()->failures_count)->toBe(1);
    Bus::assertDispatchedTimes(SendOrderToPosJob::class, 2);
});

it('does not offer another send while the job is active', function () {
    app(OrderDeliveryDispatcher::class)->enqueue($this->order);
    livewire(ViewOrder::class, ['record' => $this->order->id])
        ->assertActionHidden('send_to_pos')
        ->assertActionHidden('resend_to_pos')
        ->assertActionHidden('verify_pos_delivery');
});

it('explains unpaid orders and disables sending', function () {
    $this->order->update(['payment_method' => 'card', 'payment_status' => 'pending']);
    livewire(ViewOrder::class, ['record' => $this->order->id])
        ->assertSee('بانتظار تأكيد سداد الطلب.')
        ->assertActionDisabled('send_to_pos');
    Bus::assertNothingDispatched();
});

it('records a confirmed POS receipt through the verification action and displays its audit row', function () {
    $delivery = app(OrderDeliveryDispatcher::class)->enqueue($this->order);
    $tracker = app(OrderDeliveryTracker::class);
    $attempt = $tracker->start($this->order, $delivery->generation, 1);
    $tracker->finish($attempt, OrderPosStatus::NEEDS_VERIFICATION, 'uncertain', 'لم يصل تأكيد الاستلام');

    livewire(ViewOrder::class, ['record' => $this->order->id])
        ->assertActionVisible('verify_pos_delivery')
        ->assertActionHidden('resend_to_pos')
        ->callAction('verify_pos_delivery', data: [
            'received' => 'yes',
            'evidence' => 'أكد مسؤول الفرع وجود الطلب برقم المتجر',
        ])
        ->assertHasNoActionErrors()
        ->assertSee('تم الإرسال');

    $audit = $delivery->attempts()->where('outcome', 'verified_sent')->firstOrFail();
    livewire(App\Plugins\PosIntegration\src\Filament\RelationManagers\OrderDeliveryAttemptsRelationManager::class, [
        'ownerRecord' => $this->order->fresh(),
        'pageClass' => ViewOrder::class,
    ])->assertCanSeeTableRecords([$audit])->assertSee('أكد مسؤول الفرع وجود الطلب برقم المتجر');
    expect($delivery->fresh()->status)->toBe(OrderPosStatus::SENT);
});

it('filters overdue delivery jobs on the orders list', function () {
    $delivery = app(OrderDeliveryDispatcher::class)->enqueue($this->order);
    $delivery->update(['queued_at' => now()->subMinutes(6)]);
    $other = Order::factory()->create(['order_number' => 'CURRENT-UNQUEUED']);
    livewire(App\Plugins\OrderManagement\src\Filament\Resources\Orders\Pages\ListOrders::class)
        ->filterTable('pos_overdue')
        ->assertCanSeeTableRecords([$this->order])
        ->assertCanNotSeeTableRecords([$other]);
});

it('hides delivery controls from non-admin users', function () {
    $this->actingAs(User::factory()->create(['is_admin' => false]));
    livewire(ViewOrder::class, ['record' => $this->order->id])
        ->assertActionHidden('send_to_pos')
        ->assertActionHidden('resend_to_pos')
        ->assertActionHidden('verify_pos_delivery');
    Bus::assertNothingDispatched();
});

it('uses the same audited retry path from the failed-orders dashboard widget', function () {
    $this->order->update(['pos_status' => OrderPosStatus::FAILED, 'pos_failure_reason' => 'فشل سابق']);
    livewire(App\Plugins\PosIntegration\src\Filament\Widgets\PosFailedOrdersTable::class)
        ->callTableAction('resend_to_pos', $this->order)
        ->assertHasNoTableActionErrors()
        ->assertNotified('تم إدراج الطلب في قائمة الإرسال');

    Bus::assertDispatchedTimes(SendOrderToPosJob::class, 1);
    expect($this->order->fresh()->pos_status)->toBe(OrderPosStatus::QUEUED)
        ->and($this->order->fresh()->posDelivery->trigger)->toBe('manual');
});

it('renders without POS tables when the addon is inactive', function () {
    App\Models\TenantPlugin::where('tenant_id', tenant()->id)->where('plugin_id', 'pos-integration')
        ->update(['status' => App\Core\Plugins\Enums\PluginStatus::DEACTIVATED]);
    app(App\Core\Plugins\Services\PluginManager::class)->resetTenant(tenant());
    Illuminate\Support\Facades\Schema::connection('tenant')->dropIfExists('order_pos_delivery_attempts');
    Illuminate\Support\Facades\Schema::connection('tenant')->dropIfExists('order_pos_deliveries');

    try {
        livewire(ViewOrder::class, ['record' => $this->order->id])
            ->assertOk()->assertDontSee('متابعة الإرسال لنقطة البيع')
            ->assertActionHidden('send_to_pos');
        livewire(App\Plugins\OrderManagement\src\Filament\Resources\Orders\Pages\ListOrders::class)->assertOk();
    } finally {
        // Restore the isolated test tenant schema for other tests in this process.
        $migration = require base_path('app/Plugins/PosIntegration/database/migrations/tenant/2026_11_05_000001_create_order_pos_deliveries.php');
        $migration->up();
        $longRetryMigration = require base_path('app/Plugins/PosIntegration/database/migrations/tenant/2026_11_26_000001_add_long_retry_to_order_pos_deliveries.php');
        $longRetryMigration->up();
    }
});

it('displays delayed retry round progress, next retry time, and immediate resend action while waiting for a long retry', function () {
    $delivery = app(OrderDeliveryDispatcher::class)->enqueue($this->order);
    $nextRetryAt = now()->addHours(2);
    $delivery->update([
        'status' => OrderPosStatus::RETRY_WAIT,
        'long_retry_round' => 2,
        'unreachable_since' => now()->subHours(1),
        'next_retry_at' => $nextRetryAt,
        'last_error' => 'الفرع لا يقبل الطلبات حالياً.',
    ]);
    $this->order->update([
        'pos_status' => OrderPosStatus::RETRY_WAIT,
        'pos_failure_reason' => 'الفرع لا يقبل الطلبات حالياً.',
    ]);

    livewire(ViewOrder::class, ['record' => $this->order->id])
        ->assertSee('إعادة المحاولة المؤجلة 2 من 5')
        ->assertSee($nextRetryAt->format('Y-m-d H:i:s'))
        ->assertActionVisible('resend_to_pos')
        ->assertSee('إعادة الإرسال الآن')
        ->callAction('resend_to_pos')
        ->assertHasNoActionErrors()
        ->assertSee('في قائمة الإرسال');

    expect($delivery->fresh()->long_retry_round)->toBe(0)
        ->and($delivery->fresh()->unreachable_since)->toBeNull();
    Bus::assertDispatchedTimes(SendOrderToPosJob::class, 2);
});

it('hides immediate resend while sending and does not treat scheduled long-retry wait as overdue', function () {
    $delivery = app(OrderDeliveryDispatcher::class)->enqueue($this->order);
    $delivery->update([
        'status' => OrderPosStatus::RETRY_WAIT,
        'long_retry_round' => 1,
        'unreachable_since' => now(),
        'next_retry_at' => now()->addHour(),
    ]);
    $this->order->update(['pos_status' => OrderPosStatus::RETRY_WAIT]);

    livewire(App\Plugins\OrderManagement\src\Filament\Resources\Orders\Pages\ListOrders::class)
        ->filterTable('pos_overdue')
        ->assertCanNotSeeTableRecords([$this->order]);

    $delivery->update(['status' => OrderPosStatus::SENDING, 'last_attempt_at' => now()]);
    $this->order->update(['pos_status' => OrderPosStatus::SENDING]);

    livewire(ViewOrder::class, ['record' => $this->order->id])
        ->assertActionHidden('send_to_pos')
        ->assertActionHidden('resend_to_pos');
});
