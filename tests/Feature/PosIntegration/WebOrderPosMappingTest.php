<?php

declare(strict_types=1);

/*
 * The POS order must read like the website order (order 160): add-ons are
 * their own lines with the website count and only an «إضافة على» note, weighted items
 * keep their weight, and names and the address are in Arabic.
 */

use App\Plugins\CatalogProducts\src\Models\ExtraOptionItem;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\WeightOptionValue;
use App\Plugins\LocationsDelivery\src\Models\Area;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\LocationsDelivery\src\Models\Governorate;
use App\Plugins\OrderManagement\src\Enums\OrderType;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Models\OrderItem;
use App\Plugins\OrderManagement\src\Models\OrderItemExtra;
use App\Plugins\PosIntegration\src\Models\ProductPosMapping;
use App\Plugins\PosIntegration\src\Services\POS\OrderPOSPayloadBuilder;
use App\Plugins\PosIntegration\src\Services\POS\POSCustomerDataFormatter;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->ginseng = ExtraOptionItem::factory()->create(['name' => 'Adding ginseng 1g', 'name_ar' => 'اضافة جينسينج 1جم', 'pos_mapping_type' => 'pos_item']);
    ProductPosMapping::factory()->create(['extra_option_item_id' => $this->ginseng->id, 'pos_item_id' => 'GINSENG', 'branch_id' => $this->branch->id]);
    $this->order = Order::factory()->create(['branch_id' => $this->branch->id, 'type' => OrderType::WEB_DELIVERY, 'shift_id' => 1]);
});

function mappedProduct(string $ref, Branch $branch): Product
{
    $product = Product::factory()->create();
    ProductPosMapping::factory()->create(['product_id' => $product->id, 'pos_item_id' => $ref, 'branch_id' => $branch->id]);

    return $product;
}

function addGinseng(OrderItem $item, ExtraOptionItem $ginseng, int $quantity = 1): void
{
    OrderItemExtra::factory()->create([
        'order_item_id' => $item->id,
        'extra_option_item_id' => $ginseng->id,
        'extra_name' => 'Adding ginseng 1g',
        'extra_name_ar' => 'اضافة جينسينج 1جم',
        'quantity' => $quantity,
    ]);
}

it('sends a weighted item with its add-on as x1, not x the weight in kg', function () {
    $half = WeightOptionValue::factory()->create(['value' => '0.500', 'label' => '500 جرام']);
    $item = OrderItem::factory()->create([
        'order_id' => $this->order->id,
        'product_id' => mappedProduct('KOFE-SPICED', $this->branch)->id,
        'weight_option_value_id' => $half->id,
        'weight_multiplier' => 1,
        'weight_label' => '500 جرام',
        'variant_name' => 'Medium',
        'variant_name_ar' => 'وسط',
        'product_name_ar' => 'كوف محوج',
        'quantity' => 0.5,
        'notes' => null,
    ]);
    addGinseng($item, $this->ginseng);

    $items = app(OrderPOSPayloadBuilder::class)->build($this->order->fresh())['order']['items'];

    expect($items)->toBe([
        [
            'quantity' => 0.5,
            'notes' => 'json::'.json_encode(['الوزن' => '(1 x) 500 جرام', 'النوع' => 'وسط'], JSON_UNESCAPED_UNICODE),
            'posRefObj' => [['productRef' => 'KOFE-SPICED', 'quantity' => 1]],
        ],
        [
            'quantity' => 1,
            'notes' => 'إضافة على: كوف محوج',
            'posRefObj' => [['productRef' => 'GINSENG', 'quantity' => 1]],
        ],
    ]);
});

it('counts a piece item add-on per piece', function () {
    $item = OrderItem::factory()->create([
        'order_id' => $this->order->id,
        'product_id' => mappedProduct('COLOMBIAN', $this->branch)->id,
        'variant_name' => null,
        'variant_name_ar' => null,
        'product_name' => 'Colombian Coffee Pack',
        'product_name_ar' => null,
        'quantity' => 3,
        'notes' => null,
    ]);
    addGinseng($item, $this->ginseng);

    $items = app(OrderPOSPayloadBuilder::class)->build($this->order->fresh())['order']['items'];

    expect($items[0]['notes'])->toBe('')
        ->and($items[1])->toBe([
            'quantity' => 3,
            'notes' => 'إضافة على: Colombian Coffee Pack',
            'posRefObj' => [['productRef' => 'GINSENG', 'quantity' => 1]],
        ]);
});

it('writes the address with Arabic labels and Arabic area names', function () {
    $governorate = Governorate::factory()->create(['name' => 'Beheira', 'name_ar' => 'البحيرة']);
    $area = Area::factory()->create(['governorate_id' => $governorate->id, 'name' => 'Beheira', 'name_ar' => 'دمنهور']);
    $order = Order::factory()->make([
        'customer_name' => 'Habib',
        'customer_phone' => '01000000000',
        'delivery_address' => [
            'street' => 'اسيوط',
            'building' => '3',
            'floor' => '2',
            'apartment' => '5',
            'area_id' => $area->id,
            'area_name' => 'Beheira',
            'governorate_name' => 'Beheira',
        ],
    ]);

    $user = (new POSCustomerDataFormatter())->format($order);

    expect($user['area'])->toBe('دمنهور')
        ->and($user['address'])->toBe('اسيوط، عمارة 3، الدور 2، شقة 5، دمنهور، البحيرة');
});
