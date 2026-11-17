<?php

declare(strict_types=1);

/*
 * Takeaway web orders are never out for delivery: the POS completes them
 * straight from processing, and the website must accept that step.
 */

use App\Plugins\OrderManagement\src\Enums\OrderStatus;
use App\Plugins\OrderManagement\src\Enums\OrderType;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Models\OrderStatusEvent;
use App\Plugins\OrderManagement\src\Services\OrderStatusTransitions;
use App\Plugins\OrderManagement\src\Support\OrderManagementSettings;
use App\Plugins\StoreSettings\src\Services\SettingService;

beforeEach(function () {
    app(SettingService::class)->set(OrderManagementSettings::INTEGRATION_TOKEN_KEY, 'takeaway-token');
});

it('lets a takeaway order go from processing to completed', function () {
    $transitions = app(OrderStatusTransitions::class);

    expect($transitions->canTransition(OrderStatus::PREPARING, OrderStatus::DELIVERED, OrderType::WEB_TAKEAWAY))->toBeTrue()
        ->and($transitions->canTransition(OrderStatus::PREPARING, OrderStatus::OUT_FOR_DELIVERY, OrderType::WEB_TAKEAWAY))->toBeTrue()
        ->and($transitions->canTransition(OrderStatus::PREPARING, OrderStatus::CANCELLED, OrderType::WEB_TAKEAWAY))->toBeTrue()
        ->and($transitions->canTransition(OrderStatus::PENDING, OrderStatus::DELIVERED, OrderType::WEB_TAKEAWAY))->toBeFalse();
});

it('keeps delivery orders going through out for delivery', function () {
    $transitions = app(OrderStatusTransitions::class);

    expect($transitions->canTransition(OrderStatus::PREPARING, OrderStatus::DELIVERED, OrderType::WEB_DELIVERY))->toBeFalse()
        ->and($transitions->canTransition(OrderStatus::PREPARING, OrderStatus::DELIVERED))->toBeFalse();
});

it('accepts the POS completing a takeaway order from processing', function () {
    $order = Order::factory()->create(['type' => OrderType::WEB_TAKEAWAY, 'status' => OrderStatus::PREPARING]);

    $this->withToken('takeaway-token')->postJson('/api/order-status', [
        'orderNumber' => $order->order_number,
        'status' => 'completed',
    ])->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::DELIVERED)
        ->and(OrderStatusEvent::where('order_id', $order->id)->where('source', 'integration')->count())->toBe(1);
});

it('still rejects completing a delivery order from processing', function () {
    $order = Order::factory()->create(['type' => OrderType::WEB_DELIVERY, 'status' => OrderStatus::PREPARING]);

    $this->withToken('takeaway-token')->postJson('/api/order-status', [
        'orderNumber' => $order->order_number,
        'status' => 'completed',
    ])->assertStatus(409)
        ->assertJsonPath('from_status', 'processing')
        ->assertJsonPath('to_status', 'completed');

    expect($order->fresh()->status)->toBe(OrderStatus::PREPARING);
});
