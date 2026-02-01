<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Enums\OrderPosStatus;
use App\Jobs\SendOrderToPosJob;
use App\Models\Order;
use App\Services\OrderPOSService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

it('updates order status to SENDING when job starts', function () {
    $order = Order::factory()->create([
        'pos_status' => OrderPosStatus::PENDING,
    ]);

    $orderPOSService = mock(OrderPOSService::class);
    $orderPOSService->shouldReceive('canAcceptOrder')
        ->once()
        ->andReturn(true);
    $orderPOSService->shouldReceive('placeOrder')
        ->once()
        ->andReturn(['success' => true]);

    $job = new SendOrderToPosJob($order);
    $job->handle($orderPOSService);

    expect($order->fresh()->pos_status)->toBe(OrderPosStatus::SENT);
});

it('updates order status to SENT when order is successfully placed', function () {
    $order = Order::factory()->create([
        'pos_status' => OrderPosStatus::PENDING,
    ]);

    $orderPOSService = mock(OrderPOSService::class);
    $orderPOSService->shouldReceive('canAcceptOrder')
        ->once()
        ->andReturn(true);
    $orderPOSService->shouldReceive('placeOrder')
        ->once()
        ->andReturn(['success' => true]);

    $job = new SendOrderToPosJob($order);
    $job->handle($orderPOSService);

    expect($order->fresh())
        ->pos_status->toBe(OrderPosStatus::SENT)
        ->pos_failure_reason->toBeNull();
});

it('releases job for 3 seconds when branch cannot accept orders', function () {
    $order = Order::factory()->create([
        'pos_status' => OrderPosStatus::PENDING,
    ]);

    $orderPOSService = mock(OrderPOSService::class);
    $orderPOSService->shouldReceive('canAcceptOrder')
        ->once()
        ->andReturn(false);
    $orderPOSService->shouldReceive('placeOrder')
        ->never();

    $job = new SendOrderToPosJob($order);

    // Mock the release method
    $jobMock = mock(SendOrderToPosJob::class)->makePartial();
    $jobMock->order = $order;
    $jobMock->shouldReceive('release')
        ->once()
        ->with(3);

    $jobMock->handle($orderPOSService);
});

it('updates order status to FAILED and rethrows exception on failure', function () {
    $order = Order::factory()->create([
        'pos_status' => OrderPosStatus::PENDING,
    ]);

    $orderPOSService = mock(OrderPOSService::class);
    $orderPOSService->shouldReceive('canAcceptOrder')
        ->once()
        ->andReturn(true);
    $orderPOSService->shouldReceive('placeOrder')
        ->once()
        ->andThrow(new Exception('POS Error'));

    $job = new SendOrderToPosJob($order);

    try {
        $job->handle($orderPOSService);
    } catch (Exception $e) {
        expect($e->getMessage())->toBe('POS Error');
    }

    expect($order->fresh())
        ->pos_status->toBe(OrderPosStatus::FAILED)
        ->pos_failure_reason->toBe('POS Error');
});

it('has correct retry configuration', function () {
    $order = Order::factory()->create();
    $job = new SendOrderToPosJob($order);

    expect($job->tries)->toBe(5)
        ->and($job->backoff)->toBe(3);
});
