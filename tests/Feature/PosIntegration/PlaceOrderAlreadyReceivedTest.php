<?php

declare(strict_types=1);

/*
 * The POS answers a retried order it already saved with already_received and
 * its current status, and a different order under the same number with 409.
 */

use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\OrderManagement\src\Enums\OrderStatus;
use App\Plugins\OrderManagement\src\Enums\OrderType;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Models\OrderStatusEvent;
use App\Plugins\PosIntegration\src\Services\OrderPOSService;
use App\Plugins\PosIntegration\src\Services\POS\Contracts\OrderPOSPayloadBuilderInterface;
use App\Plugins\PosIntegration\src\Services\POS\Contracts\PosMasterClient;
use App\Plugins\PosIntegration\src\Services\POS\OrderPOSPayloadBuilder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();

    $this->branch = Branch::factory()->create(['link' => 'https://pos.example.com']);
    $this->order = Order::factory()->for($this->branch)->create([
        'shift_id' => 42,
        'type' => OrderType::WEB_DELIVERY,
        'status' => OrderStatus::PENDING,
    ]);

    $builder = mock(OrderPOSPayloadBuilderInterface::class);
    $builder->shouldReceive('build')->andReturn(['user' => [], 'order' => ['orderNumber' => (string) $this->order->order_number]]);
    $this->app->instance(OrderPOSPayloadBuilderInterface::class, $builder);
    $this->app->instance(PosMasterClient::class, mock(PosMasterClient::class));

    $this->service = app(OrderPOSService::class);
});

function fakePosAlreadyReceived(string $status): void
{
    Http::fake([
        'https://pos.example.com/api/web-orders/place-order' => Http::response([
            'message' => 'تم استلام الطلب من قبل',
            'already_received' => true,
            'order' => ['id' => 7, 'order_number' => 'X', 'status' => $status, 'total' => 100],
        ]),
    ]);
}

it('treats an already received order as sent', function () {
    fakePosAlreadyReceived('pending');

    expect($this->service->placeOrder($this->order))->toBe([
        'success' => true,
        'alreadyReceived' => true,
        'posStatus' => 'pending',
    ]);
    expect($this->order->fresh()->status)->toBe(OrderStatus::PENDING)
        ->and(OrderStatusEvent::count())->toBe(0);
});

it('catches the website order up with the POS status', function () {
    fakePosAlreadyReceived('processing');

    $this->service->placeOrder($this->order);

    expect($this->order->fresh()->status)->toBe(OrderStatus::PREPARING)
        ->and(OrderStatusEvent::where('order_id', $this->order->id)->where('source', 'integration')->count())->toBe(1);
});

it('walks forward through allowed steps to a later POS status', function () {
    fakePosAlreadyReceived('completed');

    $this->service->placeOrder($this->order);

    expect($this->order->fresh()->status)->toBe(OrderStatus::DELIVERED)
        ->and(OrderStatusEvent::where('order_id', $this->order->id)->pluck('to_status')->map(fn ($s) => $s instanceof OrderStatus ? $s->value : $s)->all())
        ->toBe(['processing', 'out_for_delivery', 'completed']);
});

it('never moves the website order backwards', function () {
    $this->order->update(['status' => OrderStatus::OUT_FOR_DELIVERY]);
    fakePosAlreadyReceived('processing');

    $this->service->placeOrder($this->order);

    expect($this->order->fresh()->status)->toBe(OrderStatus::OUT_FOR_DELIVERY);
});

it('fails without retrying when the POS holds a different order under the number', function () {
    Http::fake([
        'https://pos.example.com/api/web-orders/place-order' => Http::response([
            'message' => 'رقم الطلب مستخدم بالفعل لطلب مختلف',
            'conflict' => true,
            'mismatches' => ['الإجمالي'],
            'existing_order' => ['id' => 7, 'order_number' => 'X', 'status' => 'pending'],
        ], 409),
    ]);

    $result = $this->service->placeOrder($this->order);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('رقم الطلب مستخدم في الفرع لطلب مختلف')
        ->and($result['message'])->toContain('الإجمالي');
});

it('gives every send of the same order the same idempotency key', function () {
    $builder = app(OrderPOSPayloadBuilder::class);
    $other = Order::factory()->for($this->branch)->create(['type' => OrderType::WEB_DELIVERY]);

    expect($builder->idempotencyKey($this->order))->toBe($builder->idempotencyKey($this->order->fresh()))
        ->and($builder->idempotencyKey($this->order))->not->toBe($builder->idempotencyKey($other))
        ->and(mb_strlen($builder->idempotencyKey($this->order)))->toBeLessThanOrEqual(100);
});

it('catches a takeaway order up to completed without the delivery step', function () {
    $this->order->update(['type' => OrderType::WEB_TAKEAWAY]);
    fakePosAlreadyReceived('completed');

    $this->service->placeOrder($this->order);

    expect($this->order->fresh()->status)->toBe(OrderStatus::DELIVERED)
        ->and(OrderStatusEvent::where('order_id', $this->order->id)->pluck('to_status')->map(fn ($s) => $s instanceof OrderStatus ? $s->value : $s)->all())
        ->toBe(['processing', 'completed']);
});
