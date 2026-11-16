<?php

declare(strict_types=1);

use App\Models\User;
use App\Plugins\LocationsDelivery\src\Models\Address;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\PosIntegration\src\Services\POS\POSCustomerDataFormatter;

uses()->group('unit');

beforeEach(function () {
    $this->formatter = new POSCustomerDataFormatter();
});

describe('POSCustomerDataFormatter', function () {
    it('formats customer data directly from order snapshot fields', function () {
        $order = Order::factory()->make([
            'customer_name' => 'John Doe',
            'customer_phone' => '+201012345678',
            'delivery_address' => [
                'street' => 'Street 10',
                'building' => '5',
                'floor' => '2',
                'apartment' => '4',
                'city' => 'Cairo',
                'area_name' => 'Nasr City',
                'governorate_name' => 'Cairo',
            ],
        ]);

        $result = $this->formatter->format($order);

        expect($result)->toBe([
            'name' => 'John Doe',
            'phone' => '+201012345678',
            'area' => 'Nasr City',
            'address' => 'Street 10، عمارة 5، الدور 2، شقة 4، Cairo، Nasr City، Cairo',
        ]);
    });

    it('formats customer address with full_address from snapshot', function () {
        $order = Order::factory()->make([
            'customer_name' => 'Jane Smith',
            'customer_phone' => '+201098765432',
            'delivery_address' => [
                'full_address' => 'Custom verbatim full address',
                'area_name' => 'Downtown',
            ],
        ]);

        $result = $this->formatter->format($order);

        expect($result)->toBe([
            'name' => 'Jane Smith',
            'phone' => '+201098765432',
            'area' => 'Downtown',
            'address' => 'Custom verbatim full address',
        ]);
    });

    it('does not fall back to live user or address models when snapshot fields are absent', function () {
        $user = User::factory()->make(['name' => 'Live User', 'phone' => '+201111111111']);
        $address = Address::factory()->make(['street' => 'Live Street', 'phone_number' => '+202222222222']);

        $order = Order::factory()->make([
            'user_id' => 1,
            'address_id' => 1,
            'customer_name' => null,
            'customer_phone' => null,
            'delivery_address' => null,
        ]);
        $order->setRelation('user', $user);
        $order->setRelation('address', $address);

        $result = $this->formatter->format($order);

        expect($result)->toBe([
            'name' => 'N/A',
            'phone' => 'N/A',
            'area' => 'N/A',
            'address' => 'N/A',
        ]);
    });

    it('returns N/A defaults for missing snapshot values', function () {
        $order = Order::factory()->make([
            'customer_name' => null,
            'customer_phone' => null,
            'delivery_address' => [],
        ]);

        $result = $this->formatter->format($order);

        expect($result)->toBe([
            'name' => 'N/A',
            'phone' => 'N/A',
            'area' => 'N/A',
            'address' => 'N/A',
        ]);
    });
});
