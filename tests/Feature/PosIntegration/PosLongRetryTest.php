<?php

declare(strict_types=1);

use App\Models\User;
use App\Plugins\OrderManagement\src\Enums\OrderPosStatus;
use App\Plugins\OrderManagement\src\Enums\OrderStatus;
use App\Plugins\OrderManagement\src\Enums\PaymentMethod;
use App\Plugins\OrderManagement\src\Enums\PaymentStatus;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Models\OrderItem;
use App\Plugins\PosIntegration\src\Jobs\SendOrderToPosJob;
use App\Plugins\PosIntegration\src\Models\OrderPosDelivery;
use App\Plugins\PosIntegration\src\Services\OrderDeliveryDispatcher;
use App\Plugins\PosIntegration\src\Services\OrderPOSService;
use App\Plugins\PosIntegration\src\Services\POS\OrderPOSPayloadBuilder;
use App\Plugins\PosIntegration\src\Support\PosRetrySchedule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\Feature\PosIntegration\Support\PosOrderFixture;

function createPosRetryOrder(array $attributes = []): Order
{
    $order = PosOrderFixture::createDocExampleOrder();
    $order->update(array_merge([
        'status' => OrderStatus::PENDING,
        'payment_method' => PaymentMethod::COD,
        'payment_status' => PaymentStatus::PENDING,
        'pos_status' => OrderPosStatus::READY,
    ], $attributes));

    return $order->fresh();
}

function enqueueWithFakeQueue(Order $order): OrderPosDelivery
{
    Queue::fake();
    $delivery = app(OrderDeliveryDispatcher::class)->enqueue($order);

    // Clear the initial immediate job so assertions inspect only newly scheduled round jobs.
    Queue::fake();

    return $delivery;
}

function runExhaustedRound(Order $order, string $generation): void
{
    $job = (new SendOrderToPosJob($order, $generation))->withFakeQueueInteractions();
    for ($attempt = 1; $attempt <= $job->tries; $attempt++) {
        $job->job->attempts = $attempt;
        try {
            $job->handle(app(OrderPOSService::class));
        } catch (Throwable $e) {
            if ($attempt === $job->tries) {
                throw $e;
            }
        }
    }
}

it('defines the cumulative 24h retry schedule in one place', function () {
    expect(PosRetrySchedule::DELAYS)->toBe([3600, 7200, 14400, 28800, 32400])
        ->and(array_sum(PosRetrySchedule::DELAYS))->toBe(86400)
        ->and(PosRetrySchedule::maxRounds())->toBe(5);
});

it('schedules 5 delayed rounds over 24h and marks FAILED with the 24h reason after the 5th round', function (string $failureMode) {
    Http::preventStrayRequests();

    $order = createPosRetryOrder([
        'shift_id' => $failureMode === 'shift_id' ? null : 42,
    ]);
    $delivery = enqueueWithFakeQueue($order);

    Http::fake(match ($failureMode) {
        'can_accept_false' => [
            '*/api/can-accept-order' => Http::response(['can_accept' => false], 200),
        ],
        'can_accept_throws' => [
            '*/api/can-accept-order' => Http::response([], 500),
        ],
        'shift_id' => [
            '*/api/can-accept-order' => Http::response(['can_accept' => true], 200),
            '*/api/get-shift-id' => Http::response(['shift_id' => null], 200),
        ],
    });

    $expectedDelays = [3600, 7200, 14400, 28800, 32400];

    foreach ($expectedDelays as $index => $expectedDelay) {
        Queue::fake();
        runExhaustedRound($order, $delivery->generation);

        $fresh = $delivery->fresh();
        $expectedRound = $index + 1;

        expect($fresh->status)->toBe(OrderPosStatus::RETRY_WAIT)
            ->and($fresh->long_retry_round)->toBe($expectedRound)
            ->and($fresh->run_attempts_count)->toBe(0)
            ->and($fresh->unreachable_since)->not->toBeNull()
            ->and($fresh->next_retry_at)->not->toBeNull()
            ->and($order->fresh()->pos_status)->toBe(OrderPosStatus::RETRY_WAIT);

        Queue::assertPushed(SendOrderToPosJob::class, function (SendOrderToPosJob $job) use ($delivery, $expectedDelay): bool {
            return $job->generation === $delivery->generation && $job->delay === $expectedDelay;
        });
        Queue::assertPushed(SendOrderToPosJob::class, 1);
    }

    // 5th long-retry round ends unreachable -> terminal FAILED with 24h reason, no more delayed jobs.
    Queue::fake();
    runExhaustedRound($order, $delivery->generation);

    $finalDelivery = $delivery->fresh();
    $finalOrder = $order->fresh();

    expect($finalDelivery->status)->toBe(OrderPosStatus::FAILED)
        ->and($finalDelivery->long_retry_round)->toBe(5)
        ->and($finalDelivery->next_retry_at)->toBeNull()
        ->and($finalDelivery->last_error)->toBe(PosRetrySchedule::EXHAUSTED_REASON)
        ->and($finalOrder->pos_status)->toBe(OrderPosStatus::FAILED)
        ->and($finalOrder->pos_failure_reason)->toBe(PosRetrySchedule::EXHAUSTED_REASON);

    Queue::assertNothingPushed();
})->with([
    'canAcceptOrder returns false' => ['can_accept_false'],
    'canAcceptOrder throws' => ['can_accept_throws'],
    'getShiftId fails' => ['shift_id'],
]);

it('marks SENT and resets long retry counters when the POS comes back in a later round', function () {
    Http::preventStrayRequests();
    $order = createPosRetryOrder();
    $delivery = enqueueWithFakeQueue($order);

    Http::fake([
        '*/api/can-accept-order' => Http::response(['can_accept' => false], 200),
    ]);

    runExhaustedRound($order, $delivery->generation);

    expect($delivery->fresh()->long_retry_round)->toBe(1)
        ->and($delivery->fresh()->unreachable_since)->not->toBeNull();

    Http::swap(new Illuminate\Http\Client\Factory);
    Http::fake([
        '*/api/can-accept-order' => Http::response(['can_accept' => true], 200),
        '*/api/web-orders/place-order' => Http::response(['message' => 'ok'], 200),
    ]);

    $delayedJob = (new SendOrderToPosJob($order, $delivery->generation))->withFakeQueueInteractions();
    $delayedJob->handle(app(OrderPOSService::class));

    $fresh = $delivery->fresh();
    expect($fresh->status)->toBe(OrderPosStatus::SENT)
        ->and($fresh->long_retry_round)->toBe(0)
        ->and($fresh->unreachable_since)->toBeNull()
        ->and($fresh->next_retry_at)->toBeNull()
        ->and($order->fresh()->pos_status)->toBe(OrderPosStatus::SENT)
        ->and($order->fresh()->pos_failure_reason)->toBeNull();
});

it('long-retries PosDeliveryOutcomeUnknown with the exact same idempotencyKey and marks SENT on already_received', function (string $errorType) {
    Http::preventStrayRequests();
    $order = createPosRetryOrder();
    $delivery = enqueueWithFakeQueue($order);
    $expectedKey = app(OrderPOSPayloadBuilder::class)->idempotencyKey($order);
    $sentKeys = [];

    Http::fake([
        '*/api/can-accept-order' => Http::response(['can_accept' => true], 200),
        '*/api/web-orders/place-order' => function (Request $request) use (&$sentKeys, $errorType) {
            $sentKeys[] = $request->data()['order']['idempotencyKey'] ?? null;

            if ($errorType === 'connection_error') {
                throw new ConnectionException('Connection timed out');
            }

            return Http::response(['message' => 'Internal Error'], 502);
        },
    ]);

    runExhaustedRound($order, $delivery->generation);

    expect($delivery->fresh()->status)->toBe(OrderPosStatus::RETRY_WAIT)
        ->and($delivery->fresh()->long_retry_round)->toBe(1)
        ->and($sentKeys)->toHaveCount(5);

    Queue::assertPushed(SendOrderToPosJob::class, function (SendOrderToPosJob $job) use ($delivery): bool {
        return $job->generation === $delivery->generation && $job->delay === 3600;
    });

    // Next round replays with the SAME idempotencyKey and POS returns already_received.
    Http::swap(new Illuminate\Http\Client\Factory);
    Http::fake([
        '*/api/can-accept-order' => Http::response(['can_accept' => true], 200),
        '*/api/web-orders/place-order' => function (Request $request) use (&$sentKeys) {
            $sentKeys[] = $request->data()['order']['idempotencyKey'] ?? null;

            return Http::response([
                'message' => 'تم استلام الطلب من قبل',
                'already_received' => true,
                'order' => ['id' => 99, 'order_number' => $request->data()['order']['orderNumber'], 'status' => 'processing'],
            ], 200);
        },
    ]);

    $delayedJob = (new SendOrderToPosJob($order, $delivery->generation))->withFakeQueueInteractions();
    $delayedJob->handle(app(OrderPOSService::class));

    expect($sentKeys)->toHaveCount(6)
        ->and(array_unique($sentKeys))->toBe([$expectedKey])
        ->and($delivery->fresh()->status)->toBe(OrderPosStatus::SENT)
        ->and($delivery->fresh()->long_retry_round)->toBe(0)
        ->and($delivery->fresh()->unreachable_since)->toBeNull()
        ->and($order->fresh()->pos_status)->toBe(OrderPosStatus::SENT)
        ->and($order->fresh()->status)->toBe(OrderStatus::PREPARING);
})->with([
    'POS 5xx' => ['pos_5xx'],
    'connection error' => ['connection_error'],
]);

it('never long-retries 409 conflict or unknown products or 4xx errors', function (string $scenario) {
    Http::preventStrayRequests();
    $order = createPosRetryOrder();
    $delivery = enqueueWithFakeQueue($order);

    if ($scenario === 'missing_products') {
        $masterClient = mock(App\Plugins\PosIntegration\src\Services\POS\Contracts\PosMasterClient::class);
        $masterClient->shouldReceive('getProductsByReferences')->andReturn([['name' => 'برجر', 'posRef' => 'MISSING']]);
        $this->app->instance(App\Plugins\PosIntegration\src\Services\POS\Contracts\PosMasterClient::class, $masterClient);
    }

    Http::fake([
        '*/api/can-accept-order' => Http::response(['can_accept' => true], 200),
        '*/api/web-orders/place-order' => match ($scenario) {
            'conflict_409' => Http::response(['conflict' => true, 'mismatches' => ['الإجمالي']], 409),
            'missing_products' => Http::response(['error' => 'منتجات غير موجودة: ["MISSING"]'], 400),
            'client_422' => Http::response(['message' => 'بيانات الطلب غير صالحة'], 422),
        },
    ]);

    if ($scenario === 'client_422') {
        $job = (new SendOrderToPosJob($order, $delivery->generation))->withFakeQueueInteractions();
        for ($attempt = 1; $attempt <= $job->tries; $attempt++) {
            $job->job->attempts = $attempt;
            expect(fn () => $job->handle(app(OrderPOSService::class)))->toThrow(Exception::class);
        }
        $job->failed(new Exception('بيانات الطلب غير صالحة'));
    } else {
        $job = (new SendOrderToPosJob($order, $delivery->generation))->withFakeQueueInteractions();
        $job->handle(app(OrderPOSService::class));
    }

    expect($delivery->fresh()->status)->toBe(OrderPosStatus::FAILED)
        ->and($delivery->fresh()->long_retry_round)->toBe(0)
        ->and($order->fresh()->pos_status)->toBe(OrderPosStatus::FAILED);
    Queue::assertNothingPushed();
})->with([
    '409 conflict' => ['conflict_409'],
    'unknown products' => ['missing_products'],
    '422 client error' => ['client_422'],
]);

it('stops without calling POS when the order is cancelled during the wait', function () {
    Http::preventStrayRequests();
    $order = createPosRetryOrder();
    $delivery = enqueueWithFakeQueue($order);

    Http::fake([
        '*/api/can-accept-order' => Http::response(['can_accept' => false], 200),
    ]);

    runExhaustedRound($order, $delivery->generation);
    expect($delivery->fresh()->status)->toBe(OrderPosStatus::RETRY_WAIT);

    $order->update(['status' => OrderStatus::CANCELLED]);

    Queue::fake();
    Http::swap(new Illuminate\Http\Client\Factory);
    Http::fake();

    $delayedJob = (new SendOrderToPosJob($order, $delivery->generation))->withFakeQueueInteractions();
    $delayedJob->handle(app(OrderPOSService::class));

    Http::assertNothingSent();
    Queue::assertNothingPushed();
    expect($delivery->fresh()->status)->toBe(OrderPosStatus::FAILED)
        ->and($order->fresh()->pos_status)->toBe(OrderPosStatus::FAILED)
        ->and($order->fresh()->pos_failure_reason)->toBe('لا يمكن إرسال طلب ملغي أو مكتمل.');
});

it('allows manual resend during long-retry wait, rotates generation, and fences the old delayed job', function () {
    Http::preventStrayRequests();
    $admin = User::factory()->create(['is_admin' => true]);
    $order = createPosRetryOrder();
    $delivery = enqueueWithFakeQueue($order);
    $oldGeneration = $delivery->generation;

    Http::fake([
        '*/api/can-accept-order' => Http::response(['can_accept' => false], 200),
    ]);

    runExhaustedRound($order, $oldGeneration);
    expect($delivery->fresh()->status)->toBe(OrderPosStatus::RETRY_WAIT)
        ->and($delivery->fresh()->long_retry_round)->toBe(1);

    // Manual resend is rejected while SENDING.
    $delivery->update(['status' => OrderPosStatus::SENDING]);
    $order->update(['pos_status' => OrderPosStatus::SENDING]);
    expect(fn () => app(OrderDeliveryDispatcher::class)->request($order->fresh(), $admin))->toThrow(ValidationException::class);

    // Restore RETRY_WAIT and perform manual resend.
    $delivery->update(['status' => OrderPosStatus::RETRY_WAIT]);
    $order->update(['pos_status' => OrderPosStatus::RETRY_WAIT]);

    Queue::fake();
    $updatedDelivery = app(OrderDeliveryDispatcher::class)->request($order->fresh(), $admin);

    expect($updatedDelivery->generation)->not->toBe($oldGeneration)
        ->and($updatedDelivery->status)->toBe(OrderPosStatus::QUEUED)
        ->and($updatedDelivery->long_retry_round)->toBe(0)
        ->and($updatedDelivery->unreachable_since)->toBeNull();

    Queue::assertPushed(SendOrderToPosJob::class, function (SendOrderToPosJob $job) use ($updatedDelivery): bool {
        return $job->generation === $updatedDelivery->generation && $job->delay === null;
    });

    // Old delayed job wakes up later -> no-ops via generation check.
    Http::swap(new Illuminate\Http\Client\Factory);
    Http::fake();

    $oldDelayedJob = (new SendOrderToPosJob($order, $oldGeneration))->withFakeQueueInteractions();
    $oldDelayedJob->handle(app(OrderPOSService::class));

    Http::assertNothingSent();
    expect($delivery->fresh()->generation)->toBe($updatedDelivery->generation)
        ->and($delivery->fresh()->status)->toBe(OrderPosStatus::QUEUED);
});

it('does not schedule long retries on the sync queue connection', function () {
    Http::preventStrayRequests();
    config(['queue.default' => 'sync']);

    $order = createPosRetryOrder();
    Http::fake([
        '*/api/can-accept-order' => Http::response(['can_accept' => false], 200),
    ]);

    $delivery = app(OrderDeliveryDispatcher::class)->enqueue($order);

    expect($delivery->fresh()->status)->toBe(OrderPosStatus::FAILED)
        ->and($delivery->fresh()->long_retry_round)->toBe(0)
        ->and($delivery->fresh()->next_retry_at)->toBeNull();

    $order2 = Order::factory()->for($order->branch)->create([
        'order_number' => 'WEB-SYNC-UNKNOWN',
        'shift_id' => 42,
        'type' => $order->type,
        'status' => OrderStatus::PENDING,
        'payment_method' => PaymentMethod::COD,
        'payment_status' => PaymentStatus::PENDING,
        'pos_status' => OrderPosStatus::READY,
    ]);
    OrderItem::factory()->create([
        'order_id' => $order2->id,
        'product_id' => $order->items()->first()->product_id,
        'quantity' => 1,
    ]);
    Http::swap(new Illuminate\Http\Client\Factory);
    Http::fake([
        '*/api/can-accept-order' => Http::response(['can_accept' => true], 200),
        '*/api/web-orders/place-order' => Http::response([], 500),
    ]);

    $delivery2 = app(OrderDeliveryDispatcher::class)->enqueue($order2);

    expect($delivery2->fresh()->status)->toBe(OrderPosStatus::NEEDS_VERIFICATION)
        ->and($delivery2->fresh()->long_retry_round)->toBe(0);
});

it('stores delayed round jobs centrally on pos-database when database queue has no connection override', function () {
    config(['queue.default' => 'database', 'queue.connections.database.connection' => null]);
    tenant()->saveQuietly();
    Http::preventStrayRequests();

    $order = createPosRetryOrder();
    Http::fake([
        '*/api/can-accept-order' => Http::response(['can_accept' => false], 200),
    ]);

    $delivery = app(OrderDeliveryDispatcher::class)->enqueue($order);
    $jobRow = DB::connection('sqlite')->table('jobs')->first();
    expect($jobRow)->not->toBeNull();

    $job = (new SendOrderToPosJob($order, $delivery->generation))->withFakeQueueInteractions();
    $job->job->attempts = 5;
    $job->handle(app(OrderPOSService::class));

    $rows = DB::connection('sqlite')->table('jobs')->orderBy('id')->get();
    expect($rows)->toHaveCount(2)
        ->and($rows[1]->available_at - $rows[1]->created_at)->toBeGreaterThanOrEqual(3595);
});

it('does not schedule a long retry for a pre-submit plain Exception on the fifth attempt', function () {
    $order = createPosRetryOrder();
    $delivery = enqueueWithFakeQueue($order);

    $posService = mock(OrderPOSService::class);
    $posService->shouldReceive('canAcceptOrder')->once()->andReturn(true);
    $posService->shouldReceive('placeOrder')->once()->andThrow(new Exception('Order does not have a branch assigned'));

    $job = (new SendOrderToPosJob($order, $delivery->generation))->withFakeQueueInteractions();
    $job->job->attempts = 5;

    expect(fn () => $job->handle($posService))->toThrow(Exception::class, 'Order does not have a branch assigned');
    $job->failed(new Exception('Order does not have a branch assigned'));

    expect($delivery->fresh()->status)->toBe(OrderPosStatus::FAILED)
        ->and($delivery->fresh()->long_retry_round)->toBe(0)
        ->and($delivery->fresh()->next_retry_at)->toBeNull()
        ->and($order->fresh()->pos_status)->toBe(OrderPosStatus::FAILED);
    Queue::assertNothingPushed();
});
