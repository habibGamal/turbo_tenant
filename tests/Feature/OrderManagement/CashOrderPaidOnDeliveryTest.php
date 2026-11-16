<?php

declare(strict_types=1);

/*
 * A cash-on-delivery order is marked paid («مكتمل (مدفوع)») once it is
 * completed, including when the POS reports the completion.
 */

use App\Plugins\OrderManagement\src\Enums\OrderStatus;
use App\Plugins\OrderManagement\src\Enums\PaymentMethod;
use App\Plugins\OrderManagement\src\Enums\PaymentStatus;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Support\OrderManagementSettings;
use App\Plugins\StoreSettings\src\Services\SettingService;

function deliveryOrder(PaymentMethod $method, OrderStatus $status = OrderStatus::OUT_FOR_DELIVERY): Order
{
    return Order::factory()->create([
        'status' => $status,
        'payment_method' => $method,
        'payment_status' => PaymentStatus::PENDING,
    ]);
}

it('marks a cash order paid when the POS completes it', function () {
    app(SettingService::class)->set(OrderManagementSettings::INTEGRATION_TOKEN_KEY, 'cash-paid-token-123');
    $order = deliveryOrder(PaymentMethod::COD);

    $this->withToken('cash-paid-token-123')->postJson('/api/order-status', [
        'orderNumber' => $order->order_number,
        'status' => 'completed',
    ])->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::DELIVERED)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::COMPLETED);
});

it('marks a cash order paid whatever path completes it', function () {
    $order = deliveryOrder(PaymentMethod::COD);

    $order->update(['status' => OrderStatus::DELIVERED]);

    expect($order->fresh()->payment_status)->toBe(PaymentStatus::COMPLETED);
});

it('leaves a cash order unpaid until it is completed', function () {
    $order = deliveryOrder(PaymentMethod::COD, OrderStatus::PENDING);

    $order->update(['status' => OrderStatus::PREPARING]);

    expect($order->fresh()->payment_status)->toBe(PaymentStatus::PENDING);
});

it('does not touch the payment status of online-paid orders', function () {
    $order = deliveryOrder(PaymentMethod::CARD);

    $order->update(['status' => OrderStatus::DELIVERED]);

    expect($order->fresh()->payment_status)->toBe(PaymentStatus::PENDING);
});
