<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Models\User;
use App\Plugins\CatalogProducts\src\Models\ExtraOptionItem;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\ProductVariant;
use App\Plugins\CatalogProducts\src\Models\WeightOption;
use App\Plugins\CatalogProducts\src\Models\WeightOptionValue;
use App\Plugins\CouponsPromotions\src\Models\Coupon;
use App\Plugins\LocationsDelivery\src\Models\Address;
use App\Plugins\LocationsDelivery\src\Models\Area;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\LocationsDelivery\src\Models\Governorate;
use App\Plugins\OrderManagement\src\DTOs\OrderTotals;
use App\Plugins\OrderManagement\src\DTOs\PlaceOrderData;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Models\OrderItem;
use App\Plugins\OrderManagement\src\Models\OrderItemExtra;
use App\Plugins\OrderManagement\src\Services\Repositories\OrderRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Money Precision Model Casts', function () {
    it('casts catalog product money columns to float (DB stores decimal 10,2)', function () {
        $product = Product::factory()->create([
            'base_price' => 15.5,
            'price_after_discount' => 12.3,
        ]);
        expect($product->base_price)->toBe(15.5)
            ->and($product->price_after_discount)->toBe(12.3);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 9.9,
        ]);
        expect($variant->price)->toBe(9.9);

        $extra = ExtraOptionItem::factory()->create([
            'price' => 3.1,
        ]);
        expect($extra->price)->toBe(3.1);
    });

    it('casts order management money columns to float (DB stores decimal 10,2)', function () {
        $order = Order::factory()->create([
            'sub_total' => 100.1,
            'tax' => 14.0,
            'service' => 5.25,
            'delivery_fee' => 10.0,
            'discount' => 20.5,
            'total' => 108.85,
        ]);

        expect($order->sub_total)->toBe(100.1)
            ->and($order->tax)->toBe(14.0)
            ->and($order->service)->toBe(5.25)
            ->and($order->delivery_fee)->toBe(10.0)
            ->and($order->discount)->toBe(20.5)
            ->and($order->total)->toBe(108.85);

        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'unit_price' => 50.0,
            'total' => 100.0,
            'extras_unit_total' => 6.5,
        ]);

        expect($item->unit_price)->toBe(50.0)
            ->and($item->total)->toBe(100.0)
            ->and($item->extras_unit_total)->toBe(6.5);

        $extra = OrderItemExtra::factory()->create([
            'order_item_id' => $item->id,
            'extra_price' => 3.25,
        ]);

        expect($extra->extra_price)->toBe(3.25);
    });
});

describe('Order User nullOnDelete', function () {
    it('nullifies user_id on order when user is deleted without deleting the order', function () {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'customer_name' => 'John Doe',
            'customer_phone' => '1234567890',
        ]);

        expect($order->user_id)->toBe($user->id);

        $user->delete();

        $order->refresh();
        expect($order->user_id)->toBeNull()
            ->and(Order::where('id', $order->id)->exists())->toBeTrue()
            ->and($order->getCustomerName())->toBe('John Doe');
    });
});

describe('Order and OrderItem Snapshots', function () {
    it('snapshots customer, address, coupon, weight label, and extras unit total upon order creation', function () {
        $user = User::factory()->create([
            'name' => 'Sara Connor',
        ]);

        $governorate = Governorate::factory()->create(['name' => 'Cairo']);
        $area = Area::factory()->create([
            'name' => 'Nasr City',
            'governorate_id' => $governorate->id,
        ]);
        $address = Address::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
            'phone_number' => '0123456789',
            'street' => 'Abbas El Akkad',
            'building' => '15',
        ]);

        $branch = Branch::factory()->create();
        $coupon = Coupon::factory()->create([
            'code' => 'SAVE20',
        ]);

        $weightOption = WeightOption::factory()->create();
        $weightValue = WeightOptionValue::factory()->create([
            'weight_option_id' => $weightOption->id,
            'value' => '0.500',
            'label' => '500g',
        ]);

        $product = Product::factory()->create([
            'name' => 'Fresh Meat',
            'sell_by_weight' => true,
            'weight_options_id' => $weightOption->id,
            'base_price' => 200.00,
        ]);

        $extraItem = ExtraOptionItem::factory()->create([
            'name' => 'Special Spice',
            'price' => 15.00,
        ]);

        $cartItems = [
            [
                'product' => [
                    'id' => $product->id,
                    'name' => 'Fresh Meat',
                    'name_ar' => null,
                ],
                'variant' => null,
                'weight_option_value' => [
                    'id' => $weightValue->id,
                    'value' => '0.500',
                    'label' => '500g',
                ],
                'weight_multiplier' => 2,
                'price' => 100.00,
                'subtotal' => 230.00,
                'quantity' => '1.000',
                'notes' => 'Please pack well',
                'extras' => [
                    [
                        'id' => $extraItem->id,
                        'name' => 'Special Spice',
                        'name_ar' => null,
                        'price' => 15.00,
                        'quantity' => 2,
                    ],
                ],
            ],
        ];

        $totals = new OrderTotals(
            subTotal: 230.00,
            discount: 20.00,
            tax: 10.00,
            service: 5.00,
            deliveryFee: 15.00,
            total: 240.00
        );

        $placeOrderData = new PlaceOrderData(
            user: $user,
            branchId: $branch->id,
            paymentMethod: PaymentMethod::COD,
            addressId: $address->id,
            couponId: $coupon->id,
            note: 'Deliver before 5 PM'
        );

        $repo = app(OrderRepository::class);
        $order = $repo->createOrderWithItems($placeOrderData, $cartItems, $totals, null);

        expect($order->customer_name)->toBe('Sara Connor')
            ->and($order->customer_phone)->toBe('0123456789')
            ->and($order->coupon_code)->toBe('SAVE20')
            ->and($order->delivery_address)->toBeArray()
            ->and($order->delivery_address['street'])->toBe('Abbas El Akkad')
            ->and($order->delivery_address['area_name'])->toBe('Nasr City')
            ->and($order->delivery_address['governorate_name'])->toBe('Cairo');

        $item = $order->items->first();
        expect($item)->not->toBeNull()
            ->and($item->weight_label)->toBe('500g')
            ->and((float) $item->extras_unit_total)->toBe(30.00); // 15.00 * 2
    });
});

it('keeps variant price nullable and extra price defaulted after the decimal migration', function () {
    $product = Product::factory()->create();

    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'price' => null,
    ]);
    expect($variant->fresh()->price)->toBeNull();

    $extraOption = App\Plugins\CatalogProducts\src\Models\ExtraOption::factory()->create();
    $id = Illuminate\Support\Facades\DB::connection('tenant')->table('extra_option_items')->insertGetId([
        'extra_option_id' => $extraOption->id,
        'name' => 'No price given',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    expect((float) ExtraOptionItem::find($id)->price)->toBe(0.0);
});
