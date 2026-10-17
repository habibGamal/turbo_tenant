<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Plugins\LocationsDelivery\src\Models\Address;
use App\Plugins\LocationsDelivery\src\Models\Area;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\LocationsDelivery\src\Models\Governorate;
use App\Plugins\GuestCheckout\src\Models\GuestUser;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Models\OrderItem;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Models\User;
use App\Plugins\PosIntegration\src\Services\POS\Contracts\POSProductResolverInterface;
use App\Plugins\PosIntegration\src\Services\POS\OrderPOSPayloadBuilder;
use App\Plugins\PosIntegration\src\Services\POS\POSCustomerDataFormatter;

beforeEach(function () {
    $this->productResolver = mock(POSProductResolverInterface::class);
    $this->customerFormatter = new POSCustomerDataFormatter();
    $this->builder = new OrderPOSPayloadBuilder($this->productResolver, $this->customerFormatter);
});

describe('OrderPOSPayloadBuilder', function () {
    it('builds full order payload for registered user with delivery conforming to API doc', function () {
        $governorate = Governorate::factory()->create(['name' => 'Cairo']);
        $area = Area::factory()->create([
            'name' => 'Maadi',
            'governorate_id' => $governorate->id,
        ]);

        $user = User::factory()->create([
            'name' => 'Ahmed Mostafa',
        ]);

        $address = Address::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
            'phone_number' => '01012345678',
            'street' => 'Street 9',
            'building' => '15',
            'apartment' => '7',
            'full_address' => null,
        ]);

        $branch = Branch::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'address_id' => $address->id,
            'order_number' => 'WEB-10542',
            'shift_id' => 42,
            'type' => 'web_delivery',
            'sub_total' => 320.00,
            'tax' => 44.80,
            'service' => 5.00,
            'delivery_fee' => 20.00,
            'discount' => 20.00,
            'total' => 369.80,
            'note' => 'Call upon arrival',
            'payment_method' => PaymentMethod::COD,
        ]);

        $product = Product::factory()->create();
        $orderItem = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $this->productResolver->shouldReceive('resolveOrderItem')
            ->once()
            ->with(Mockery::on(fn ($arg) => $arg->id === $orderItem->id), $branch->id)
            ->andReturn([
                'quantity' => 2,
                'notes' => 'Well done',
                'posRefObj' => [
                    [
                        'productRef' => 'BURGER-DBL-CHEESE',
                        'quantity' => 1,
                    ],
                ],
            ]);

        $payload = $this->builder->build($order);

        // Verify root structure from CREATE_ORDER_API_DOC.md
        expect($payload)->toHaveKeys(['user', 'order'])
            ->and($payload['user']['name'])->toBe('Ahmed Mostafa')
            ->and($payload['user']['phone'])->toBe('01012345678')
            ->and($payload['user']['area'])->toBe('Maadi')
            ->and($payload['user']['address'])->toContain('Street 9')
            ->and($payload['order']['type'])->toBe('web_delivery')
            ->and($payload['order']['shiftId'])->toBe(42)
            ->and($payload['order']['orderNumber'])->toBe('WEB-10542')
            ->and($payload['order']['subTotal'])->toBe(320.00)
            ->and($payload['order']['tax'])->toBe(44.80)
            ->and($payload['order']['service'])->toBe(25.00) // 5.00 + 20.00 delivery_fee
            ->and($payload['order']['discount'])->toBe(20.00)
            ->and($payload['order']['total'])->toBe(369.80)
            ->and($payload['order']['note'])->toBe('Call upon arrival')
            ->and($payload['order']['webPreferences'])->toBe([
                'payment_method' => 'cash',
            ])
            ->and($payload['order']['items'])->toHaveCount(1)
            ->and($payload['order']['items'][0]['posRefObj'][0]['productRef'])->toBe('BURGER-DBL-CHEESE');
    });

    it('builds payload for guest user and formats guest address correctly', function () {
        $governorate = Governorate::factory()->create(['name' => 'Giza']);
        $area = Area::factory()->create([
            'name' => 'Dokki',
            'governorate_id' => $governorate->id,
        ]);

        $guestUser = GuestUser::factory()->create([
            'name' => 'Sara Guest',
            'phone' => '1122334455',
            'phone_country_code' => '+20',
            'area_id' => $area->id,
            'street' => 'Tahrir St',
            'building' => '12',
            'floor' => '4',
            'apartment' => '402',
            'city' => 'Giza',
        ]);

        $branch = Branch::factory()->create();

        $order = Order::factory()->create([
            'user_id' => null,
            'guest_user_id' => $guestUser->id,
            'branch_id' => $branch->id,
            'order_number' => 'WEB-GUEST-1',
            'shift_id' => 10,
            'type' => 'delivery',
        ]);

        $this->productResolver->shouldReceive('resolveOrderItem')->andReturn(null);

        $payload = $this->builder->build($order);

        expect($payload['user']['name'])->toBe('Sara Guest')
            ->and($payload['user']['phone'])->toBe('+201122334455')
            ->and($payload['user']['area'])->toBe('Dokki')
            ->and($payload['user']['address'])->toContain('Building 12')
            ->and($payload['user']['address'])->toContain('Floor 4')
            ->and($payload['user']['address'])->toContain('Apartment 402')
            ->and($payload['order']['type'])->toBe('web_delivery');
    });

    it('maps takeaway order type to web_takeaway', function () {
        $branch = Branch::factory()->create();
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'type' => 'takeaway',
            'shift_id' => 5,
        ]);

        $this->productResolver->shouldReceive('resolveOrderItem')->andReturn(null);

        $payload = $this->builder->build($order);

        expect($payload['order']['type'])->toBe('web_takeaway');
    });

    it('includes transaction_id in webPreferences for card payment', function () {
        $branch = Branch::factory()->create();
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'shift_id' => 5,
            'payment_method' => PaymentMethod::CARD,
            'transaction_id' => 'TXN-998877',
        ]);

        $this->productResolver->shouldReceive('resolveOrderItem')->andReturn(null);

        $payload = $this->builder->build($order);

        expect($payload['order']['webPreferences'])->toBe([
            'payment_method' => 'card',
            'transaction_id' => 'TXN-998877',
        ]);
    });

    it('handles raw string payment_method gracefully', function () {
        $branch = Branch::factory()->create();
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'shift_id' => 5,
            'transaction_id' => 'TXN-RAW-1',
        ]);

        // Force string payment_method on model attribute
        $order->setAttribute('payment_method', 'card');

        $this->productResolver->shouldReceive('resolveOrderItem')->andReturn(null);

        $payload = $this->builder->build($order);

        expect($payload['order']['webPreferences'])->toBe([
            'payment_method' => 'card',
            'transaction_id' => 'TXN-RAW-1',
        ]);
    });

    it('formats registered user address with full_address without leading dash', function () {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['name' => 'Kareem']);
        $address = Address::factory()->create([
            'user_id' => $user->id,
            'full_address' => 'Villa 12, Compound Beverly Hills, Sheikh Zayed',
            'street' => 'ignored street',
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'address_id' => $address->id,
            'branch_id' => $branch->id,
            'shift_id' => 1,
        ]);

        $this->productResolver->shouldReceive('resolveOrderItem')->andReturn(null);

        $payload = $this->builder->build($order);

        expect($payload['user']['address'])->toBe('Villa 12, Compound Beverly Hills, Sheikh Zayed');
    });
});
