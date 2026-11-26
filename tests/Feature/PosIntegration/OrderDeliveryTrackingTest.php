<?php

declare(strict_types=1);

use App\Models\User;
use App\Plugins\OrderManagement\src\Enums\OrderPosStatus;
use App\Plugins\OrderManagement\src\Enums\OrderStatus;
use App\Plugins\OrderManagement\src\Enums\PaymentMethod;
use App\Plugins\OrderManagement\src\Enums\PaymentStatus;
use App\Plugins\OrderManagement\src\Events\OrderFinalized;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\PosIntegration\src\Jobs\SendOrderToPosJob;
use App\Plugins\PosIntegration\src\Models\OrderPosDelivery;
use App\Plugins\PosIntegration\src\Services\OrderDeliveryDispatcher;
use App\Plugins\PosIntegration\src\Services\OrderDeliveryReview;
use App\Plugins\PosIntegration\src\Services\OrderDeliveryTracker;
use App\Plugins\PosIntegration\src\Services\OrderPOSService;
use App\Plugins\PosIntegration\src\Services\POS\Contracts\OrderPOSPayloadBuilderInterface;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\Feature\PosIntegration\Support\PosOrderFixture;

function deliverableOrder(array $attributes = []): Order
{
    return Order::factory()->create(array_merge([
        'status' => OrderStatus::PENDING,
        'type' => 'web_takeaway',
        'payment_method' => PaymentMethod::COD,
        'payment_status' => PaymentStatus::PENDING,
        'pos_status' => OrderPosStatus::READY,
    ], $attributes));
}

it('queues an eligible unsent order once and records the requesting admin', function () {
    Bus::fake([SendOrderToPosJob::class]);
    $admin = User::factory()->create(['is_admin' => true]);
    $order = deliverableOrder();
    $dispatcher = app(OrderDeliveryDispatcher::class);

    $dispatcher->request($order, $admin);
    expect(fn () => $dispatcher->request($order->fresh(), $admin))->toThrow(ValidationException::class);

    Bus::assertDispatchedTimes(SendOrderToPosJob::class, 1);
    $delivery = OrderPosDelivery::firstOrFail();
    expect($delivery->status)->toBe(OrderPosStatus::QUEUED)
        ->and($delivery->requested_by)->toBe($admin->id)
        ->and($delivery->trigger)->toBe('manual')
        ->and($delivery->attempts_count)->toBe(0)
        ->and($order->fresh()->pos_status)->toBe(OrderPosStatus::QUEUED);
});

it('dispatches an offline finalized order without needing a business status change', function () {
    Bus::fake([SendOrderToPosJob::class]);
    $order = deliverableOrder(['pos_status' => OrderPosStatus::NOT_READY]);

    OrderFinalized::dispatch($order);

    Bus::assertDispatchedTimes(SendOrderToPosJob::class, 1);
    expect($order->fresh()->pos_status)->toBe(OrderPosStatus::QUEUED);
});

it('rejects manual delivery for customers and ineligible orders', function () {
    Bus::fake([SendOrderToPosJob::class]);
    $dispatcher = app(OrderDeliveryDispatcher::class);
    $customer = User::factory()->create(['is_admin' => false]);
    $admin = User::factory()->create(['is_admin' => true]);

    expect(fn () => $dispatcher->request(deliverableOrder(), $customer))->toThrow(Illuminate\Auth\Access\AuthorizationException::class);
    foreach ([
        ['status' => OrderStatus::CANCELLED],
        ['status' => OrderStatus::DELIVERED],
        ['payment_method' => PaymentMethod::CARD, 'payment_status' => PaymentStatus::PENDING],
        ['payment_status' => PaymentStatus::REFUNDED],
        ['type' => 'pos'],
        ['pos_status' => OrderPosStatus::SENT],
    ] as $attributes) {
        expect(fn () => $dispatcher->request(deliverableOrder($attributes), $admin))->toThrow(ValidationException::class);
    }
    Bus::assertNothingDispatched();
});

function queuedDeliveryJob(): SendOrderToPosJob
{
    Bus::fake([SendOrderToPosJob::class]);
    Http::preventStrayRequests();
    $order = deliverableOrder();
    $order->branch->update(['link' => 'https://pos.example.com']);
    app()->instance(OrderPOSPayloadBuilderInterface::class, mock(OrderPOSPayloadBuilderInterface::class)
        ->shouldReceive('build')->andReturn(PosOrderFixture::docExamplePayload())->getMock());
    $delivery = app(OrderDeliveryDispatcher::class)->enqueue($order);

    return (new SendOrderToPosJob($order, $delivery->generation))->withFakeQueueInteractions();
}

it('persists successful delivery and refuses to send the same job again', function () {
    $job = queuedDeliveryJob();
    Http::fake([
        '*/api/can-accept-order' => Http::response(['can_accept' => true]),
        '*/api/web-orders/place-order' => Http::response(['message' => 'accepted']),
    ]);
    $job->handle(app(OrderPOSService::class));
    $job->handle(app(OrderPOSService::class));

    $delivery = OrderPosDelivery::firstOrFail();
    expect($delivery->status)->toBe(OrderPosStatus::SENT)
        ->and($delivery->attempts_count)->toBe(1)
        ->and($delivery->failures_count)->toBe(0)
        ->and($delivery->sent_at)->not->toBeNull()
        ->and($delivery->attempts()->first()->submitted_at)->not->toBeNull();
    Http::assertSentCount(2);
});

it('records branch deferrals separately from failures and schedules a long retry after the fifth attempt', function () {
    $job = queuedDeliveryJob();
    Http::fake(['*/api/can-accept-order' => Http::response(['can_accept' => false])]);
    $job->handle(app(OrderPOSService::class));
    $job->assertReleased(3);
    expect(OrderPosDelivery::first()->status)->toBe(OrderPosStatus::RETRY_WAIT)
        ->and(OrderPosDelivery::first()->failures_count)->toBe(0);

    for ($attempt = 2; $attempt <= 5; $attempt++) {
        $job->job->attempts = $attempt;
        $job->handle(app(OrderPOSService::class));
    }

    $delivery = OrderPosDelivery::first();
    expect($delivery->status)->toBe(OrderPosStatus::RETRY_WAIT)
        ->and($delivery->long_retry_round)->toBe(1)
        ->and($delivery->attempts_count)->toBe(5)
        ->and($delivery->failures_count)->toBe(0)
        ->and($delivery->next_retry_at)->not->toBeNull();
    Http::assertSentCount(5);
});

it('counts transient failures and retains their history after a successful retry', function () {
    $job = queuedDeliveryJob();
    Http::fake(['*/api/can-accept-order' => Http::response([], 500)]);
    expect(fn () => $job->handle(app(OrderPOSService::class)))->toThrow(Exception::class);
    expect(OrderPosDelivery::first()->status)->toBe(OrderPosStatus::RETRY_WAIT)
        ->and(OrderPosDelivery::first()->failures_count)->toBe(1);

    Http::swap(new Illuminate\Http\Client\Factory);
    Http::fake([
        '*/api/can-accept-order' => Http::response(['can_accept' => true]),
        '*/api/web-orders/place-order' => Http::response([]),
    ]);
    $job->job->attempts = 2;
    $job->handle(app(OrderPOSService::class));

    $delivery = OrderPosDelivery::first();
    expect($delivery->status)->toBe(OrderPosStatus::SENT)
        ->and($delivery->attempts_count)->toBe(2)
        ->and($delivery->failures_count)->toBe(1)
        ->and($delivery->last_error)->not->toBeNull()
        ->and($delivery->attempts()->oldest('id')->first()->outcome)->toBe('failed');
});

it('requires verification after an unexpected error post-submit and records the admin decision', function () {
    $job = queuedDeliveryJob();
    Http::fake([
        '*/api/can-accept-order' => Http::response(['can_accept' => true]),
        '*/api/web-orders/place-order' => fn () => throw new TypeError('unexpected failure after submit'),
    ]);
    $job->handle(app(OrderPOSService::class));
    $job->assertNotReleased();
    $admin = User::factory()->create(['is_admin' => true]);
    expect(OrderPosDelivery::first()->status)->toBe(OrderPosStatus::NEEDS_VERIFICATION);
    expect(fn () => app(OrderDeliveryDispatcher::class)->request($job->order, $admin))->toThrow(ValidationException::class);

    app(OrderDeliveryReview::class)->verify($job->order, $admin, false, 'تم التواصل مع الفرع وتأكيد عدم استلام الطلب');
    app(OrderDeliveryDispatcher::class)->request($job->order, $admin);

    $delivery = OrderPosDelivery::first();
    expect($delivery->status)->toBe(OrderPosStatus::QUEUED)
        ->and($delivery->failures_count)->toBe(1)
        ->and($delivery->attempts()->where('outcome', 'verified_not_sent')->first()->requested_by)->toBe($admin->id);
    $job->handle(app(OrderPOSService::class));
    $job->failed(new RuntimeException('old job'));
    expect($delivery->fresh()->status)->toBe(OrderPosStatus::QUEUED);
});

it('marks a worker timeout after submission as uncertain and counts it only once', function () {
    $job = queuedDeliveryJob();
    $tracker = app(OrderDeliveryTracker::class);
    $attempt = $tracker->start($job->order, $job->generation, 1);
    $tracker->submitted($attempt);
    $rehydrated = unserialize(serialize($job));
    $rehydrated->failed(new RuntimeException('timeout'));
    $rehydrated->failed(new RuntimeException('timeout'));
    expect(OrderPosDelivery::first()->status)->toBe(OrderPosStatus::NEEDS_VERIFICATION)
        ->and(OrderPosDelivery::first()->failures_count)->toBe(1);
});

it('does not touch records or the POS in another tenant context', function () {
    $job = queuedDeliveryJob();
    $job->tenantId = 'another_tenant';
    $job->handle(app(OrderPOSService::class));
    $job->failed(new RuntimeException('timeout'));
    expect(OrderPosDelivery::first()->status)->toBe(OrderPosStatus::QUEUED)
        ->and(OrderPosDelivery::first()->attempts_count)->toBe(0);
    Http::assertNothingSent();
});

it('stores database jobs centrally and processes them with the original tenant restored', function () {
    config(['queue.default' => 'database', 'queue.connections.database.connection' => null]);
    tenant()->saveQuietly();
    $order = deliverableOrder();
    $order->branch->update(['link' => 'https://pos.example.com']);
    app()->instance(OrderPOSPayloadBuilderInterface::class, mock(OrderPOSPayloadBuilderInterface::class)
        ->shouldReceive('build')->andReturn(PosOrderFixture::docExamplePayload())->getMock());
    Http::preventStrayRequests();
    Http::fake([
        '*/api/can-accept-order' => Http::response(['can_accept' => true]),
        '*/api/web-orders/place-order' => Http::response([]),
    ]);
    app(OrderDeliveryDispatcher::class)->enqueue($order);
    expect(Illuminate\Support\Facades\DB::connection('sqlite')->table('jobs')->count())->toBe(1);
    $tenant = tenant();
    tenancy()->end();
    app('queue.worker')->runNextJob('database', 'default', new Illuminate\Queue\WorkerOptions(sleep: 0));
    tenancy()->initialize($tenant);
    expect(OrderPosDelivery::first()->status)->toBe(OrderPosStatus::SENT);
    Http::assertSentCount(2);
});

it('does not submit an order cancelled during the branch availability check', function () {
    $job = queuedDeliveryJob();
    Http::fake([
        '*/api/can-accept-order' => function () use ($job) {
            $job->order->update(['status' => OrderStatus::CANCELLED]);

            return Http::response(['can_accept' => true]);
        },
        '*/api/web-orders/place-order' => Http::response([]),
    ]);
    $job->handle(app(OrderPOSService::class));
    expect(OrderPosDelivery::first()->status)->toBe(OrderPosStatus::FAILED);
    Http::assertSentCount(1);
});

it('records a queue publication failure without inventing an execution attempt', function () {
    Bus::shouldReceive('dispatch')->once()->andThrow(new RuntimeException('private queue credentials'));
    $order = deliverableOrder();
    app(OrderDeliveryDispatcher::class)->enqueue($order);
    expect(OrderPosDelivery::first()->status)->toBe(OrderPosStatus::FAILED)
        ->and(OrderPosDelivery::first()->attempts_count)->toBe(0)
        ->and(OrderPosDelivery::first()->last_error)->not->toContain('credentials');
});

it('does not advertise an automatic retry when the sync driver cannot perform one', function () {
    Http::preventStrayRequests();
    Http::fake(['*/api/can-accept-order' => Http::response(['can_accept' => false])]);
    $order = deliverableOrder();
    $order->branch->update(['link' => 'https://pos.example.com']);
    app(OrderDeliveryDispatcher::class)->enqueue($order);
    expect(OrderPosDelivery::first()->status)->toBe(OrderPosStatus::FAILED)
        ->and(OrderPosDelivery::first()->attempts_count)->toBe(1)
        ->and(OrderPosDelivery::first()->next_retry_at)->toBeNull();
});

it('does not replay an unexpected error after submission or expose its private details', function () {
    $job = queuedDeliveryJob();
    Http::fake([
        '*/api/can-accept-order' => Http::response(['can_accept' => true]),
        '*/api/web-orders/place-order' => fn () => throw new TypeError('private configuration and credentials'),
    ]);
    $job->handle(app(OrderPOSService::class));
    $job->assertNotReleased();
    expect(OrderPosDelivery::first()->status)->toBe(OrderPosStatus::NEEDS_VERIFICATION)
        ->and(OrderPosDelivery::first()->last_error)->not->toContain('credentials');
});

it('does not publish a delivery whose outer order transaction rolls back', function () {
    Bus::fake([SendOrderToPosJob::class]);
    $order = deliverableOrder();
    $connection = Illuminate\Support\Facades\DB::connection('tenant');
    // Laravel's testing transaction manager treats level one as a test wrapper;
    // the tenant connection has no such wrapper. Exercise production semantics.
    $connection->setTransactionManager(new Illuminate\Database\DatabaseTransactionsManager);
    $connection->beginTransaction();
    app(OrderDeliveryDispatcher::class)->enqueue($order);
    Bus::assertNothingDispatched();
    $connection->rollBack();

    Bus::assertNothingDispatched();
    expect(OrderPosDelivery::count())->toBe(0)
        ->and($order->fresh()->pos_status)->toBe(OrderPosStatus::READY);
});
