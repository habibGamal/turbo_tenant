<?php

declare(strict_types=1);

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\OrderItem;

use function Pest\Livewire\livewire;

it('can render the index page', function () {
    livewire(ListOrders::class)
        ->assertOk();
});

it('can render the view page with all order details', function () {
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
        ->assertSee('completed')
        ->assertSee('card')
        ->assertSee('{"gateway":"paymob","rrn":"123456"}')
        ->assertSee('Temporary POS timeout')
        ->assertSee('Signature Burger');
});
