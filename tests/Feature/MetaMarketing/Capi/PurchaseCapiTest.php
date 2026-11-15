<?php

declare(strict_types=1);

use App\Core\Contracts\SettingsRepository;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\ProductVariant;
use App\Plugins\LocationsDelivery\src\Models\Area;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\MetaMarketing\src\DTOs\MetaMarketingConfig;
use App\Plugins\MetaMarketing\src\Models\MetaCapiEvent;
use App\Plugins\MetaMarketing\src\Models\MetaOrderTracking;
use App\Plugins\MetaMarketing\src\Settings\MetaSettingKey;
use App\Plugins\OrderManagement\src\Contracts\PaymentInitiator;
use App\Plugins\OrderManagement\src\Contracts\PaymentIntention;
use App\Plugins\OrderManagement\src\Enums\OrderType;
use App\Plugins\OrderManagement\src\Events\OrderFinalized;
use App\Plugins\OrderManagement\src\Events\OrderPaymentCompleted;
use App\Plugins\OrderManagement\src\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Auth::logout();

    $settings = app(SettingsRepository::class);
    $settings->set(MetaSettingKey::PIXEL_ID, '123456789012345');
    $settings->set(MetaSettingKey::CAPI_ENABLED, true);
    $settings->set(MetaSettingKey::CAPI_ACCESS_TOKEN, Crypt::encryptString('secret-capi-token'));
    app()->forgetInstance(MetaMarketingConfig::class);

    $this->area = Area::factory()->create(['is_active' => true]);
    $this->branch = Branch::factory()->create();
});

test('COD order placement posts exactly one Purchase event to Meta and returns tracking contract in response', function () {
    Http::fake([
        'https://graph.facebook.com/*' => Http::response([
            'events_received' => 1,
            'fbtrace_id' => 'fb_trace_purchase_cod',
        ], 200),
    ]);

    $product = Product::factory()->create(['base_price' => 100.00, 'is_active' => true]);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'price' => 120.00,
        'is_available' => true,
    ]);

    $this->postJson(route('api.cart.store'), [
        'product_id' => $product->id,
        'variant_id' => $variant->id,
        'quantity' => 2,
    ]);

    $guestData = [
        'name' => 'Sara Nabil',
        'phone' => '01098765432',
        'phone_country_code' => '+20',
        'email' => 'sara@example.com',
        'street' => '9th Street',
        'building' => '15',
        'city' => 'Cairo',
        'area_id' => $this->area->id,
    ];

    $response = $this->withCredentials()->withUnencryptedCookies([
        '_fbp' => 'fb.1.1680000000.111111111',
        '_fbc' => 'fb.1.1680000000.222222222',
    ])->postJson(route('api.orders.place'), [
        'branch_id' => $this->branch->id,
        'payment_method' => 'cod',
        'type' => 'web_delivery',
        'guest_data' => $guestData,
    ]);

    $response->assertSuccessful();

    // Verify response tracking contract shape
    $response->assertJsonStructure([
        'status',
        'data' => [
            'order_id',
            'order_number',
            'requires_payment',
            'tracking' => [
                'purchase_event_id',
                'order_id',
                'value',
                'currency',
                'content_type',
                'content_ids',
                'contents' => [
                    ['id', 'quantity', 'item_price'],
                ],
                'num_items',
            ],
        ],
    ]);

    $trackingPayload = $response->json('data.tracking');
    $orderId = $response->json('data.order_id');
    $orderNumber = $response->json('data.order_number');

    expect($trackingPayload['purchase_event_id'])->toBe('purchase.'.$orderId)
        ->and($trackingPayload['order_id'])->toBe($orderNumber)
        ->and($trackingPayload['content_type'])->toBe('product')
        ->and($trackingPayload['content_ids'])->toBe([$product->id.'-'.$variant->id])
        ->and($trackingPayload['num_items'])->toBe(2)
        ->and($trackingPayload['currency'])->toBe('EGP');

    // Exactly one Purchase event sent to Meta
    Http::assertSent(function (Illuminate\Http\Client\Request $request) use ($orderId, $orderNumber, $product, $variant) {
        if (! str_contains($request->url(), '123456789012345/events')) {
            return false;
        }

        $events = $request['data'];
        if (count($events) !== 1) {
            return false;
        }

        $event = $events[0];
        $userData = $event['user_data'];
        $customData = $event['custom_data'];

        return $event['event_name'] === 'Purchase'
            && $event['event_id'] === 'purchase.'.$orderId
            && $event['action_source'] === 'website'
            && $customData['order_id'] === $orderNumber
            && $customData['content_type'] === 'product'
            && $customData['content_ids'] === [$product->id.'-'.$variant->id]
            && $customData['num_items'] === 2
            && $userData['fbp'] === 'fb.1.1680000000.111111111'
            && $userData['fbc'] === 'fb.1.1680000000.222222222'
            && $userData['em'] === [hash('sha256', 'sara@example.com')]
            && $userData['ph'] === [hash('sha256', '201098765432')];
    });

    // Verify delivery log does NOT contain the access token in payload
    $log = MetaCapiEvent::where('event_id', 'purchase.'.$orderId)->first();
    expect($log)->not->toBeNull()
        ->and($log->status)->toBe('sent')
        ->and($log->http_status)->toBe(200)
        ->and(json_encode($log->payload))->not->toContain('secret-capi-token');

    // Verify meta_order_tracking has purchase_sent_at populated
    $orderTracking = MetaOrderTracking::where('order_id', $orderId)->first();
    expect($orderTracking)->not->toBeNull()
        ->and($orderTracking->purchase_sent_at)->not->toBeNull();
});

test('online-payment order does not send Purchase at placement, but sends once on OrderPaymentCompleted', function () {
    Http::fake([
        'https://graph.facebook.com/*' => Http::response([
            'events_received' => 1,
            'fbtrace_id' => 'fb_trace_online',
        ], 200),
    ]);

    $this->mock(PaymentInitiator::class, function ($mock) {
        $mock->shouldReceive('getGatewayId')->andReturn('paymob');
        $mock->shouldReceive('createPaymentIntention')->andReturn(
            PaymentIntention::created(
                checkoutUrl: 'https://paymob.example/checkout/12345',
                data: ['transaction_id' => 'txn_123'],
            )
        );
    });

    $product = Product::factory()->create(['base_price' => 150.00, 'is_active' => true]);

    $this->postJson(route('api.cart.store'), [
        'product_id' => $product->id,
        'quantity' => 1,
    ]);

    $response = $this->postJson(route('api.orders.place'), [
        'branch_id' => $this->branch->id,
        'payment_method' => 'card',
        'type' => 'web_delivery',
        'guest_data' => [
            'name' => 'Online Customer',
            'phone' => '01122334455',
            'phone_country_code' => '+20',
            'email' => 'online@example.com',
            'street' => 'Street 1',
            'building' => '2',
            'city' => 'Giza',
            'area_id' => $this->area->id,
        ],
    ]);

    $response->assertSuccessful();
    expect($response->json('data.requires_payment'))->toBeTrue();

    // No HTTP call sent to Meta at placement time for online payment
    expect(Http::recorded(fn ($req) => str_contains($req->url(), 'graph.facebook.com')))->toBeEmpty();

    $orderId = (int) $response->json('data.order_id');
    $order = Order::find($orderId);

    // Dispatch OrderPaymentCompleted (as gateway webhook does)
    OrderPaymentCompleted::dispatch($order);

    // Now exactly 1 event should be sent to Meta
    $sentToMeta = Http::recorded(fn ($req) => str_contains($req->url(), 'graph.facebook.com'));
    expect(count($sentToMeta))->toBe(1);

    Http::assertSent(function (Illuminate\Http\Client\Request $request) use ($orderId) {
        return str_contains($request->url(), 'graph.facebook.com')
            && $request['data'][0]['event_id'] === 'purchase.'.$orderId;
    });

    // Idempotency check: dispatching OrderPaymentCompleted a second time sends nothing more
    OrderPaymentCompleted::dispatch($order);
    $sentToMetaAfter = Http::recorded(fn ($req) => str_contains($req->url(), 'graph.facebook.com'));
    expect(count($sentToMetaAfter))->toBe(1);
});

test('POS orders are never sent to Meta', function () {
    Http::fake();

    $order = Order::factory()->create([
        'branch_id' => $this->branch->id,
        'type' => OrderType::POS,
        'shift_id' => null,
        'address_id' => null,
        'coupon_id' => null,
        'total' => 200.0,
    ]);

    OrderFinalized::dispatch($order);
    OrderPaymentCompleted::dispatch($order);

    expect(Http::recorded(fn ($req) => str_contains($req->url(), 'graph.facebook.com')))->toBeEmpty();
});

test('when CAPI is disabled or token is missing, no HTTP call is made', function () {
    Http::fake();

    $settings = app(SettingsRepository::class);
    $settings->set(MetaSettingKey::CAPI_ENABLED, false);
    app()->forgetInstance(MetaMarketingConfig::class);

    $order = Order::factory()->create([
        'branch_id' => $this->branch->id,
        'type' => OrderType::WEB_DELIVERY,
        'shift_id' => null,
        'address_id' => null,
        'coupon_id' => null,
        'total' => 200.0,
    ]);

    OrderFinalized::dispatch($order);

    expect(Http::recorded(fn ($req) => str_contains($req->url(), 'graph.facebook.com')))->toBeEmpty();
});

test('Meta 400 error logs failure and does not fail the order flow', function () {
    Http::fake([
        'https://graph.facebook.com/*' => Http::response([
            'error' => [
                'message' => 'Invalid OAuth access token.',
                'type' => 'OAuthException',
                'code' => 190,
                'fbtrace_id' => 'err_fbtrace_400',
            ],
        ], 400),
    ]);

    $product = Product::factory()->create(['base_price' => 50.00, 'is_active' => true]);

    $this->postJson(route('api.cart.store'), [
        'product_id' => $product->id,
        'quantity' => 1,
    ]);

    $response = $this->postJson(route('api.orders.place'), [
        'branch_id' => $this->branch->id,
        'payment_method' => 'cod',
        'type' => 'web_delivery',
        'guest_data' => [
            'name' => 'Failing Meta Test',
            'phone' => '01000000000',
            'phone_country_code' => '+20',
            'email' => 'fail@example.com',
            'street' => 'Street 1',
            'building' => '1',
            'city' => 'Cairo',
            'area_id' => $this->area->id,
        ],
    ]);

    $response->assertSuccessful();

    $orderId = $response->json('data.order_id');
    $log = MetaCapiEvent::where('event_id', 'purchase.'.$orderId)->first();

    expect($log)->not->toBeNull()
        ->and($log->status)->toBe('failed')
        ->and($log->http_status)->toBe(400)
        ->and($log->error)->toContain('Invalid OAuth access token');
});
