<?php

declare(strict_types=1);

use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\OrderManagement\src\Contracts\PaymentInitiator;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Models\OrderItem;

it('locks JSON shape for authenticated COD order placement', function () {
    $product = Product::factory()->create([
        'base_price' => 60.00,
        'is_active' => true,
    ]);
    $branch = Branch::factory()->create();

    $this->postJson(route('api.cart.store'), [
        'product_id' => $product->id,
        'quantity' => '1',
    ]);

    $response = $this->postJson(route('api.orders.place'), [
        'branch_id' => $branch->id,
        'payment_method' => 'cod',
        'type' => 'web_takeaway',
    ]);

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'status',
        'data' => [
            'order_id',
            'order_number',
            'requires_payment',
            'checkout_url',
        ],
    ]);

    expect($response->json('status'))->toBe('success')
        ->and($response->json('data.order_id'))->toBeInt()
        ->and($response->json('data.order_number'))->toBeString()
        ->and($response->json('data.requires_payment'))->toBeFalse()
        ->and($response->json('data.checkout_url'))->toBeNull();
});

it('locks JSON shape for authenticated online card order placement with mocked payment gateway', function () {
    $product = Product::factory()->create([
        'base_price' => 80.00,
        'is_active' => true,
    ]);
    $branch = Branch::factory()->create();

    $this->postJson(route('api.cart.store'), [
        'product_id' => $product->id,
        'quantity' => '1',
    ]);

    $this->mock(PaymentInitiator::class, function ($mock) {
        $mock->shouldReceive('getGatewayId')->andReturn('paymob');
        $mock->shouldReceive('createPaymentIntention')->andReturn(
            App\Plugins\OrderManagement\src\Contracts\PaymentIntention::created(
                checkoutUrl: 'https://payment.gateway.com/checkout/ord123',
                data: ['transaction_id' => 'TXN123'],
            )
        );
    });

    $response = $this->postJson(route('api.orders.place'), [
        'branch_id' => $branch->id,
        'payment_method' => 'card',
        'type' => 'web_takeaway',
        'billing_data' => [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'phone_number' => '+201111111111',
            'street' => 'Nile Street',
            'building' => '45',
            'city' => 'Giza',
            'country' => 'EG',
        ],
    ]);

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'status',
        'data' => [
            'order_id',
            'order_number',
            'requires_payment',
            'checkout_url',
        ],
    ]);

    expect($response->json('status'))->toBe('success')
        ->and($response->json('data.requires_payment'))->toBeTrue()
        ->and($response->json('data.checkout_url'))->toBeString()
        ->and($response->json('data.checkout_url'))->toContain('https://')
        ->and($response->json('data.order_id'))->toBeInt()
        ->and($response->json('data.order_number'))->toBeString();
});

it('locks JSON shape for guest COD order placement', function () {
    auth()->logout();

    $product = Product::factory()->create([
        'base_price' => 45.00,
        'is_active' => true,
    ]);
    $branch = Branch::factory()->create();

    $this->postJson(route('api.cart.store'), [
        'product_id' => $product->id,
        'quantity' => '1',
    ]);

    $response = $this->postJson(route('api.orders.place'), [
        'branch_id' => $branch->id,
        'payment_method' => 'cod',
        'type' => 'web_takeaway',
        'guest_data' => [
            'name' => 'Guest Customer',
            'phone' => '01012345678',
            'phone_country_code' => '+20',
            'email' => 'guest@example.com',
        ],
    ]);

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'status',
        'data' => [
            'order_id',
            'order_number',
            'requires_payment',
            'checkout_url',
            'tracking_token',
        ],
    ]);

    expect($response->json('status'))->toBe('success')
        ->and($response->json('data.requires_payment'))->toBeFalse()
        ->and($response->json('data.checkout_url'))->toBeNull()
        ->and($response->json('data.order_id'))->toBeInt()
        ->and($response->json('data.order_number'))->toBeString()
        ->and($response->json('data.tracking_token'))->toBeString()
        ->and(str_contains((string) json_encode($response->json()), '01012345678'))->toBeFalse();
});

it('locks JSON shape for authenticated user orders index endpoint', function () {
    $user = auth()->user();
    $branch = Branch::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'branch_id' => $branch->id,
        'sub_total' => 100.00,
        'total' => 120.00,
    ]);
    $product = Product::factory()->create(['base_price' => 50.00]);
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 50.00,
        'total' => 100.00,
    ]);

    $response = $this->getJson(route('api.orders.index'));

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'status',
        'data' => [
            '*' => [
                'id',
                'order_number',
                'status',
                'payment_status',
                'payment_method',
                'type',
                'sub_total',
                'tax',
                'service',
                'delivery_fee',
                'discount',
                'total',
                'created_at',
                'updated_at',
                'branch' => [
                    'id',
                    'name',
                ],
                'items' => [
                    '*' => [
                        'id',
                        'order_id',
                        'product_id',
                        'product_name',
                        'quantity',
                        'unit_price',
                        'total',
                    ],
                ],
            ],
        ],
        'meta' => [
            'current_page',
            'last_page',
            'per_page',
            'total',
        ],
    ]);

    $meta = $response->json('meta');
    expect($response->json('status'))->toBe('success')
        ->and($meta['current_page'])->toBeInt()
        ->and($meta['total'])->toBeInt()
        ->and($meta['per_page'])->toBeInt();

    $firstOrder = $response->json('data.0');
    expect($firstOrder['id'])->toBe($order->id)
        ->and($firstOrder['order_number'])->toBeString()
        ->and($firstOrder['status'])->toBeString()
        ->and($firstOrder['total'])->toBeNumeric()
        ->and($firstOrder['items'])->toBeArray()
        ->and($firstOrder['items'][0]['id'])->toBeInt()
        ->and($firstOrder['items'][0]['product_id'])->toBeInt();
});

it('locks JSON shape for authenticated user order show endpoint', function () {
    $user = auth()->user();
    $branch = Branch::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'branch_id' => $branch->id,
        'sub_total' => 100.00,
        'total' => 120.00,
    ]);
    $product = Product::factory()->create(['base_price' => 50.00]);
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 50.00,
        'total' => 100.00,
    ]);

    $response = $this->getJson(route('api.orders.show', $order->id));

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'status',
        'data' => [
            'id',
            'order_number',
            'status',
            'payment_status',
            'payment_method',
            'type',
            'sub_total',
            'tax',
            'service',
            'delivery_fee',
            'discount',
            'total',
            'created_at',
            'updated_at',
            'branch' => [
                'id',
                'name',
            ],
            'items' => [
                '*' => [
                    'id',
                    'order_id',
                    'product_id',
                    'product_name',
                    'quantity',
                    'unit_price',
                    'total',
                    'extras',
                ],
            ],
        ],
    ]);

    $orderData = $response->json('data');
    expect($response->json('status'))->toBe('success')
        ->and($orderData['id'])->toBe($order->id)
        ->and($orderData['order_number'])->toBeString()
        ->and($orderData['status'])->toBeString()
        ->and($orderData['total'])->toBeNumeric()
        ->and($orderData['items'])->toBeArray()
        ->and($orderData['items'][0]['id'])->toBeInt()
        ->and($orderData['items'][0]['product_id'])->toBeInt()
        ->and($orderData['items'][0]['extras'])->toBeArray();
});
