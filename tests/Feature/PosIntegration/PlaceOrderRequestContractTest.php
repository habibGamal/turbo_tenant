<?php

declare(strict_types=1);

use App\Models\User;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\GuestCheckout\src\Models\GuestUser;
use App\Plugins\LocationsDelivery\src\Models\Address;
use App\Plugins\LocationsDelivery\src\Models\Area;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\LocationsDelivery\src\Models\Governorate;
use App\Plugins\OrderManagement\src\Enums\OrderType;
use App\Plugins\OrderManagement\src\Enums\PaymentMethod;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Models\OrderItem;
use App\Plugins\PosIntegration\src\Models\ProductPosMapping;
use App\Plugins\PosIntegration\src\Services\OrderPOSService;
use App\Plugins\PosIntegration\src\Services\POS\Contracts\PosMasterClient;
use Illuminate\Support\Facades\Http;
use Tests\Feature\PosIntegration\Support\PosOrderFixture;

beforeEach(function () {
    $this->posMasterClient = mock(PosMasterClient::class);
    $this->app->instance(PosMasterClient::class, $this->posMasterClient);
    $this->service = app(OrderPOSService::class);
    Http::preventStrayRequests();
});

describe('POST /api/web-orders/place-order request contract', function () {
    it('sends the exact doc example order payload (doc: Request Example)', function () {
        Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response(['message' => 'تم إنشاء الطلب بنجاح'], 200),
        ]);

        $order = PosOrderFixture::createDocExampleOrder();

        $result = $this->service->placeOrder($order);

        expect($result)->toBe(['success' => true]);

        Http::assertSentCount(1);
        $recorded = Http::recorded();
        expect(count($recorded))->toBe(1);

        [$request, $response] = $recorded[0];
        expect($request->method())->toBe('POST')
            ->and($request->url())->toBe('https://pos.example.com/api/web-orders/place-order')
            ->and($request->header('Content-Type'))->toBe(['application/json'])
            ->and($request->header('Accept'))->toBe(['application/json'])
            ->and($request->data())->toBe(PosOrderFixture::docExamplePayload($order));
    });

    it('sends exact key sets for user and order without optional fields (doc: Schema Definition)', function () {
        Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response(['message' => 'تم إنشاء الطلب بنجاح'], 200),
        ]);

        $branch = Branch::factory()->create(['link' => 'https://pos.example.com']);
        $user = User::factory()->create(['name' => 'Kareem']);
        $gov = Governorate::factory()->create(['name' => 'Cairo']);
        $area = Area::factory()->create(['governorate_id' => $gov->id, 'name' => 'Maadi']);
        $address = Address::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
            'phone_number' => '01000000000',
            'full_address' => 'Full address',
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'address_id' => $address->id,
            'branch_id' => $branch->id,
            'type' => OrderType::WEB_DELIVERY,
            'shift_id' => 12,
            'note' => null,
            'payment_method' => null,
            'transaction_id' => null,
        ]);

        $this->service->placeOrder($order);

        $recorded = Http::recorded();
        expect(count($recorded))->toBe(1);

        $body = $recorded[0][0]->data();
        expect(array_keys($body['user']))->toBe(['name', 'phone', 'area', 'address'])
            ->and(array_keys($body['order']))->toBe([
                'type',
                'shiftId',
                'orderNumber',
                'idempotencyKey',
                'subTotal',
                'tax',
                'service',
                'discount',
                'total',
                'items',
            ])
            ->and(array_key_exists('note', $body['order']))->toBeFalse()
            ->and(array_key_exists('webPreferences', $body['order']))->toBeFalse();
    });

    it('omits note when empty string and omits webPreferences when payment_method is null (doc: Schema Definition)', function () {
        Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response(['message' => 'تم إنشاء الطلب بنجاح'], 200),
        ]);

        $branch = Branch::factory()->create(['link' => 'https://pos.example.com']);
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'type' => OrderType::WEB_DELIVERY,
            'shift_id' => 15,
            'note' => '',
            'payment_method' => null,
        ]);

        $this->service->placeOrder($order);

        $recorded = Http::recorded();
        $body = $recorded[0][0]->data();
        expect(array_key_exists('note', $body['order']))->toBeFalse()
            ->and(array_key_exists('webPreferences', $body['order']))->toBeFalse();
    });

    it('maps order type to pos type or throws for unsupported type (doc: Schema Definition)', function (OrderType $type, ?string $expectedPosType, ?string $expectedExceptionMessage) {
        Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response(['message' => 'تم إنشاء الطلب بنجاح'], 200),
        ]);

        $branch = Branch::factory()->create(['link' => 'https://pos.example.com']);
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'type' => $type,
            'shift_id' => 20,
        ]);

        if ($expectedExceptionMessage !== null) {
            expect(fn () => $this->service->placeOrder($order))
                ->toThrow(InvalidArgumentException::class, $expectedExceptionMessage);
            Http::assertNothingSent();
        } else {
            $this->service->placeOrder($order);
            Http::assertSentCount(1);
            $recorded = Http::recorded();
            expect($recorded[0][0]->data()['order']['type'])->toBe($expectedPosType);
        }
    })->with([
        [OrderType::WEB_DELIVERY, 'web_delivery', null],
        [OrderType::WEB_TAKEAWAY, 'web_takeaway', null],
        [OrderType::POS, null, 'نوع الطلب غير مدعوم في نقطة البيع: pos'],
    ]);

    it('sends stored shiftId as int without calling get-shift-id (doc: Service Endpoints & Flow)', function () {
        Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response(['message' => 'تم إنشاء الطلب بنجاح'], 200),
        ]);

        $branch = Branch::factory()->create(['link' => 'https://pos.example.com']);
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'type' => OrderType::WEB_DELIVERY,
            'shift_id' => 42,
        ]);

        $this->service->placeOrder($order);

        Http::assertSentCount(1);
        $recorded = Http::recorded();
        expect($recorded[0][0]->url())->toBe('https://pos.example.com/api/web-orders/place-order')
            ->and($recorded[0][0]->data()['order']['shiftId'])->toBe(42);
    });

    it('calls get-shift-id before place-order when stored shift_id is null [current behavior (not in doc)]', function () {
        Http::fake([
            'https://pos.example.com/api/get-shift-id' => Http::response(['shift_id' => 77], 200),
            'https://pos.example.com/api/web-orders/place-order' => Http::response(['message' => 'تم إنشاء الطلب بنجاح'], 200),
        ]);

        $branch = Branch::factory()->create(['link' => 'https://pos.example.com']);
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'type' => OrderType::WEB_DELIVERY,
            'shift_id' => null,
        ]);

        $this->service->placeOrder($order);

        Http::assertSentCount(2);
        $recorded = Http::recorded();
        expect($recorded[0][0]->method())->toBe('GET')
            ->and($recorded[0][0]->url())->toBe('https://pos.example.com/api/get-shift-id')
            ->and($recorded[1][0]->method())->toBe('POST')
            ->and($recorded[1][0]->url())->toBe('https://pos.example.com/api/web-orders/place-order')
            ->and($recorded[1][0]->data()['order']['shiftId'])->toBe(77);
    });

    it('calculates service as service plus delivery_fee and keeps decimals as floats (doc: Schema Definition)', function () {
        Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response(['message' => 'تم إنشاء الطلب بنجاح'], 200),
        ]);

        $branch = Branch::factory()->create(['link' => 'https://pos.example.com']);
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'type' => OrderType::WEB_DELIVERY,
            'shift_id' => 30,
            'sub_total' => 320.00,
            'tax' => 44.80,
            'service' => 5.50,
            'delivery_fee' => 20.25,
            'discount' => 20.00,
            'total' => 370.55,
        ]);

        $this->service->placeOrder($order);

        $body = Http::recorded()[0][0]->data();
        $orderData = $body['order'];

        expect($orderData['service'])->toBe(25.75)
            ->and($orderData['subTotal'])->toBe(320.0)
            ->and($orderData['tax'])->toBe(44.8)
            ->and($orderData['discount'])->toBe(20.0)
            ->and($orderData['total'])->toBe(370.55)
            ->and(is_float($orderData['subTotal']))->toBeTrue()
            ->and(is_float($orderData['tax']))->toBeTrue()
            ->and(is_float($orderData['service']))->toBeTrue()
            ->and(is_float($orderData['discount']))->toBeTrue()
            ->and(is_float($orderData['total']))->toBeTrue();
    });

    it('maps payment method to cash or card across all enum cases (doc: Schema Definition)', function (PaymentMethod $paymentMethod, string $expectedPosMethod) {
        Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response(['message' => 'تم إنشاء الطلب بنجاح'], 200),
        ]);

        $branch = Branch::factory()->create(['link' => 'https://pos.example.com']);
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'type' => OrderType::WEB_DELIVERY,
            'shift_id' => 10,
            'payment_method' => $paymentMethod,
            'transaction_id' => null,
        ]);

        $this->service->placeOrder($order);

        $body = Http::recorded()[0][0]->data();
        expect($body['order']['webPreferences'])->toBe([
            'payment_method' => $expectedPosMethod,
        ]);
    })->with([
        [PaymentMethod::COD, 'cash'],
        [PaymentMethod::CARD, 'card'],
        [PaymentMethod::WALLET, 'card'],
        [PaymentMethod::KIOSK, 'card'],
        [PaymentMethod::BANK_TRANSFER, 'card'],
    ]);

    it('includes transaction_id when non-empty and omits when null or empty string (doc: Schema Definition)', function (?string $txnId, bool $shouldBePresent) {
        Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response(['message' => 'تم إنشاء الطلب بنجاح'], 200),
        ]);

        $branch = Branch::factory()->create(['link' => 'https://pos.example.com']);
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'type' => OrderType::WEB_DELIVERY,
            'shift_id' => 10,
            'payment_method' => PaymentMethod::CARD,
            'transaction_id' => $txnId,
        ]);

        $this->service->placeOrder($order);

        $body = Http::recorded()[0][0]->data();
        if ($shouldBePresent) {
            expect($body['order']['webPreferences'])->toBe([
                'payment_method' => 'card',
                'transaction_id' => $txnId,
            ]);
        } else {
            expect($body['order']['webPreferences'])->toBe([
                'payment_method' => 'card',
            ]);
        }
    })->with([
        ['TXN-1', true],
        [null, false],
        ['', false],
    ]);

    it('formats registered user customer data with address phone (doc: Schema Definition)', function () {
        Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response(['message' => 'تم إنشاء الطلب بنجاح'], 200),
        ]);

        $branch = Branch::factory()->create(['link' => 'https://pos.example.com']);
        $user = User::factory()->create(['name' => 'Sara']);
        $address = Address::factory()->create([
            'user_id' => $user->id,
            'phone_number' => '01012345678',
            'full_address' => 'Sample Address',
        ]);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'address_id' => $address->id,
            'customer_name' => 'Sara',
            'customer_phone' => '01012345678',
            'delivery_address' => [
                'full_address' => 'Sample Address',
                'phone_number' => '01012345678',
            ],
            'branch_id' => $branch->id,
            'type' => OrderType::WEB_DELIVERY,
            'shift_id' => 1,
        ]);

        $this->service->placeOrder($order);

        $body = Http::recorded()[0][0]->data();
        expect($body['user']['name'])->toBe('Sara')
            ->and($body['user']['phone'])->toBe('01012345678');
    });

    it('formats registered user customer data with full_address sent verbatim (doc: Schema Definition)', function () {
        Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response(['message' => 'تم إنشاء الطلب بنجاح'], 200),
        ]);

        $branch = Branch::factory()->create(['link' => 'https://pos.example.com']);
        $user = User::factory()->create();
        $address = Address::factory()->create([
            'user_id' => $user->id,
            'full_address' => 'شارع 9، عمارة 15، الدور 3، شقة 7',
            'street' => 'ignored',
        ]);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'address_id' => $address->id,
            'delivery_address' => [
                'full_address' => 'شارع 9، عمارة 15، الدور 3، شقة 7',
            ],
            'branch_id' => $branch->id,
            'type' => OrderType::WEB_DELIVERY,
            'shift_id' => 1,
        ]);

        $this->service->placeOrder($order);

        $body = Http::recorded()[0][0]->data();
        expect($body['user']['address'])->toBe('شارع 9، عمارة 15، الدور 3، شقة 7');
    });

    it('formats registered user customer data with composed address (doc: Schema Definition)', function () {
        Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response(['message' => 'تم إنشاء الطلب بنجاح'], 200),
        ]);

        $branch = Branch::factory()->create(['link' => 'https://pos.example.com']);
        $gov = Governorate::factory()->create(['name' => 'Cairo']);
        $area = Area::factory()->create(['governorate_id' => $gov->id, 'name' => 'Maadi']);
        $user = User::factory()->create();
        $address = Address::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
            'street' => 'Street 9',
            'building' => '15',
            'floor' => '3',
            'apartment' => '7',
            'full_address' => null,
        ]);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'address_id' => $address->id,
            'delivery_address' => [
                'street' => 'Street 9',
                'building' => '15',
                'floor' => '3',
                'apartment' => '7',
                'area_name' => 'Maadi',
                'governorate_name' => 'Cairo',
            ],
            'branch_id' => $branch->id,
            'type' => OrderType::WEB_DELIVERY,
            'shift_id' => 1,
        ]);

        $this->service->placeOrder($order);

        $body = Http::recorded()[0][0]->data();
        expect($body['user']['address'])->toBe('Street 9، عمارة 15، الدور 3، شقة 7، Maadi، Cairo');
    });

    it('formats guest user customer data with full_phone and composed address (doc: Schema Definition)', function () {
        Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response(['message' => 'تم إنشاء الطلب بنجاح'], 200),
        ]);

        $branch = Branch::factory()->create(['link' => 'https://pos.example.com']);
        $gov = Governorate::factory()->create(['name' => 'Giza']);
        $area = Area::factory()->create(['governorate_id' => $gov->id, 'name' => 'Dokki']);
        $guestUser = GuestUser::factory()->create([
            'name' => 'Guest Ahmed',
            'phone' => '01099887766',
            'phone_country_code' => '+20',
            'area_id' => $area->id,
            'street' => 'Tahrir',
            'building' => '1',
            'floor' => '2',
            'apartment' => '3',
            'city' => 'Dokki City',
        ]);

        $order = Order::factory()->create([
            'user_id' => null,
            'guest_user_id' => $guestUser->id,
            'customer_name' => 'Guest Ahmed',
            'customer_phone' => '+2001099887766',
            'delivery_address' => [
                'street' => 'Tahrir',
                'building' => '1',
                'floor' => '2',
                'apartment' => '3',
                'city' => 'Dokki City',
                'area_name' => 'Dokki',
                'governorate_name' => 'Giza',
            ],
            'branch_id' => $branch->id,
            'type' => OrderType::WEB_DELIVERY,
            'shift_id' => 1,
        ]);

        $this->service->placeOrder($order);

        $body = Http::recorded()[0][0]->data();
        expect($body['user']['name'])->toBe('Guest Ahmed')
            ->and($body['user']['phone'])->toBe('+2001099887766')
            ->and($body['user']['area'])->toBe('Dokki')
            ->and($body['user']['address'])->toBe('Tahrir، عمارة 1، الدور 2، شقة 3، Dokki City، Dokki، Giza');
    });

    it('formats customer data as N/A when user and guest are absent [current behavior (not in doc)]', function () {
        Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response(['message' => 'تم إنشاء الطلب بنجاح'], 200),
        ]);

        $branch = Branch::factory()->create(['link' => 'https://pos.example.com']);
        $order = Order::factory()->create([
            'user_id' => null,
            'guest_user_id' => null,
            'address_id' => null,
            'branch_id' => $branch->id,
            'type' => OrderType::WEB_DELIVERY,
            'shift_id' => 1,
        ]);

        $this->service->placeOrder($order);

        $body = Http::recorded()[0][0]->data();
        expect($body['user'])->toBe([
            'name' => 'N/A',
            'phone' => 'N/A',
            'area' => 'N/A',
            'address' => 'N/A',
        ]);
    });

    it('drops items without pos mapping from items array [current behavior (not in doc)]', function () {
        Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response(['message' => 'تم إنشاء الطلب بنجاح'], 200),
        ]);

        $branch = Branch::factory()->create(['link' => 'https://pos.example.com']);
        $user = User::factory()->create();
        $productMapped = Product::factory()->create();
        $productUnmapped = Product::factory()->create();

        ProductPosMapping::factory()->create([
            'product_id' => $productMapped->id,
            'pos_item_id' => 'MAPPED-ITEM',
            'branch_id' => $branch->id,
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'type' => OrderType::WEB_DELIVERY,
            'shift_id' => 1,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $productMapped->id,
            'quantity' => 1,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $productUnmapped->id,
            'quantity' => 1,
        ]);

        $this->service->placeOrder($order);

        $body = Http::recorded()[0][0]->data();
        expect(count($body['order']['items']))->toBe(1)
            ->and($body['order']['items'][0]['posRefObj'][0]['productRef'])->toBe('MAPPED-ITEM');
    });

    it('sends empty items array when all items are unmapped [current behavior (not in doc)]', function () {
        Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response(['message' => 'تم إنشاء الطلب بنجاح'], 200),
        ]);

        $branch = Branch::factory()->create(['link' => 'https://pos.example.com']);
        $user = User::factory()->create();
        $productUnmapped = Product::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'type' => OrderType::WEB_DELIVERY,
            'shift_id' => 1,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $productUnmapped->id,
            'quantity' => 1,
        ]);

        $this->service->placeOrder($order);

        Http::assertSentCount(1);
        $body = Http::recorded()[0][0]->data();
        expect($body['order']['items'])->toBe([]);
    });

    it('throws exception when order does not have a branch assigned (doc: Service Endpoints & Flow)', function () {
        $order = Order::factory()->make([
            'branch_id' => null,
            'type' => OrderType::WEB_DELIVERY,
        ]);

        expect(fn () => $this->service->placeOrder($order))
            ->toThrow(Exception::class, 'Order does not have a branch assigned');
    });
});
