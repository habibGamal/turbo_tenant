<?php

declare(strict_types=1);

use App\Core\Plugins\DTOs\PluginManifest;
use App\Core\Plugins\Listeners\ActivationGatedListener;
use App\Enums\OrderType;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\OrderManagement\src\Enums\OrderPosStatus;
use App\Plugins\OrderManagement\src\Events\OrderCreated;
use App\Plugins\OrderManagement\src\Events\OrderStatusUpdated;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\PosIntegration\src\Jobs\SendOrderToPosJob;
use App\Plugins\PosIntegration\src\Listeners\SendOrderToPosListener;
use App\Plugins\PosIntegration\src\Services\OrderPOSService;
use App\Plugins\PosIntegration\src\Services\POS\Contracts\OrderPOSPayloadBuilderInterface;
use App\Plugins\PosIntegration\src\Services\POS\Contracts\POSApiClientInterface;
use App\Plugins\PosIntegration\src\Services\POS\Contracts\POSErrorResponseHandlerInterface;
use App\Plugins\PosIntegration\src\Services\ProductPOSImporterService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\PosIntegration\Support\PosOrderFixture;

describe('SendOrderToPosListener', function () {
    it('dispatches SendOrderToPosJob for exact order when OrderCreated has READY pos_status (doc: Post-Creation Processing & Lifecycle)', function () {
        $order = PosOrderFixture::createDocExampleOrder();
        $order->update(['pos_status' => OrderPosStatus::READY]);

        Queue::fake();

        $listener = new SendOrderToPosListener();
        $listener->handleOrderCreated(new OrderCreated($order));

        Queue::assertPushed(SendOrderToPosJob::class, function (SendOrderToPosJob $job) use ($order) {
            return $job->order->id === $order->id;
        });
        Queue::assertPushed(SendOrderToPosJob::class, 1);
    });

    it('does not dispatch SendOrderToPosJob when OrderCreated has non-READY pos_status (doc: Post-Creation Processing & Lifecycle)', function (OrderPosStatus $status) {
        $order = PosOrderFixture::createDocExampleOrder();
        $order->update(['pos_status' => $status]);

        Queue::fake();

        $listener = new SendOrderToPosListener();
        $listener->handleOrderCreated(new OrderCreated($order));

        Queue::assertNothingPushed();
    })->with([
        [OrderPosStatus::NOT_READY],
        [OrderPosStatus::PENDING],
        [OrderPosStatus::SENDING],
        [OrderPosStatus::SENT],
        [OrderPosStatus::FAILED],
    ]);

    it('dispatches SendOrderToPosJob for exact order when OrderStatusUpdated has READY pos_status (doc: Post-Creation Processing & Lifecycle)', function () {
        $order = PosOrderFixture::createDocExampleOrder();
        $order->update(['pos_status' => OrderPosStatus::READY]);

        Queue::fake();

        $listener = new SendOrderToPosListener();
        $listener->handleStatusUpdated(new OrderStatusUpdated($order));

        Queue::assertPushed(SendOrderToPosJob::class, function (SendOrderToPosJob $job) use ($order) {
            return $job->order->id === $order->id;
        });
        Queue::assertPushed(SendOrderToPosJob::class, 1);
    });

    it('does not dispatch SendOrderToPosJob when OrderStatusUpdated has non-READY pos_status (doc: Post-Creation Processing & Lifecycle)', function (OrderPosStatus $status) {
        $order = PosOrderFixture::createDocExampleOrder();
        $order->update(['pos_status' => $status]);

        Queue::fake();

        $listener = new SendOrderToPosListener();
        $listener->handleStatusUpdated(new OrderStatusUpdated($order));

        Queue::assertNothingPushed();
    })->with([
        [OrderPosStatus::NOT_READY],
        [OrderPosStatus::PENDING],
        [OrderPosStatus::SENDING],
        [OrderPosStatus::SENT],
        [OrderPosStatus::FAILED],
    ]);
});

describe('Plugin Wiring', function () {
    it('declares exactly the POS listener methods for OrderCreated and OrderStatusUpdated in its manifest (doc: Post-Creation Processing & Lifecycle)', function () {
        $basePath = base_path('app/Plugins/PosIntegration');
        $manifest = PluginManifest::fromArray(
            json_decode((string) file_get_contents($basePath.'/plugin.json'), true, flags: JSON_THROW_ON_ERROR),
            $basePath,
        );

        expect($manifest->listeners)->toBe([
            OrderCreated::class => [SendOrderToPosListener::class.'@handleOrderCreated'],
            OrderStatusUpdated::class => [SendOrderToPosListener::class.'@handleStatusUpdated'],
        ]);
    });

    it('wires the manifest listeners through the activation gate exactly once', function () {
        $posListeners = fn (string $event): array => array_values(array_filter(
            Event::getRawListeners()[$event] ?? [],
            fn ($listener): bool => $listener instanceof ActivationGatedListener
                && $listener->getListenerClass() === SendOrderToPosListener::class,
        ));

        expect($posListeners(OrderCreated::class))->toHaveCount(1)
            ->and($posListeners(OrderCreated::class)[0]->getMethod())->toBe('handleOrderCreated')
            ->and($posListeners(OrderStatusUpdated::class))->toHaveCount(1)
            ->and($posListeners(OrderStatusUpdated::class)[0]->getMethod())->toBe('handleStatusUpdated');
    });
});

describe('OrderPOSService DI & Container Resolution', function () {
    it('can be resolved from the service container via app()', function () {
        $resolved = app(OrderPOSService::class);

        expect($resolved)->toBeInstanceOf(OrderPOSService::class);
    });

    it('supports pure SOLID dependency injection with mocked interfaces', function () {
        $apiClient = mock(POSApiClientInterface::class);
        $payloadBuilder = mock(OrderPOSPayloadBuilderInterface::class);
        $errorHandler = mock(POSErrorResponseHandlerInterface::class);

        $branch = Branch::factory()->create();
        $order = Order::factory()->create([
            'branch_id' => $branch->id,
            'shift_id' => 99,
            'type' => OrderType::WEB_DELIVERY,
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
});

describe('SendOrderToPosJob Lifecycle', function () {
    beforeEach(function () {
        Http::preventStrayRequests();
        $this->productImporter = mock(ProductPOSImporterService::class);
        $this->app->instance(ProductPOSImporterService::class, $this->productImporter);
    });

    it('successfully sends order to POS with exact sequence, payload, and updates status to SENT (doc: Endpoint 1 & Endpoint 3 Success)', function () {
        $order = PosOrderFixture::createDocExampleOrder();
        $order->update([
            'pos_status' => OrderPosStatus::READY,
            'pos_failure_reason' => 'previous failure',
        ]);

        $statusAtSendTime = null;

        Http::fake([
            'https://pos.example.com/api/can-accept-order' => Http::response([
                'can_accept' => true,
            ], 200),
            'https://pos.example.com/api/web-orders/place-order' => function (Request $request) use ($order, &$statusAtSendTime) {
                $statusAtSendTime = $order->fresh()->pos_status;

                return Http::response([
                    'message' => 'تم إنشاء الطلب بنجاح',
                ], 200);
            },
        ]);

        $job = (new SendOrderToPosJob($order))->withFakeQueueInteractions();
        app()->call([$job, 'handle']);

        expect($statusAtSendTime)->toBe(OrderPosStatus::SENDING);

        $fresh = $order->fresh();
        expect($fresh->pos_status)->toBe(OrderPosStatus::SENT)
            ->and($fresh->pos_failure_reason)->toBeNull();

        $job->assertNotReleased();

        $recorded = Http::recorded();
        expect(count($recorded))->toBe(2);

        [$req1] = $recorded[0];
        expect($req1->method())->toBe('GET')
            ->and($req1->url())->toBe('https://pos.example.com/api/can-accept-order')
            ->and($req1->header('Accept'))->toBe(['application/json']);

        [$req2] = $recorded[1];
        expect($req2->method())->toBe('POST')
            ->and($req2->url())->toBe('https://pos.example.com/api/web-orders/place-order')
            ->and($req2->header('Accept'))->toBe(['application/json'])
            ->and($req2->header('Content-Type'))->toBe(['application/json'])
            ->and($req2->data())->toBe(PosOrderFixture::docExamplePayload());
    });

    it('releases job for 3 seconds when can-accept-order returns false and does not send place-order (doc: Endpoint 1)', function () {
        $order = PosOrderFixture::createDocExampleOrder();

        Http::fake([
            'https://pos.example.com/api/can-accept-order' => Http::response([
                'can_accept' => false,
            ], 200),
        ]);

        $job = (new SendOrderToPosJob($order))->withFakeQueueInteractions();
        app()->call([$job, 'handle']);

        $job->assertReleased(delay: 3);
        Http::assertSentCount(1);
        expect($order->fresh()->pos_status)->toBe(OrderPosStatus::SENDING);
    });

    it('updates status to FAILED with exact error reason and rethrows on Scenario A 400 (doc: Responses section 3 Scenario A)', function () {
        $order = PosOrderFixture::createDocExampleOrder();

        Http::fake([
            'https://pos.example.com/api/can-accept-order' => Http::response([
                'can_accept' => true,
            ], 200),
            'https://pos.example.com/api/web-orders/place-order' => Http::response([
                'message' => 'فشل في إنشاء الطلب',
                'error' => 'الوردية غير صحيحة',
            ], 400),
        ]);

        $job = (new SendOrderToPosJob($order))->withFakeQueueInteractions();

        expect(fn () => app()->call([$job, 'handle']))
            ->toThrow(Exception::class, 'الوردية غير صحيحة');

        $fresh = $order->fresh();
        expect($fresh->pos_status)->toBe(OrderPosStatus::FAILED)
            ->and($fresh->pos_failure_reason)->toBe('الوردية غير صحيحة');
    });

    it('updates status to FAILED with resolved product names and does not rethrow or release on Scenario B 400 (doc: Responses section 3 Scenario B)', function () {
        $order = PosOrderFixture::createDocExampleOrder();

        $this->productImporter->shouldReceive('getProductsByReferences')
            ->once()
            ->with(['INVALID-REF-101'])
            ->andReturn([
                ['name' => 'برجر كومبو', 'posRef' => 'INVALID-REF-101'],
            ]);

        Http::fake([
            'https://pos.example.com/api/can-accept-order' => Http::response([
                'can_accept' => true,
            ], 200),
            'https://pos.example.com/api/web-orders/place-order' => Http::response([
                'message' => 'فشل في إنشاء الطلب',
                'error' => 'منتجات غير موجودة: [\"INVALID-REF-101\"]',
            ], 400),
        ]);

        $job = (new SendOrderToPosJob($order))->withFakeQueueInteractions();
        app()->call([$job, 'handle']);

        $fresh = $order->fresh();
        expect($fresh->pos_status)->toBe(OrderPosStatus::FAILED)
            ->and($fresh->pos_failure_reason)->toBe('المنتجات التالية غير موجودة بهذا الفرع: برجر كومبو');

        $job->assertNotReleased();
    });

    it('updates status to FAILED with exact validation message and rethrows on 422 Unprocessable Content (doc: Responses section 2 Validation Failure)', function () {
        $order = PosOrderFixture::createDocExampleOrder();

        Http::fake([
            'https://pos.example.com/api/can-accept-order' => Http::response([
                'can_accept' => true,
            ], 200),
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

        $job = (new SendOrderToPosJob($order))->withFakeQueueInteractions();

        expect(fn () => app()->call([$job, 'handle']))
            ->toThrow(Exception::class, 'The order.type field must be one of: web_delivery, web_takeaway.');

        $fresh = $order->fresh();
        expect($fresh->pos_status)->toBe(OrderPosStatus::FAILED)
            ->and($fresh->pos_failure_reason)->toBe('The order.type field must be one of: web_delivery, web_takeaway.');
    });

    it('updates status to FAILED with exact connection message and rethrows on place-order connection failure (doc: Endpoint 3 transport)', function () {
        $order = PosOrderFixture::createDocExampleOrder();

        Http::fake([
            'https://pos.example.com/api/can-accept-order' => Http::response([
                'can_accept' => true,
            ], 200),
            'https://pos.example.com/api/web-orders/place-order' => fn () => throw new ConnectionException('Connection timed out'),
        ]);

        $job = (new SendOrderToPosJob($order))->withFakeQueueInteractions();

        expect(fn () => app()->call([$job, 'handle']))
            ->toThrow(Exception::class, 'لا يمكن التواصل مع الفرع في الوقت الحالي');

        $fresh = $order->fresh();
        expect($fresh->pos_status)->toBe(OrderPosStatus::FAILED)
            ->and($fresh->pos_failure_reason)->toBe('لا يمكن التواصل مع الفرع في الوقت الحالي');
    });

    it('updates status to FAILED with exact message, rethrows, and does not post order when can-accept-order returns HTTP 500 (doc: Endpoint 1 error handling)', function () {
        $order = PosOrderFixture::createDocExampleOrder();

        Http::fake([
            'https://pos.example.com/api/can-accept-order' => Http::response([], 500),
        ]);

        $job = (new SendOrderToPosJob($order))->withFakeQueueInteractions();

        expect(fn () => app()->call([$job, 'handle']))
            ->toThrow(Exception::class, 'حدث خطاء اثناء الاستعلام عن قبول الطلبات');

        $fresh = $order->fresh();
        expect($fresh->pos_status)->toBe(OrderPosStatus::FAILED)
            ->and($fresh->pos_failure_reason)->toBe('حدث خطاء اثناء الاستعلام عن قبول الطلبات');

        Http::assertSentCount(1);
    });

    it('updates status to FAILED with exact message, rethrows, and does not post order when shift_id is null and get-shift-id returns null (doc: Endpoint 2 note)', function () {
        $order = PosOrderFixture::createDocExampleOrder();
        $order->update(['shift_id' => null]);

        Http::fake([
            'https://pos.example.com/api/can-accept-order' => Http::response([
                'can_accept' => true,
            ], 200),
            'https://pos.example.com/api/get-shift-id' => Http::response([
                'shift_id' => null,
            ], 200),
        ]);

        $job = (new SendOrderToPosJob($order))->withFakeQueueInteractions();

        expect(fn () => app()->call([$job, 'handle']))
            ->toThrow(Exception::class, 'حدث خطاء اثناء الاستعلام عن رقم الوردية');

        $fresh = $order->fresh();
        expect($fresh->pos_status)->toBe(OrderPosStatus::FAILED)
            ->and($fresh->pos_failure_reason)->toBe('حدث خطاء اثناء الاستعلام عن رقم الوردية');

        Http::assertSentCount(2);
        $recorded = Http::recorded();
        expect($recorded[0][0]->url())->toBe('https://pos.example.com/api/can-accept-order')
            ->and($recorded[1][0]->url())->toBe('https://pos.example.com/api/get-shift-id');
    });

    it('updates status to FAILED with exact message, rethrows, and does not post order when order type is pos (doc: Schema Definition order.type)', function () {
        $order = PosOrderFixture::createDocExampleOrder();
        $order->update(['type' => OrderType::POS]);

        Http::fake([
            'https://pos.example.com/api/can-accept-order' => Http::response([
                'can_accept' => true,
            ], 200),
        ]);

        $job = (new SendOrderToPosJob($order))->withFakeQueueInteractions();

        expect(fn () => app()->call([$job, 'handle']))
            ->toThrow(Exception::class, 'نوع الطلب غير مدعوم في نقطة البيع: pos');

        $fresh = $order->fresh();
        expect($fresh->pos_status)->toBe(OrderPosStatus::FAILED)
            ->and($fresh->pos_failure_reason)->toBe('نوع الطلب غير مدعوم في نقطة البيع: pos');

        Http::assertSentCount(1);
    });

    it('configures exactly 5 tries and 3 seconds backoff for queue retries (doc: Post-Creation Processing & Lifecycle)', function () {
        $order = PosOrderFixture::createDocExampleOrder();
        $job = new SendOrderToPosJob($order);

        expect($job->tries)->toBe(5)
            ->and($job->backoff)->toBe(3);
    });
});
