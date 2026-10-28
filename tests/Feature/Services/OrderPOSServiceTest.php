<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Models\User;
use App\Plugins\CatalogProducts\src\Models\ExtraOptionItem;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\ProductVariant;
use App\Plugins\LocationsDelivery\src\Models\Address;
use App\Plugins\LocationsDelivery\src\Models\Area;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\LocationsDelivery\src\Models\Governorate;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Models\OrderItem;
use App\Plugins\OrderManagement\src\Models\OrderItemExtra;
use App\Plugins\PosIntegration\src\Models\ProductPosMapping;
use App\Plugins\PosIntegration\src\Services\OrderPOSService;
use App\Plugins\PosIntegration\src\Services\POS\OrderPOSPayloadBuilder;
use App\Plugins\PosIntegration\src\Services\POS\POSProductResolver;
use App\Plugins\PosIntegration\src\Services\ProductPOSImporterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->productImporter = mock(ProductPOSImporterService::class);
    $this->app->instance(ProductPOSImporterService::class, $this->productImporter);
    $this->service = app(OrderPOSService::class);
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
            return $request->url() === 'https://pos.example.com/api/get-shift-id';
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
        ]);

        $address = Address::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
            'phone_number' => '01234567890',
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
                && (float) $data['order']['items'][0]['quantity'] === 2.0
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
        ]);

        $address = Address::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
            'phone_number' => '01234567890',
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
        ]);

        $address = Address::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
            'phone_number' => '01234567890',
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

    it('handles product not found according to CREATE_ORDER_API_DOC Scenario B', function () {
        $branch = Branch::factory()->create([
            'link' => 'https://pos.example.com',
        ]);

        $user = User::factory()->create(['email' => 'pos_order_user_'.uniqid().'@example.com']);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'shift_id' => 42,
            'type' => 'delivery',
        ]);

        $product = Product::factory()->create();
        ProductPosMapping::factory()->create([
            'product_id' => $product->id,
            'pos_item_id' => 'INVALID-REF-101',
            'branch_id' => $branch->id,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
        ]);

        Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response([
                'message' => 'فشل في إنشاء الطلب',
                'error' => 'منتجات غير موجودة: ["INVALID-REF-101"]',
            ], 400),
        ]);

        $this->productImporter->shouldReceive('getProductsByReferences')
            ->with(['INVALID-REF-101'])
            ->andReturn([
                ['name' => 'Burger Combo', 'posRef' => 'INVALID-REF-101'],
            ]);

        $result = $this->service->placeOrder($order);

        expect($result['success'])->toBeFalse()
            ->and($result['message'])->toContain('Burger Combo')
            ->and($result['notFoundProducts'])->toBe(['INVALID-REF-101']);
    });

    it('places guest user order according to CREATE_ORDER_API_DOC schema', function () {
        $governorate = Governorate::factory()->create(['name' => 'Alexandria']);
        $area = Area::factory()->create([
            'name' => 'Smouha',
            'governorate_id' => $governorate->id,
        ]);

        $guest = App\Plugins\GuestCheckout\src\Models\GuestUser::factory()->create([
            'name' => 'Mona Guest',
            'phone' => '01099887766',
            'phone_country_code' => '+20',
            'area_id' => $area->id,
            'street' => 'Victor Emmanuel',
            'building' => '5',
            'city' => 'Alexandria',
        ]);

        $branch = Branch::factory()->create([
            'link' => 'https://pos.example.com',
        ]);

        $product = Product::factory()->create();
        ProductPosMapping::factory()->create([
            'product_id' => $product->id,
            'pos_item_id' => 'GUEST-PROD-1',
            'branch_id' => $branch->id,
        ]);

        $order = Order::factory()->create([
            'user_id' => null,
            'guest_user_id' => $guest->id,
            'branch_id' => $branch->id,
            'order_number' => 'WEB-GUEST-99',
            'shift_id' => 42,
            'type' => 'delivery',
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

            return $data['user']['name'] === 'Mona Guest'
                && $data['user']['phone'] === '+2001099887766'
                && $data['user']['area'] === 'Smouha'
                && str_contains($data['user']['address'], 'Victor Emmanuel')
                && $data['order']['type'] === 'web_delivery';
        });
    });

    it('converts takeaway order type correctly', function () {
        $governorate = Governorate::factory()->create(['name' => 'Cairo']);
        $area = Area::factory()->create([
            'name' => 'Nasr City',
            'governorate_id' => $governorate->id,
        ]);

        $user = User::factory()->create(['email' => 'pos_order_user_'.uniqid().'@example.com']);
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
        $order = Order::factory()->make([
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

        $user = User::factory()->create(['email' => 'pos_order_user_'.uniqid().'@example.com']);
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

        $extraOptionItem = ExtraOptionItem::factory()->create();

        ProductPosMapping::factory()->create([
            'extra_option_item_id' => $extraOptionItem->id,
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
            'extra_option_item_id' => $extraOptionItem->id,
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
                && (float) $posRefs[1]['quantity'] === 2.0;
        });
    });

    it('can be resolved from the service container via app()', function () {
        $resolved = app(OrderPOSService::class);

        expect($resolved)->toBeInstanceOf(OrderPOSService::class);
    });

    it('supports pure SOLID dependency injection with mocked interfaces', function () {
        $apiClient = mock(App\Plugins\PosIntegration\src\Services\POS\Contracts\POSApiClientInterface::class);
        $payloadBuilder = mock(App\Plugins\PosIntegration\src\Services\POS\Contracts\OrderPOSPayloadBuilderInterface::class);
        $errorHandler = mock(App\Plugins\PosIntegration\src\Services\POS\Contracts\POSErrorResponseHandlerInterface::class);

        $branch = Branch::factory()->create();
        $order = Order::factory()->create([
            'branch_id' => $branch->id,
            'shift_id' => 99,
        ]);

        $customService = new OrderPOSService($apiClient, $payloadBuilder, $errorHandler);

        $payloadBuilder->shouldReceive('build')
            ->once()
            ->with(Mockery::on(fn ($arg) => $arg->id === $order->id))
            ->andReturn([
                'user' => ['name' => 'Custom'],
                'order' => ['orderNumber' => 'CUSTOM-1'],
            ]);

        $apiClient->shouldReceive('sendOrder')
            ->once()
            ->andReturn([
                'successful' => true,
                'status' => 200,
                'data' => [],
            ]);

        $result = $customService->placeOrder($order);

        expect($result)->toBe(['success' => true]);
    });

    it('fetches shift ID automatically if order has no shift_id assigned', function () {
        $branch = Branch::factory()->create([
            'link' => 'https://pos.example.com',
        ]);

        $user = User::factory()->create(['email' => 'pos_order_user_'.uniqid().'@example.com']);
        $address = Address::factory()->create([
            'user_id' => $user->id,
            'phone_number' => '01234567890',
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
            'shift_id' => null,
            'type' => 'delivery',
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        Http::fake([
            'https://pos.example.com/api/get-shift-id' => Http::response([
                'shift_id' => 999,
            ], 200),
            'https://pos.example.com/api/web-orders/place-order' => Http::response([], 200),
        ]);

        $result = $this->service->placeOrder($order);

        expect($result['success'])->toBeTrue();

        Http::assertSent(function ($request) {
            if ($request->url() === 'https://pos.example.com/api/web-orders/place-order') {
                return $request->data()['order']['shiftId'] === 999;
            }

            return true;
        });
    });

    it('throws shift error exception for CREATE_ORDER_API_DOC Scenario A', function () {
        $branch = Branch::factory()->create([
            'link' => 'https://pos.example.com',
        ]);

        $user = User::factory()->create(['email' => 'pos_order_user_'.uniqid().'@example.com']);
        $address = Address::factory()->create([
            'user_id' => $user->id,
            'phone_number' => '01234567890',
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
            'shift_id' => 42,
            'type' => 'delivery',
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response([
                'message' => 'فشل في إنشاء الطلب',
                'error' => 'الوردية غير صحيحة',
            ], 400),
        ]);

        expect(fn () => $this->service->placeOrder($order))
            ->toThrow(Exception::class, 'الوردية غير صحيحة');
    });
});

describe('POS Mapping enhancements (Groups 3.a - 3.d)', function () {
    it('prevents duplicate mapping entries even when some columns are NULL', function () {
        $product = Product::factory()->create();

        ProductPosMapping::create([
            'product_id' => $product->id,
            'variant_id' => null,
            'extra_option_item_id' => null,
            'branch_id' => null,
            'pos_item_id' => 'POS-1',
        ]);

        expect(function () use ($product) {
            ProductPosMapping::create([
                'product_id' => $product->id,
                'variant_id' => null,
                'extra_option_item_id' => null,
                'branch_id' => null,
                'pos_item_id' => 'POS-2',
            ]);
        })->toThrow(Illuminate\Database\QueryException::class);
    });

    it('loads all POS mappings for an order in a single database query', function () {
        $branch = Branch::factory()->create(['link' => 'https://pos.example.com']);
        $user = User::factory()->create(['email' => 'pos_order_user_'.uniqid().'@example.com']);
        $address = Address::factory()->create(['user_id' => $user->id]);

        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();
        $extraItem1 = ExtraOptionItem::factory()->create();
        $extraItem2 = ExtraOptionItem::factory()->create();

        ProductPosMapping::create([
            'product_id' => $product1->id,
            'branch_id' => $branch->id,
            'pos_item_id' => 'POS-P1',
        ]);
        ProductPosMapping::create([
            'product_id' => $product2->id,
            'branch_id' => $branch->id,
            'pos_item_id' => 'POS-P2',
        ]);
        ProductPosMapping::create([
            'extra_option_item_id' => $extraItem1->id,
            'branch_id' => $branch->id,
            'pos_item_id' => 'POS-E1',
        ]);
        ProductPosMapping::create([
            'extra_option_item_id' => $extraItem2->id,
            'branch_id' => $branch->id,
            'pos_item_id' => 'POS-E2',
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'address_id' => $address->id,
            'shift_id' => 1,
            'type' => 'delivery',
        ]);

        $item1 = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product1->id,
            'quantity' => 1,
        ]);
        OrderItemExtra::factory()->create([
            'order_item_id' => $item1->id,
            'extra_option_item_id' => $extraItem1->id,
            'quantity' => 1,
        ]);

        $item2 = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product2->id,
            'quantity' => 2,
        ]);
        OrderItemExtra::factory()->create([
            'order_item_id' => $item2->id,
            'extra_option_item_id' => $extraItem2->id,
            'quantity' => 1,
        ]);

        $order->load(['items.extras.extraOptionItem']);

        $builder = app(OrderPOSPayloadBuilder::class);

        // Count queries executed on product_pos_mappings during payload building
        DB::connection('tenant')->enableQueryLog();
        DB::connection('tenant')->flushQueryLog();

        $payload = $builder->build($order);

        $queries = DB::connection('tenant')->getQueryLog();
        $mappingQueries = array_filter($queries, fn ($q) => str_contains($q['query'], 'product_pos_mappings'));

        expect(count($mappingQueries))->toBe(1)
            ->and(count($payload['order']['items']))->toBe(2)
            ->and($payload['order']['items'][0]['posRefObj'][0]['productRef'])->toBe('POS-P1')
            ->and($payload['order']['items'][0]['posRefObj'][1]['productRef'])->toBe('POS-E1')
            ->and($payload['order']['items'][1]['posRefObj'][0]['productRef'])->toBe('POS-P2')
            ->and($payload['order']['items'][1]['posRefObj'][1]['productRef'])->toBe('POS-E2');
    });

    it('uses snapshot extra_name and variant_name in notes, falling back only when empty', function () {
        $branch = Branch::factory()->create();
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Live Variant Name',
        ]);
        $extraItem = ExtraOptionItem::factory()->create([
            'name' => 'Live Extra Name',
        ]);

        // Base product mapping only so variant name appears in notes
        ProductPosMapping::create([
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'pos_item_id' => 'POS-BASE',
        ]);

        $resolver = new POSProductResolver();

        $item = OrderItem::factory()->create([
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'variant_name' => 'Snapshot Variant Name',
            'quantity' => 1,
        ]);

        OrderItemExtra::factory()->create([
            'order_item_id' => $item->id,
            'extra_option_item_id' => $extraItem->id,
            'extra_name' => 'Snapshot Extra Name',
            'quantity' => 1,
        ]);

        $item->load(['extras.extraOptionItem', 'variant']);

        $resolved = $resolver->resolveOrderItem($item, $branch->id);

        expect($resolved['notes'])->toContain('Snapshot Variant Name')
            ->and($resolved['notes'])->not->toContain('Live Variant Name')
            ->and($resolved['notes'])->toContain('Snapshot Extra Name')
            ->and($resolved['notes'])->not->toContain('Live Extra Name');

        // Test fallback when snapshot names are empty
        $itemFallback = OrderItem::factory()->create([
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'variant_name' => null,
            'quantity' => 1,
        ]);

        OrderItemExtra::factory()->create([
            'order_item_id' => $itemFallback->id,
            'extra_option_item_id' => $extraItem->id,
            'extra_name' => '',
            'quantity' => 1,
        ]);

        $itemFallback->load(['extras.extraOptionItem', 'variant']);

        $resolvedFallback = $resolver->resolveOrderItem($itemFallback, $branch->id);

        expect($resolvedFallback['notes'])->toContain('Live Variant Name')
            ->and($resolvedFallback['notes'])->toContain('Live Extra Name');
    });

    it('respects extra_option_items pos_mapping_type for notes vs pos_item', function () {
        $branch = Branch::factory()->create();
        $product = Product::factory()->create();

        $extraPosItem = ExtraOptionItem::factory()->create([
            'name' => 'Extra Onion',
            'pos_mapping_type' => 'pos_item',
        ]);
        $extraNotes = ExtraOptionItem::factory()->create([
            'name' => 'Extra Hot Sauce',
            'pos_mapping_type' => 'notes',
        ]);

        ProductPosMapping::create([
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'pos_item_id' => 'POS-BURGER',
        ]);
        ProductPosMapping::create([
            'extra_option_item_id' => $extraPosItem->id,
            'branch_id' => $branch->id,
            'pos_item_id' => 'POS-ONION',
        ]);
        ProductPosMapping::create([
            'extra_option_item_id' => $extraNotes->id,
            'branch_id' => $branch->id,
            'pos_item_id' => 'POS-SAUCE',
        ]);

        $resolver = new POSProductResolver();

        $item = OrderItem::factory()->create([
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        OrderItemExtra::factory()->create([
            'order_item_id' => $item->id,
            'extra_option_item_id' => $extraPosItem->id,
            'extra_name' => 'Extra Onion',
            'quantity' => 1,
        ]);
        OrderItemExtra::factory()->create([
            'order_item_id' => $item->id,
            'extra_option_item_id' => $extraNotes->id,
            'extra_name' => 'Extra Hot Sauce',
            'quantity' => 1,
        ]);

        $item->load(['extras.extraOptionItem']);

        $resolved = $resolver->resolveOrderItem($item, $branch->id);

        // Both extras must be present in notes
        expect($resolved['notes'])->toContain('Extra Onion')
            ->and($resolved['notes'])->toContain('Extra Hot Sauce');

        // Only pos_item must be present in posRefObj (POS-SAUCE must be absent)
        $posRefs = array_column($resolved['posRefObj'], 'productRef');
        expect($posRefs)->toContain('POS-BURGER')
            ->and($posRefs)->toContain('POS-ONION')
            ->and($posRefs)->not->toContain('POS-SAUCE');
    });
});
