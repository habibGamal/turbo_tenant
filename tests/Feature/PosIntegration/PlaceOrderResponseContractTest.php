<?php

declare(strict_types=1);

use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\OrderManagement\src\Enums\OrderType;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\PosIntegration\src\Services\OrderPOSService;
use App\Plugins\PosIntegration\src\Services\POS\Contracts\OrderPOSPayloadBuilderInterface;
use App\Plugins\PosIntegration\src\Services\POS\Contracts\PosMasterClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();

    $this->branch = Branch::factory()->create([
        'link' => 'https://pos.example.com',
    ]);

    $this->order = Order::factory()->for($this->branch)->create([
        'shift_id' => 42,
        'type' => OrderType::WEB_DELIVERY,
    ]);

    $this->minimalPayload = [
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
                    'quantity' => 2,
                    'notes' => 'اللحم مستوي جيداً',
                    'posRefObj' => [
                        [
                            'productRef' => 'BURGER-DBL-CHEESE',
                            'quantity' => 1,
                        ],
                    ],
                ],
            ],
        ],
    ];

    $this->mockPayloadBuilder = mock(OrderPOSPayloadBuilderInterface::class);
    $this->mockPayloadBuilder->shouldReceive('build')
        ->byDefault()
        ->andReturn($this->minimalPayload);
    $this->app->instance(OrderPOSPayloadBuilderInterface::class, $this->mockPayloadBuilder);

    $this->posMasterClient = mock(PosMasterClient::class);
    $this->app->instance(PosMasterClient::class, $this->posMasterClient);

    $this->service = app(OrderPOSService::class);
});

describe('PlaceOrderResponseContract', function () {
    it('handles success response with 200 OK (doc: Responses section 1 Success Response)', function () {
        $this->posMasterClient->shouldNotReceive('getProductsByReferences');

        Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response([
                'message' => 'تم إنشاء الطلب بنجاح',
            ], 200),
        ]);

        $result = $this->service->placeOrder($this->order);

        expect($result)->toBe(['success' => true]);

        Http::assertSentCount(1);
        $recorded = Http::recorded()[0][0];
        expect($recorded->method())->toBe('POST')
            ->and($recorded->url())->toBe('https://pos.example.com/api/web-orders/place-order')
            ->and($recorded->header('Accept'))->toBe(['application/json'])
            ->and($recorded->header('Content-Type'))->toBe(['application/json'])
            ->and($recorded->data())->toBe($this->minimalPayload);
    });

    it('throws shift error exception for Scenario A (doc: Responses section 3 Scenario A)', function () {
        $this->posMasterClient->shouldNotReceive('getProductsByReferences');

        Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response([
                'message' => 'فشل في إنشاء الطلب',
                'error' => 'الوردية غير صحيحة',
            ], 400),
        ]);

        expect(fn () => $this->service->placeOrder($this->order))
            ->toThrow(Exception::class, 'الوردية غير صحيحة');

        Http::assertSentCount(1);
        $recorded = Http::recorded()[0][0];
        expect($recorded->method())->toBe('POST')
            ->and($recorded->url())->toBe('https://pos.example.com/api/web-orders/place-order')
            ->and($recorded->header('Accept'))->toBe(['application/json'])
            ->and($recorded->header('Content-Type'))->toBe(['application/json'])
            ->and($recorded->data())->toBe($this->minimalPayload);
    });

    it('handles Scenario B with one escaped ref returning mapped product name (doc: Responses section 3 Scenario B)', function () {
        $this->posMasterClient->shouldReceive('getProductsByReferences')
            ->once()
            ->with(['INVALID-REF-101'])
            ->andReturn([
                ['name' => 'برجر كومبو', 'posRef' => 'INVALID-REF-101'],
            ]);

        Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response([
                'message' => 'فشل في إنشاء الطلب',
                'error' => 'منتجات غير موجودة: [\"INVALID-REF-101\"]',
            ], 400),
        ]);

        $result = $this->service->placeOrder($this->order);

        expect($result)->toBe([
            'success' => false,
            'message' => 'المنتجات التالية غير موجودة بهذا الفرع: برجر كومبو',
            'notFoundProducts' => ['INVALID-REF-101'],
        ]);

        Http::assertSentCount(1);
        $recorded = Http::recorded()[0][0];
        expect($recorded->method())->toBe('POST')
            ->and($recorded->url())->toBe('https://pos.example.com/api/web-orders/place-order')
            ->and($recorded->header('Accept'))->toBe(['application/json'])
            ->and($recorded->header('Content-Type'))->toBe(['application/json'])
            ->and($recorded->data())->toBe($this->minimalPayload);
    });

    it('handles Scenario B with two unescaped refs joining names with comma and space (doc: Responses section 3 Scenario B)', function () {
        $this->posMasterClient->shouldReceive('getProductsByReferences')
            ->once()
            ->with(['A-1', 'B-2'])
            ->andReturn([
                ['name' => 'برجر دجاج', 'posRef' => 'A-1'],
                ['name' => 'بطاطس مقلية', 'posRef' => 'B-2'],
            ]);

        Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response([
                'message' => 'فشل في إنشاء الطلب',
                'error' => 'منتجات غير موجودة: ["A-1", "B-2"]',
            ], 400),
        ]);

        $result = $this->service->placeOrder($this->order);

        expect($result)->toBe([
            'success' => false,
            'message' => 'المنتجات التالية غير موجودة بهذا الفرع: برجر دجاج, بطاطس مقلية',
            'notFoundProducts' => ['A-1', 'B-2'],
        ]);

        Http::assertSentCount(1);
        $recorded = Http::recorded()[0][0];
        expect($recorded->method())->toBe('POST')
            ->and($recorded->url())->toBe('https://pos.example.com/api/web-orders/place-order')
            ->and($recorded->header('Accept'))->toBe(['application/json'])
            ->and($recorded->header('Content-Type'))->toBe(['application/json'])
            ->and($recorded->data())->toBe($this->minimalPayload);
    });

    it('falls back to raw refs in Scenario B message when product importer throws exception (doc: Responses section 3 Scenario B)', function () {
        $this->posMasterClient->shouldReceive('getProductsByReferences')
            ->once()
            ->with(['A-1', 'B-2'])
            ->andThrow(new Exception('Network error'));

        Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response([
                'message' => 'فشل في إنشاء الطلب',
                'error' => 'منتجات غير موجودة: ["A-1", "B-2"]',
            ], 400),
        ]);

        $result = $this->service->placeOrder($this->order);

        expect($result)->toBe([
            'success' => false,
            'message' => 'المنتجات التالية غير موجودة بهذا الفرع: A-1, B-2',
            'notFoundProducts' => ['A-1', 'B-2'],
        ]);

        Http::assertSentCount(1);
        $recorded = Http::recorded()[0][0];
        expect($recorded->method())->toBe('POST')
            ->and($recorded->url())->toBe('https://pos.example.com/api/web-orders/place-order')
            ->and($recorded->header('Accept'))->toBe(['application/json'])
            ->and($recorded->header('Content-Type'))->toBe(['application/json'])
            ->and($recorded->data())->toBe($this->minimalPayload);
    });

    it('falls back to raw refs in Scenario B message when product importer returns empty array (doc: Responses section 3 Scenario B)', function () {
        $this->posMasterClient->shouldReceive('getProductsByReferences')
            ->once()
            ->with(['A-1', 'B-2'])
            ->andReturn([]);

        Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response([
                'message' => 'فشل في إنشاء الطلب',
                'error' => 'منتجات غير موجودة: ["A-1", "B-2"]',
            ], 400),
        ]);

        $result = $this->service->placeOrder($this->order);

        expect($result)->toBe([
            'success' => false,
            'message' => 'المنتجات التالية غير موجودة بهذا الفرع: A-1, B-2',
            'notFoundProducts' => ['A-1', 'B-2'],
        ]);

        Http::assertSentCount(1);
        $recorded = Http::recorded()[0][0];
        expect($recorded->method())->toBe('POST')
            ->and($recorded->url())->toBe('https://pos.example.com/api/web-orders/place-order')
            ->and($recorded->header('Accept'))->toBe(['application/json'])
            ->and($recorded->header('Content-Type'))->toBe(['application/json'])
            ->and($recorded->data())->toBe($this->minimalPayload);
    });

    it('throws validation message exception on 422 Unprocessable Content (doc: Responses section 2 Validation Failure)', function () {
        $this->posMasterClient->shouldNotReceive('getProductsByReferences');

        Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response([
                'message' => 'The order.type field must be one of: web_delivery, web_takeaway.',
                'errors' => [
                    'order.type' => [
                        'The order.type field must be one of: web_delivery, web_takeaway.',
                    ],
                    'order.shiftId' => [
                        'The order.shiftId field is required.',
                    ],
                    'order.items.0.posRefObj.0.productRef' => [
                        'The order.items.0.posRefObj.0.productRef field is required.',
                    ],
                ],
            ], 422),
        ]);

        expect(fn () => $this->service->placeOrder($this->order))
            ->toThrow(Exception::class, 'The order.type field must be one of: web_delivery, web_takeaway.');

        Http::assertSentCount(1);
        $recorded = Http::recorded()[0][0];
        expect($recorded->method())->toBe('POST')
            ->and($recorded->url())->toBe('https://pos.example.com/api/web-orders/place-order')
            ->and($recorded->header('Accept'))->toBe(['application/json'])
            ->and($recorded->header('Content-Type'))->toBe(['application/json'])
            ->and($recorded->data())->toBe($this->minimalPayload);
    });

    it('throws generic branch communication exception for unrecognized or unexpected responses (doc: Responses generic fallback)', function (callable $setupFake) {
        $this->posMasterClient->shouldNotReceive('getProductsByReferences');

        $setupFake();

        expect(fn () => $this->service->placeOrder($this->order))
            ->toThrow(Exception::class, 'لا يمكن التواصل مع الفرع في الوقت الحالي');

        if (Http::recorded()->isNotEmpty()) {
            Http::assertSentCount(1);
            $recorded = Http::recorded()[0][0];
            expect($recorded->method())->toBe('POST')
                ->and($recorded->url())->toBe('https://pos.example.com/api/web-orders/place-order')
                ->and($recorded->header('Accept'))->toBe(['application/json'])
                ->and($recorded->header('Content-Type'))->toBe(['application/json'])
                ->and($recorded->data())->toBe($this->minimalPayload);
        } else {
            Http::assertNothingSent();
        }
    })->with('placeOrderGenericErrorResponses');
});

dataset('placeOrderGenericErrorResponses', [
    '400 without error' => [
        fn () => Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response(['message' => 'فشل في إنشاء الطلب'], 400),
        ]),
    ],
    '500 with error on non-400 status' => [
        fn () => Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response(['error' => 'الوردية غير صحيحة'], 500),
        ]),
    ],
    '500 non-JSON body' => [
        fn () => Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response('Internal Server Error', 500, ['Content-Type' => 'text/plain']),
        ]),
    ],
    '404 empty body' => [
        fn () => Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response([], 404),
        ]),
    ],
    '422 without message' => [
        fn () => Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response(['errors' => ['order.type' => ['Required']]], 422),
        ]),
    ],
    'legacy 400 notFoundProducts array' => [
        fn () => Http::fake([
            'https://pos.example.com/api/web-orders/place-order' => Http::response(['notFoundProducts' => ['X']], 400),
        ]),
    ],
    'connection failure' => [
        fn () => Http::fake(fn () => throw new ConnectionException('down')),
    ],
]);
