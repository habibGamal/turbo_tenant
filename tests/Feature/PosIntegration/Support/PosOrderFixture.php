<?php

declare(strict_types=1);

namespace Tests\Feature\PosIntegration\Support;

use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Models\User;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\LocationsDelivery\src\Models\Address;
use App\Plugins\LocationsDelivery\src\Models\Area;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\LocationsDelivery\src\Models\Governorate;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Models\OrderItem;
use App\Plugins\PosIntegration\src\Models\ProductPosMapping;

final class PosOrderFixture
{
    public static function createDocExampleOrder(): Order
    {
        $governorate = Governorate::factory()->create([
            'name' => 'المعادي',
        ]);

        $area = Area::factory()->create([
            'governorate_id' => $governorate->id,
            'name' => 'المعادي',
        ]);

        $user = User::factory()->create([
            'name' => 'أحمد مصطفى',
        ]);

        $address = Address::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
            'phone_number' => '01012345678',
            'full_address' => 'شارع 9، عمارة 15، الدور 3، شقة 7',
        ]);

        $branch = Branch::factory()->create([
            'link' => 'https://pos.example.com',
        ]);

        $productA = Product::factory()->create();
        ProductPosMapping::factory()->create([
            'product_id' => $productA->id,
            'variant_id' => null,
            'extra_option_item_id' => null,
            'branch_id' => $branch->id,
            'pos_item_id' => 'BURGER-DBL-CHEESE',
        ]);

        $productB = Product::factory()->create();
        ProductPosMapping::factory()->create([
            'product_id' => $productB->id,
            'variant_id' => null,
            'extra_option_item_id' => null,
            'branch_id' => $branch->id,
            'pos_item_id' => 'FRIES-LG',
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'address_id' => $address->id,
            'branch_id' => $branch->id,
            'type' => OrderType::WEB_DELIVERY,
            'shift_id' => 42,
            'order_number' => 'WEB-10542',
            'sub_total' => 320.00,
            'tax' => 44.80,
            'service' => 0,
            'delivery_fee' => 25.00,
            'discount' => 20.00,
            'total' => 369.80,
            'note' => 'يرجى الاتصال عند الوصول',
            'payment_method' => PaymentMethod::COD,
            'transaction_id' => null,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $productA->id,
            'quantity' => 2,
            'notes' => 'اللحم مستوي جيداً',
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $productB->id,
            'quantity' => 1,
            'notes' => null,
        ]);

        return $order->fresh([
            'user',
            'guestUser.area.governorate',
            'address.area.governorate',
            'branch',
            'items.product',
            'items.variant',
            'items.weightOptionValue',
            'items.extras.extraOptionItem',
        ]);
    }

    public static function docExamplePayload(): array
    {
        return [
            'user' => [
                'name' => 'أحمد مصطفى',
                'phone' => '01012345678',
                'area' => 'المعادي',
                'address' => 'شارع 9، عمارة 15، الدور 3، شقة 7',
            ],
            'order' => [
                'type' => 'web_delivery',
                'shiftId' => 42,
                'orderNumber' => 'WEB-10542',
                'subTotal' => 320.0,
                'tax' => 44.8,
                'service' => 25.0,
                'discount' => 20.0,
                'total' => 369.8,
                'items' => [
                    [
                        'quantity' => 2.0,
                        'notes' => 'اللحم مستوي جيداً',
                        'posRefObj' => [
                            [
                                'productRef' => 'BURGER-DBL-CHEESE',
                                'quantity' => 1,
                            ],
                        ],
                    ],
                    [
                        'quantity' => 1.0,
                        'notes' => '',
                        'posRefObj' => [
                            [
                                'productRef' => 'FRIES-LG',
                                'quantity' => 1,
                            ],
                        ],
                    ],
                ],
                'note' => 'يرجى الاتصال عند الوصول',
                'webPreferences' => [
                    'payment_method' => 'cash',
                ],
            ],
        ];
    }
}
