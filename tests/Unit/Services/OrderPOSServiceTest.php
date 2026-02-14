<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Models\Address;
use App\Models\Area;
use App\Models\Branch;
use App\Models\Governorate;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemExtra;
use App\Models\Product;
use App\Models\ProductPosMapping;
use App\Models\User;
use App\Services\OrderPOSService;
use App\Services\ProductPOSImporterService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->productImporter = mock(ProductPOSImporterService::class);
    $this->service = new OrderPOSService($this->productImporter);
});

describe('getShiftId', function () {
    it('retrieves shift ID from POS system', function () {
        $branch = Branch::factory()->create([
            'link' => 'https://pos.example.com',
        ]);

        Http::fake([
            'https://pos.example.com/api/get-shift-id' => Http::response([
                'shift_id' => 123,
            ], 200),
        ]);

        $shiftId = $this->service->getShiftId($branch);

        expect($shiftId)->toBe(123);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://pos.example.com/api/get-shift-id'
                && $request->hasOption('verify', false);
        });
    });

    it('throws exception when API request fails', function () {
        $branch = Branch::factory()->create([
            'link' => 'https://pos.example.com',
        ]);

        Http::fake([
            'https://pos.example.com/api/get-shift-id' => Http::response([], 500),
        ]);

        expect(fn () => $this->service->getShiftId($branch))
            ->toThrow(Exception::class, 'حدث خطاء اثناء الاستعلام عن رقم الوردية');
    });

    it('throws exception when shift_id is missing from response', function () {
        $branch = Branch::factory()->create([
            'link' => 'https://pos.example.com',
        ]);

        Http::fake([
            'https://pos.example.com/api/get-shift-id' => Http::response([], 200),
        ]);

        expect(fn () => $this->service->getShiftId($branch))
            ->toThrow(Exception::class, 'حدث خطاء اثناء الاستعلام عن رقم الوردية');
    });
});

describe('canAcceptOrder', function () {
    it('checks if branch can accept orders', function () {
        $branch = Branch::factory()->create([
            'link' => 'https://pos.example.com',
        ]);

        Http::fake([
            'https://pos.example.com/api/can-accept-order' => Http::response([
                'can_accept' => true,
            ], 200),
        ]);

        $result = $this->service->canAcceptOrder($branch);

        expect($result)->toBeTrue();
    });

    it('returns false when branch cannot accept orders', function () {
        $branch = Branch::factory()->create([
            'link' => 'https://pos.example.com',
        ]);

        Http::fake([
            'https://pos.example.com/api/can-accept-order' => Http::response([
                'can_accept' => false,
            ], 200),
        ]);

        $result = $this->service->canAcceptOrder($branch);

        expect($result)->toBeFalse();
    });

    it('throws exception when API request fails', function () {
        $branch = Branch::factory()->create([
            'link' => 'https://pos.example.com',
        ]);

        Http::fake([
            'https://pos.example.com/api/can-accept-order' => Http::response([], 500),
        ]);

        expect(fn () => $this->service->canAcceptOrder($branch))
            ->toThrow(Exception::class, 'حدث خطاء اثناء الاستعلام عن قبول الطلبات');
    });
});

describe('placeOrder', function () {
    it('places order successfully', function () {
        $governorate = Governorate::factory()->create(['name' => 'Cairo']);
        $area = Area::factory()->create([
            'name' => 'Nasr City',
            'governorate_id' => $governorate->id,
        ]);

        $user = User::factory()->create([
            'name' => 'Ahmed Ali',
            'phone' => '01234567890',
        ]);

        $address = Address::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
            'street' => '15 Street Name',
            'building' => '10',
            'apartment' => '5',
        ]);

        $branch = Branch::factory()->create([
            'link' => 'https://pos.example.com',
        ]);

        $product = Product::factory()->create([
            'name' => 'Pizza Margherita',
        ]);

        ProductPosMapping::factory()->create([
            'product_id' => $product->id,
            'pos_item_id' => 'PROD-001',
            'branch_id' => $branch->id,
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'address_id' => $address->id,
            'order_number' => 'WEB-123-001',
            'shift_id' => 123,
            'type' => 'delivery',
            'sub_total' => 100.0,
            'tax' => 14.0,
            'service' => 5.0,
            'discount' => 10.0,
            'total' => 109.0,
            'payment_method' => PaymentMethod::COD,
            'note' => 'Please knock twice',
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 50.0,
            'total' => 100.0,
            'notes' => 'Extra cheese',
        ]);

        Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response([], 200),
        ]);

        $result = $this->service->placeOrder($order);

        expect($result)->toHaveKey('success')
            ->and($result['success'])->toBeTrue();

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'https://pos.example.com/api/web-orders/place-order'
                && $data['user']['name'] === 'Ahmed Ali'
                && $data['user']['phone'] === '01234567890'
                && $data['order']['type'] === 'web_delivery'
                && $data['order']['shiftId'] === 123
                && $data['order']['orderNumber'] === 'WEB-123-001'
                && $data['order']['total'] === 109.0
                && $data['order']['note'] === 'Please knock twice'
                && count($data['order']['items']) === 1
                && $data['order']['items'][0]['quantity'] === 2
                && $data['order']['items'][0]['notes'] === 'Extra cheese'
                && count($data['order']['items'][0]['posRefObj']) === 1
                && $data['order']['items'][0]['posRefObj'][0]['productRef'] === 'PROD-001';
        });
    });

    it('includes payment information for online payments', function () {
        $governorate = Governorate::factory()->create(['name' => 'Cairo']);
        $area = Area::factory()->create([
            'name' => 'Nasr City',
            'governorate_id' => $governorate->id,
        ]);

        $user = User::factory()->create([
            'name' => 'Ahmed Ali',
            'phone' => '01234567890',
        ]);

        $address = Address::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
        ]);

        $branch = Branch::factory()->create([
            'link' => 'https://pos.example.com',
        ]);

        $product = Product::factory()->create();

        ProductPosMapping::factory()->create([
            'product_id' => $product->id,
            'pos_item_id' => 'PROD-001',
            'branch_id' => $branch->id,
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'address_id' => $address->id,
            'order_number' => 'WEB-123-001',
            'shift_id' => 123,
            'type' => 'delivery',
            'sub_total' => 100.0,
            'tax' => 14.0,
            'service' => 5.0,
            'discount' => 10.0,
            'total' => 109.0,
            'payment_method' => PaymentMethod::CARD,
            'transaction_id' => 'TXN-12345',
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response([], 200),
        ]);

        $result = $this->service->placeOrder($order);

        expect($result['success'])->toBeTrue();

        Http::assertSent(function ($request) {
            $data = $request->data();

            return isset($data['order']['webPreferences'])
                && $data['order']['webPreferences']['payment_method'] === 'card'
                && $data['order']['webPreferences']['transaction_id'] === 'TXN-12345';
        });
    });

    it('handles product not found error', function () {
        $governorate = Governorate::factory()->create(['name' => 'Cairo']);
        $area = Area::factory()->create([
            'name' => 'Nasr City',
            'governorate_id' => $governorate->id,
        ]);

        $user = User::factory()->create([
            'name' => 'Ahmed Ali',
            'phone' => '01234567890',
        ]);

        $address = Address::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
        ]);

        $branch = Branch::factory()->create([
            'link' => 'https://pos.example.com',
        ]);

        $product = Product::factory()->create();

        ProductPosMapping::factory()->create([
            'product_id' => $product->id,
            'pos_item_id' => 'PROD-001',
            'branch_id' => $branch->id,
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'address_id' => $address->id,
            'order_number' => 'WEB-123-001',
            'shift_id' => 123,
            'type' => 'delivery',
            'sub_total' => 100.0,
            'tax' => 14.0,
            'service' => 5.0,
            'discount' => 10.0,
            'total' => 109.0,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response([
                'message' => 'Product not found',
                'notFoundProducts' => ['PROD-001'],
            ], 400),
        ]);

        $this->productImporter->shouldReceive('getProductsByReferences')
            ->with(['PROD-001'])
            ->andReturn([
                ['name' => 'Pizza Margherita', 'posRef' => 'PROD-001'],
            ]);

        $result = $this->service->placeOrder($order);

        expect($result['success'])->toBeFalse()
            ->and($result)->toHaveKey('message')
            ->and($result['message'])->toContain('المنتجات التالية غير موجودة بهذا الفرع')
            ->and($result['message'])->toContain('Pizza Margherita');
    });

    it('converts takeaway order type correctly', function () {
        $governorate = Governorate::factory()->create(['name' => 'Cairo']);
        $area = Area::factory()->create([
            'name' => 'Nasr City',
            'governorate_id' => $governorate->id,
        ]);

        $user = User::factory()->create();
        $address = Address::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
        ]);

        $branch = Branch::factory()->create([
            'link' => 'https://pos.example.com',
        ]);

        $product = Product::factory()->create();

        ProductPosMapping::factory()->create([
            'product_id' => $product->id,
            'pos_item_id' => 'PROD-001',
            'branch_id' => $branch->id,
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'address_id' => $address->id,
            'type' => 'takeaway',
            'shift_id' => 123,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
        ]);

        Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response([], 200),
        ]);

        $this->service->placeOrder($order);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $data['order']['type'] === 'web_takeaway';
        });
    });

    it('throws exception when order has no branch', function () {
        $order = Order::factory()->create([
            'branch_id' => null,
        ]);

        expect(fn () => $this->service->placeOrder($order))
            ->toThrow(Exception::class, 'Order does not have a branch assigned');
    });

    it('includes order item extras in POS references', function () {
        $governorate = Governorate::factory()->create(['name' => 'Cairo']);
        $area = Area::factory()->create([
            'name' => 'Nasr City',
            'governorate_id' => $governorate->id,
        ]);

        $user = User::factory()->create();
        $address = Address::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
        ]);

        $branch = Branch::factory()->create([
            'link' => 'https://pos.example.com',
        ]);

        $product = Product::factory()->create();

        ProductPosMapping::factory()->create([
            'product_id' => $product->id,
            'pos_item_id' => 'PROD-001',
            'branch_id' => $branch->id,
        ]);

        ProductPosMapping::factory()->create([
            'extra_option_item_id' => 1,
            'pos_item_id' => 'EXTRA-001',
            'branch_id' => $branch->id,
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'address_id' => $address->id,
            'shift_id' => 123,
        ]);

        $orderItem = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        OrderItemExtra::factory()->create([
            'order_item_id' => $orderItem->id,
            'extra_option_item_id' => 1,
            'quantity' => 2,
        ]);

        Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response([], 200),
        ]);

        $this->service->placeOrder($order);

        Http::assertSent(function ($request) {
            $data = $request->data();
            $posRefs = $data['order']['items'][0]['posRefObj'];

            return count($posRefs) === 2
                && $posRefs[0]['productRef'] === 'PROD-001'
                && $posRefs[1]['productRef'] === 'EXTRA-001'
                && $posRefs[1]['quantity'] === 2;
        });
    });
});
