<?php

declare(strict_types=1);

/*
 * Purchase contents shared by the Pixel (placement response) and CAPI: Meta
 * expects whole units, so a weighted line is one unit at the catalog unit
 * price (per kg, add-ons excluded), the same price the catalog feed publishes.
 */

use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\WeightOptionValue;
use App\Plugins\MetaMarketing\src\Capi\OrderPurchaseContents;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('sends a weighted line as one unit at the catalog unit price', function () {
    $order = Order::factory()->create();
    $kofe = Product::factory()->create();
    $quarter = WeightOptionValue::factory()->create(['value' => '0.250', 'label' => '250 جرام']);

    // Kofe Plain: 760 / kg (catalog price) x 0.25 kg + 25 ginseng add-on = 215 paid
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $kofe->id,
        'variant_id' => null,
        'weight_option_value_id' => $quarter->id,
        'weight_multiplier' => 1,
        'quantity' => 0.25,
        'unit_price' => 760,
        'total' => 215,
    ]);

    $purchase = OrderPurchaseContents::fromOrder($order);

    expect($purchase->contents)->toBe([['id' => (string) $kofe->id, 'quantity' => 1, 'item_price' => 760.0]])
        ->and($purchase->numItems)->toBe(1);
});

it('counts every line of a mixed order in num_items', function () {
    $order = Order::factory()->create();
    $half = WeightOptionValue::factory()->create(['value' => '0.500', 'label' => '500 جرام']);
    $quarter = WeightOptionValue::factory()->create(['value' => '0.250', 'label' => '250 جرام']);
    $pack = Product::factory()->create();

    foreach ([[$half, 0.5, 840, 445], [$quarter, 0.25, 760, 215]] as [$weight, $qty, $unit, $total]) {
        OrderItem::factory()->create([
            'order_id' => $order->id, 'product_id' => Product::factory()->create()->id, 'variant_id' => null,
            'weight_option_value_id' => $weight->id, 'weight_multiplier' => 1, 'quantity' => $qty, 'unit_price' => $unit, 'total' => $total,
        ]);
    }
    OrderItem::factory()->create([
        'order_id' => $order->id, 'product_id' => $pack->id, 'variant_id' => null,
        'weight_option_value_id' => null, 'quantity' => 3, 'unit_price' => 190, 'total' => 570,
    ]);

    $purchase = OrderPurchaseContents::fromOrder($order);

    expect($purchase->numItems)->toBe(5) // 1 + 1 weighted lines + 3 packs
        ->and(array_column($purchase->contents, 'quantity'))->toBe([1, 1, 3])
        ->and(array_column($purchase->contents, 'item_price'))->toBe([840.0, 760.0, 190.0]);
});
