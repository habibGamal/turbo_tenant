<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Branch;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\GuestUser;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->cartService = app(CartService::class);
});

it('guest can place COD order successfully', function () {
    $product = Product::factory()->create(['base_price' => 50.00]);
    $branch = Branch::factory()->create();

    // Add item to guest cart
    $this->postJson(route('cart.store'), [
        'product_id' => $product->id,
        'quantity' => '2',
    ]);

    $guestData = [
        'name' => 'Guest User',
        'phone' => '1234567890',
        'phone_country_code' => '+20',
        'email' => 'guest@example.com',
        'street' => 'Main Street',
        'building' => '123',
        'city' => 'Cairo',
    ];

    $response = $this->postJson(route('orders.place'), [
        'branch_id' => $branch->id,
        'payment_method' => 'cod',
        'type' => 'web_delivery',
        'guest_data' => $guestData,
    ]);

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'success',
        'redirect_type',
        'redirect_url',
        'order_id',
    ]);

    expect($response->json('success'))->toBeTrue();
    expect($response->json('redirect_type'))->toBe('internal');
});

it('guest order creates GuestUser record', function () {
    $product = Product::factory()->create(['base_price' => 50.00]);
    $branch = Branch::factory()->create();

    $this->postJson(route('cart.store'), [
        'product_id' => $product->id,
        'quantity' => '1',
    ]);

    $guestData = [
        'name' => 'Test Guest',
        'phone' => '9876543210',
        'phone_country_code' => '+20',
        'email' => 'testguest@example.com',
    ];

    expect(GuestUser::count())->toBe(0);

    $this->postJson(route('orders.place'), [
        'branch_id' => $branch->id,
        'payment_method' => 'cod',
        'type' => 'web_takeaway',
        'guest_data' => $guestData,
    ]);

    expect(GuestUser::count())->toBe(1);

    $this->assertDatabaseHas('guest_users', [
        'name' => 'Test Guest',
        'phone' => '9876543210',
        'phone_country_code' => '+20',
        'email' => 'testguest@example.com',
    ]);
});

it('guest order links to guest_user_id not user_id', function () {
    $product = Product::factory()->create(['base_price' => 50.00]);
    $branch = Branch::factory()->create();

    $this->postJson(route('cart.store'), [
        'product_id' => $product->id,
        'quantity' => '1',
    ]);

    $guestData = [
        'name' => 'Guest User',
        'phone' => '1234567890',
        'phone_country_code' => '+20',
    ];

    $response = $this->postJson(route('orders.place'), [
        'branch_id' => $branch->id,
        'payment_method' => 'cod',
        'type' => 'web_delivery',
        'guest_data' => $guestData,
    ]);

    $orderId = $response->json('order_id');
    $guestUser = GuestUser::where('phone', '1234567890')->first();

    $this->assertDatabaseHas('orders', [
        'id' => $orderId,
        'guest_user_id' => $guestUser->id,
        'user_id' => null,
    ]);
});

it('authenticated user order uses user_id not guest_user_id', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['base_price' => 50.00]);
    $branch = Branch::factory()->create();

    $this->actingAs($user)->postJson(route('cart.store'), [
        'product_id' => $product->id,
        'quantity' => '1',
    ]);

    $response = $this->actingAs($user)->postJson(route('orders.place'), [
        'branch_id' => $branch->id,
        'payment_method' => 'cod',
        'type' => 'web_delivery',
    ]);

    $orderId = $response->json('order_id');

    $this->assertDatabaseHas('orders', [
        'id' => $orderId,
        'user_id' => $user->id,
        'guest_user_id' => null,
    ]);
});

it('guest with same phone finds existing GuestUser', function () {
    $product1 = Product::factory()->create(['base_price' => 30.00]);
    $product2 = Product::factory()->create(['base_price' => 40.00]);
    $branch = Branch::factory()->create();

    // First order
    $this->postJson(route('cart.store'), [
        'product_id' => $product1->id,
        'quantity' => '1',
    ]);

    $guestData = [
        'name' => 'Guest User',
        'phone' => '1111111111',
        'phone_country_code' => '+20',
        'email' => 'guest@test.com',
    ];

    $this->postJson(route('orders.place'), [
        'branch_id' => $branch->id,
        'payment_method' => 'cod',
        'type' => 'web_delivery',
        'guest_data' => $guestData,
    ]);

    expect(GuestUser::count())->toBe(1);

    // Second order with same phone
    $this->withSession([]); // Clear session to simulate new session
    $this->postJson(route('cart.store'), [
        'product_id' => $product2->id,
        'quantity' => '1',
    ]);

    $this->postJson(route('orders.place'), [
        'branch_id' => $branch->id,
        'payment_method' => 'cod',
        'type' => 'web_delivery',
        'guest_data' => array_merge($guestData, ['name' => 'Updated Name']),
    ]);

    // Should still have only one guest user
    expect(GuestUser::count())->toBe(1);

    $guestUser = GuestUser::first();
    expect($guestUser->name)->toBe('Updated Name'); // Name updated
    expect($guestUser->orders()->count())->toBe(2); // Two orders
});

it('guest checkout requires guest_data when not authenticated', function () {
    $product = Product::factory()->create(['base_price' => 50.00]);
    $branch = Branch::factory()->create();

    $this->postJson(route('cart.store'), [
        'product_id' => $product->id,
        'quantity' => '1',
    ]);

    $response = $this->postJson(route('orders.place'), [
        'branch_id' => $branch->id,
        'payment_method' => 'cod',
        'type' => 'web_delivery',
        // Missing guest_data
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['guest_data']);
});

it('guest_data.name is required when providing guest_data', function () {
    $product = Product::factory()->create(['base_price' => 50.00]);
    $branch = Branch::factory()->create();

    $this->postJson(route('cart.store'), [
        'product_id' => $product->id,
        'quantity' => '1',
    ]);

    $response = $this->postJson(route('orders.place'), [
        'branch_id' => $branch->id,
        'payment_method' => 'cod',
        'type' => 'web_delivery',
        'guest_data' => [
            'phone' => '1234567890',
            // Missing name
        ],
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['guest_data.name']);
});

it('guest_data.phone is required when providing guest_data', function () {
    $product = Product::factory()->create(['base_price' => 50.00]);
    $branch = Branch::factory()->create();

    $this->postJson(route('cart.store'), [
        'product_id' => $product->id,
        'quantity' => '1',
    ]);

    $response = $this->postJson(route('orders.place'), [
        'branch_id' => $branch->id,
        'payment_method' => 'cod',
        'type' => 'web_delivery',
        'guest_data' => [
            'name' => 'Test User',
            // Missing phone
        ],
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['guest_data.phone']);
});

it('guest can place card payment order with billing data', function () {
    $product = Product::factory()->create(['base_price' => 100.00]);
    $branch = Branch::factory()->create();

    $this->postJson(route('cart.store'), [
        'product_id' => $product->id,
        'quantity' => '1',
    ]);

    $guestData = [
        'name' => 'Card Payer',
        'phone' => '5555555555',
        'phone_country_code' => '+20',
        'email' => 'cardpayer@example.com',
        'street' => 'Commerce St',
        'building' => '789',
        'city' => 'Cairo',
    ];

    $billingData = [
        'first_name' => 'Card',
        'last_name' => 'Payer',
        'email' => 'cardpayer@example.com',
        'phone_number' => '+201234567890',
        'apartment' => '5',
        'floor' => '3',
        'street' => 'Commerce St',
        'building' => '789',
        'city' => 'Cairo',
        'country' => 'EG',
    ];

    // Mock the payment gateway to avoid actual API calls
    $this->mock(App\Interfaces\PaymentGatewayInterface::class, function ($mock) {
        $mock->shouldReceive('getGatewayId')->andReturn('paymob');
        $mock->shouldReceive('createPaymentIntention')->andReturn([
            'success' => true,
            'checkout_url' => 'https://payment.gateway.com/checkout/abc123',
            'data' => ['transaction_id' => 'TXN123'],
        ]);
    });

    $response = $this->postJson(route('orders.place'), [
        'branch_id' => $branch->id,
        'payment_method' => 'card',
        'type' => 'web_delivery',
        'guest_data' => $guestData,
        'billing_data' => $billingData,
    ]);

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'success',
        'redirect_type',
        'redirect_url',
        'order_id',
    ]);

    expect($response->json('success'))->toBeTrue();
    expect($response->json('redirect_type'))->toBe('external');
    expect($response->json('redirect_url'))->toContain('checkout');
});

it('billing data includes guest information for card payment', function () {
    $product = Product::factory()->create(['base_price' => 100.00]);
    $branch = Branch::factory()->create();

    $this->postJson(route('cart.store'), [
        'product_id' => $product->id,
        'quantity' => '1',
    ]);

    $guestData = [
        'name' => 'Test Guest',
        'phone' => '1234567890',
        'phone_country_code' => '+20',
        'email' => 'test@example.com',
        'street' => 'Test Street',
        'building' => '100',
        'floor' => '5',
        'apartment' => '10',
        'city' => 'Alexandria',
    ];

    // Capture the billing data passed to payment gateway
    $capturedBillingData = null;
    $this->mock(App\Interfaces\PaymentGatewayInterface::class, function ($mock) use (&$capturedBillingData) {
        $mock->shouldReceive('getGatewayId')->andReturn('paymob');
        $mock->shouldReceive('createPaymentIntention')
            ->andReturnUsing(function ($order, $billingData) use (&$capturedBillingData) {
                $capturedBillingData = $billingData;

                return [
                    'success' => true,
                    'checkout_url' => 'https://payment.gateway.com/checkout',
                    'data' => [],
                ];
            });
    });

    $this->postJson(route('orders.place'), [
        'branch_id' => $branch->id,
        'payment_method' => 'card',
        'type' => 'web_delivery',
        'guest_data' => $guestData,
    ]);

    expect($capturedBillingData)->not->toBeNull();
    expect($capturedBillingData['phone_number'])->toBe('+201234567890');
    expect($capturedBillingData['email'])->toBe('test@example.com');
    expect($capturedBillingData['street'])->toBe('Test Street');
    expect($capturedBillingData['building'])->toBe('100');
    expect($capturedBillingData['city'])->toBe('Alexandria');
});

it('guest order has correct payment status after COD placement', function () {
    $product = Product::factory()->create(['base_price' => 50.00]);
    $branch = Branch::factory()->create();

    $this->postJson(route('cart.store'), [
        'product_id' => $product->id,
        'quantity' => '1',
    ]);

    $response = $this->postJson(route('orders.place'), [
        'branch_id' => $branch->id,
        'payment_method' => 'cod',
        'type' => 'web_delivery',
        'guest_data' => [
            'name' => 'Guest',
            'phone' => '1234567890',
            'phone_country_code' => '+20',
        ],
    ]);

    $orderId = $response->json('order_id');

    $this->assertDatabaseHas('orders', [
        'id' => $orderId,
        'payment_method' => PaymentMethod::COD->value,
        'payment_status' => PaymentStatus::PENDING->value,
    ]);
});

it('guest cannot place order with empty cart', function () {
    $branch = Branch::factory()->create();

    $response = $this->postJson(route('orders.place'), [
        'branch_id' => $branch->id,
        'payment_method' => 'cod',
        'type' => 'web_delivery',
        'guest_data' => [
            'name' => 'Guest',
            'phone' => '1234567890',
            'phone_country_code' => '+20',
        ],
    ]);

    $response->assertStatus(400);
    $response->assertJson([
        'success' => false,
    ]);
});

it('guest cannot place order when cart has inactive products', function () {
    $sessionCartId = 'guest_test_cart_inactive';
    $inactiveProduct = Product::factory()->create([
        'is_active' => false,
        'base_price' => 50.00,
    ]);
    $branch = Branch::factory()->create();

    $this->withSession(['guest_cart_id' => $sessionCartId]);

    $cart = Cart::factory()->create([
        'user_id' => null,
        'session_id' => $sessionCartId,
    ]);

    CartItem::factory()->create([
        'cart_id' => $cart->id,
        'product_id' => $inactiveProduct->id,
        'quantity' => '1.000',
    ]);

    $response = $this->postJson(route('orders.place'), [
        'branch_id' => $branch->id,
        'payment_method' => 'cod',
        'type' => 'web_delivery',
        'guest_data' => [
            'name' => 'Guest',
            'phone' => '1234567890',
            'phone_country_code' => '+20',
        ],
    ]);

    $response->assertStatus(400);
    $response->assertJsonPath('success', false);
    expect(data_get($response->json(), 'errors.order'))->not->toBeEmpty();
    expect((string) data_get($response->json(), 'errors.order.0'))->toContain('unavailable');
});

it('validates branch_id is required', function () {
    $product = Product::factory()->create(['base_price' => 50.00]);

    $this->postJson(route('cart.store'), [
        'product_id' => $product->id,
        'quantity' => '1',
    ]);

    $response = $this->postJson(route('orders.place'), [
        // Missing branch_id
        'payment_method' => 'cod',
        'type' => 'web_delivery',
        'guest_data' => [
            'name' => 'Guest',
            'phone' => '1234567890',
        ],
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['branch_id']);
});

it('validates payment_method is required', function () {
    $product = Product::factory()->create(['base_price' => 50.00]);
    $branch = Branch::factory()->create();

    $this->postJson(route('cart.store'), [
        'product_id' => $product->id,
        'quantity' => '1',
    ]);

    $response = $this->postJson(route('orders.place'), [
        'branch_id' => $branch->id,
        // Missing payment_method
        'type' => 'web_delivery',
        'guest_data' => [
            'name' => 'Guest',
            'phone' => '1234567890',
        ],
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['payment_method']);
});

it('validates payment_method is valid', function () {
    $product = Product::factory()->create(['base_price' => 50.00]);
    $branch = Branch::factory()->create();

    $this->postJson(route('cart.store'), [
        'product_id' => $product->id,
        'quantity' => '1',
    ]);

    $response = $this->postJson(route('orders.place'), [
        'branch_id' => $branch->id,
        'payment_method' => 'invalid_method',
        'type' => 'web_delivery',
        'guest_data' => [
            'name' => 'Guest',
            'phone' => '1234567890',
        ],
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['payment_method']);
});

it('validated guest_data.email format when provided', function () {
    $product = Product::factory()->create(['base_price' => 50.00]);
    $branch = Branch::factory()->create();

    $this->postJson(route('cart.store'), [
        'product_id' => $product->id,
        'quantity' => '1',
    ]);

    $response = $this->postJson(route('orders.place'), [
        'branch_id' => $branch->id,
        'payment_method' => 'cod',
        'type' => 'web_delivery',
        'guest_data' => [
            'name' => 'Guest',
            'phone' => '1234567890',
            'email' => 'invalid-email',
        ],
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['guest_data.email']);
});
