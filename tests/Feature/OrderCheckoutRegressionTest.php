<?php

declare(strict_types=1);

use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\GuestCheckout\src\Models\GuestUser;
use App\Plugins\GuestCheckout\src\Services\GuestTrackingTokenService;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\OrderManagement\src\Models\Order;

beforeEach(function (): void {
    $this->branch = Branch::factory()->create(['is_active' => true]);
    $this->product = Product::factory()->create([
        'name' => 'Original Coffee', 'base_price' => 100, 'price_after_discount' => null,
        'is_active' => true, 'sell_by_weight' => false, 'extra_option_id' => null,
    ]);
});

it('uses the current product price at checkout and snapshots the charged price', function (): void {
    $this->postJson('/api/cart/items', ['product_id' => $this->product->id, 'quantity' => 2])->assertCreated();
    $this->product->update(['base_price' => 125]);
    $this->getJson('/api/cart')->assertOk()->assertJsonPath('data.total', 250);

    $response = $this->postJson('/api/orders/place', [
        'branch_id' => $this->branch->id, 'payment_method' => 'cod', 'type' => 'web_takeaway',
    ])->assertOk();
    $order = Order::with('items')->findOrFail($response->json('data.order_id'));
    expect($order->sub_total)->toBe(250.0)
        ->and($order->items->first()->unit_price)->toBe(125.0);

    $this->product->update(['name' => 'Renamed Coffee', 'base_price' => 200]);
    $this->getJson('/api/orders/'.$order->id)->assertOk()
        ->assertJsonPath('data.items.0.product_name', 'Original Coffee')
        ->assertJsonPath('data.items.0.unit_price', 125);
});

it('preserves the cart when a branch becomes inactive before checkout', function (): void {
    $this->postJson('/api/cart/items', ['product_id' => $this->product->id, 'quantity' => 2])->assertCreated();
    $this->branch->update(['is_active' => false]);
    $this->postJson('/api/orders/place', [
        'branch_id' => $this->branch->id, 'payment_method' => 'cod', 'type' => 'web_takeaway',
    ])->assertUnprocessable()->assertJsonValidationErrors('branch_id');
    expect(Order::count())->toBe(0);
    $this->getJson('/api/cart')->assertOk()->assertJsonPath('data.items.0.product_id', $this->product->id)
        ->assertJsonPath('data.items.0.quantity', 2);
});

it('preserves the cart when a product becomes unavailable before checkout', function (): void {
    $this->postJson('/api/cart/items', ['product_id' => $this->product->id, 'quantity' => 1])->assertCreated();
    $this->product->update(['is_active' => false]);
    $this->postJson('/api/orders/place', [
        'branch_id' => $this->branch->id, 'payment_method' => 'cod', 'type' => 'web_takeaway',
    ])->assertUnprocessable();
    expect(Order::count())->toBe(0);
    $this->getJson('/api/cart')->assertOk()->assertJsonPath('data.items.0.product_id', $this->product->id);
});

it('does not place a second order when the completed cart is submitted again', function (): void {
    $this->postJson('/api/cart/items', ['product_id' => $this->product->id, 'quantity' => 1])->assertCreated();
    $payload = ['branch_id' => $this->branch->id, 'payment_method' => 'cod', 'type' => 'web_takeaway'];
    $this->postJson('/api/orders/place', $payload)->assertOk();
    $this->postJson('/api/orders/place', $payload)->assertUnprocessable();
    expect(Order::count())->toBe(1);
    $this->getJson('/api/cart')->assertOk()->assertJsonCount(0, 'data.items');
});

it('rejects a valid guest tracking token when paired with a different guest order', function (): void {
    $guest = GuestUser::factory()->create();
    $first = Order::factory()->create(['guest_user_id' => $guest->id, 'user_id' => null]);
    $second = Order::factory()->create(['guest_user_id' => $guest->id, 'user_id' => null]);
    $token = app(GuestTrackingTokenService::class)->generate($first);
    $this->postJson('/api/guest-checkout/track', ['order_number' => $second->order_number, 'token' => $token])
        ->assertNotFound()->assertJsonPath('status', 'error');
    $this->postJson('/api/guest-checkout/track', ['order_number' => $first->order_number, 'token' => $token])
        ->assertOk()->assertJsonPath('data.order_number', $first->order_number);
});

it('charges the current area delivery fee when it changes before placing a guest order', function (): void {
    auth()->logout();
    $area = App\Plugins\LocationsDelivery\src\Models\Area::factory()->create([
        'branch_id' => $this->branch->id, 'shipping_cost' => 20, 'is_active' => true,
    ]);
    $this->postJson('/api/cart/items', ['product_id' => $this->product->id, 'quantity' => 1])->assertCreated();
    $area->update(['shipping_cost' => 35]);
    $response = $this->postJson('/api/orders/place', [
        'branch_id' => $this->branch->id, 'payment_method' => 'cod', 'type' => 'web_delivery',
        'guest_data' => ['name' => 'Delivery Customer', 'phone' => '01012345678', 'area_id' => $area->id],
    ])->assertOk();
    $order = Order::findOrFail($response->json('data.order_id'));
    expect($order->delivery_fee)->toBe(35.0)->and($order->total)->toBe(135.0);
});

it('rejects a guest delivery area disabled before order placement', function (): void {
    auth()->logout();
    $area = App\Plugins\LocationsDelivery\src\Models\Area::factory()->create([
        'branch_id' => $this->branch->id, 'is_active' => true,
    ]);
    $this->postJson('/api/cart/items', ['product_id' => $this->product->id, 'quantity' => 1])->assertCreated();
    $area->update(['is_active' => false]);
    $this->postJson('/api/orders/place', [
        'branch_id' => $this->branch->id, 'payment_method' => 'cod', 'type' => 'web_delivery',
        'guest_data' => ['name' => 'Delivery Customer', 'phone' => '01012345678', 'area_id' => $area->id],
    ])->assertUnprocessable();
    expect(Order::count())->toBe(0);
    $this->getJson('/api/cart')->assertOk()->assertJsonCount(1, 'data.items');
});

it('retains ordered variant and extra snapshots after the catalog is edited', function (): void {
    $option = App\Plugins\CatalogProducts\src\Models\ExtraOption::factory()->create(['min_selections' => 0, 'allow_multiple' => true]);
    $extra = App\Plugins\CatalogProducts\src\Models\ExtraOptionItem::factory()->create([
        'extra_option_id' => $option->id, 'name' => 'Original Extra', 'price' => 10, 'is_default' => false,
    ]);
    $this->product->update(['extra_option_id' => $option->id]);
    $variant = App\Plugins\CatalogProducts\src\Models\ProductVariant::factory()->create([
        'product_id' => $this->product->id, 'name' => 'Original Variant', 'price' => 125, 'is_available' => true,
    ]);
    $this->postJson('/api/cart/items', [
        'product_id' => $this->product->id, 'variant_id' => $variant->id, 'quantity' => 1,
        'extras' => [['id' => $extra->id, 'quantity' => 1]],
    ])->assertCreated();
    $response = $this->postJson('/api/orders/place', [
        'branch_id' => $this->branch->id, 'payment_method' => 'cod', 'type' => 'web_takeaway',
    ])->assertOk();
    $variant->update(['name' => 'Renamed Variant', 'price' => 300]);
    $extra->update(['name' => 'Renamed Extra', 'price' => 40]);
    $this->getJson('/api/orders/'.$response->json('data.order_id'))->assertOk()
        ->assertJsonPath('data.items.0.variant_name', 'Original Variant')
        ->assertJsonPath('data.items.0.unit_price', 125)
        ->assertJsonPath('data.items.0.extras.0.extra_name', 'Original Extra')
        ->assertJsonPath('data.items.0.extras.0.extra_price', 10);
});

it('preserves the first guest delivery address when the same guest places a second order', function (): void {
    auth()->logout();
    $area = App\Plugins\LocationsDelivery\src\Models\Area::factory()->create([
        'branch_id' => $this->branch->id, 'is_active' => true,
    ]);
    $payload = [
        'branch_id' => $this->branch->id, 'payment_method' => 'cod', 'type' => 'web_delivery',
        'guest_data' => ['name' => 'Guest Customer', 'phone' => '01012345678', 'area_id' => $area->id, 'street' => 'First Street'],
    ];
    $this->postJson('/api/cart/items', ['product_id' => $this->product->id, 'quantity' => 1])->assertCreated();
    $first = $this->postJson('/api/orders/place', $payload)->assertOk();
    $this->postJson('/api/cart/items', ['product_id' => $this->product->id, 'quantity' => 1])->assertCreated();
    $payload['guest_data']['street'] = 'Second Street';
    $second = $this->postJson('/api/orders/place', $payload)->assertOk();
    $firstOrder = Order::findOrFail($first->json('data.order_id'));
    $secondOrder = Order::findOrFail($second->json('data.order_id'));
    expect($firstOrder->guest_user_id)->toBe($secondOrder->guest_user_id)
        ->and($firstOrder->delivery_address['street'])->toBe('First Street')
        ->and($secondOrder->delivery_address['street'])->toBe('Second Street');
});

it('rejects an authenticated delivery when the saved address area is disabled', function (): void {
    $area = App\Plugins\LocationsDelivery\src\Models\Area::factory()->create([
        'branch_id' => $this->branch->id, 'is_active' => true,
    ]);
    $address = App\Plugins\LocationsDelivery\src\Models\Address::factory()->create([
        'user_id' => auth()->id(), 'area_id' => $area->id,
    ]);
    $this->postJson('/api/cart/items', ['product_id' => $this->product->id, 'quantity' => 1])->assertCreated();
    $area->update(['is_active' => false]);
    $this->postJson('/api/orders/place', [
        'branch_id' => $this->branch->id, 'payment_method' => 'cod', 'type' => 'web_delivery', 'address_id' => $address->id,
    ])->assertUnprocessable();
    expect(Order::count())->toBe(0);
    $this->getJson('/api/cart')->assertOk()->assertJsonCount(1, 'data.items');
});

it('rejects unknown status values and nonexistent orders without writing status events', function (): void {
    $settings = app(App\Plugins\StoreSettings\src\Services\SettingService::class);
    $settings->set(App\Plugins\OrderManagement\src\Support\OrderManagementSettings::INTEGRATION_TOKEN_KEY, 'e2e-test-secret');
    $order = Order::factory()->create(['status' => 'pending', 'branch_id' => $this->branch->id]);
    $this->withToken('e2e-test-secret')->postJson('/api/order-status', [
        'orderNumber' => $order->order_number, 'status' => 'not-a-status',
    ])->assertUnprocessable()->assertJsonValidationErrors('status');
    $this->withToken('e2e-test-secret')->postJson('/api/order-status', [
        'orderNumber' => 'NONEXISTENT-ORDER', 'status' => 'processing',
    ])->assertUnprocessable()->assertJsonValidationErrors('orderNumber');
    expect($order->fresh()->status->value)->toBe('pending')
        ->and(App\Plugins\OrderManagement\src\Models\OrderStatusEvent::where('order_id', $order->id)->count())->toBe(0);
});

it('keeps the cart available when the online payment gateway cannot create a checkout', function (): void {
    $this->postJson('/api/cart/items', ['product_id' => $this->product->id, 'quantity' => 1])->assertCreated();
    $gateway = Mockery::mock(App\Plugins\OrderManagement\src\Contracts\PaymentInitiator::class);
    $gateway->shouldReceive('getGatewayId')->andReturn('paymob');
    $gateway->shouldReceive('createPaymentIntention')->once()
        ->andReturn(App\Plugins\OrderManagement\src\Contracts\PaymentIntention::failed('Payment provider unavailable'));
    $this->app->instance(App\Plugins\OrderManagement\src\Contracts\PaymentInitiator::class, $gateway);

    $this->postJson('/api/orders/place', [
        'branch_id' => $this->branch->id, 'payment_method' => 'card', 'type' => 'web_takeaway',
    ])->assertUnprocessable()->assertJsonPath('status', 'error');
    $order = Order::firstOrFail();
    expect($order->status->value)->toBe('cancelled')
        ->and($order->payment_status->value)->toBe('failed');
    $this->getJson('/api/orders/'.$order->id)->assertOk();
    $this->getJson('/api/cart')->assertOk()->assertJsonPath('data.items.0.product_id', $this->product->id);
});

it('keeps the cart when the online gateway returns success without a checkout URL', function (): void {
    $this->postJson('/api/cart/items', ['product_id' => $this->product->id, 'quantity' => 1])->assertCreated();
    $gateway = Mockery::mock(App\Plugins\OrderManagement\src\Contracts\PaymentInitiator::class);
    $gateway->shouldReceive('getGatewayId')->andReturn('paymob');
    $gateway->shouldReceive('createPaymentIntention')->once()
        ->andReturn(App\Plugins\OrderManagement\src\Contracts\PaymentIntention::created(null));
    $this->app->instance(App\Plugins\OrderManagement\src\Contracts\PaymentInitiator::class, $gateway);
    $this->postJson('/api/orders/place', [
        'branch_id' => $this->branch->id, 'payment_method' => 'card', 'type' => 'web_takeaway',
    ])->assertStatus(502);
    $this->getJson('/api/cart')->assertOk()->assertJsonPath('data.items.0.product_id', $this->product->id);
});

it('clears the cart after the online gateway creates a usable checkout URL', function (): void {
    $this->postJson('/api/cart/items', ['product_id' => $this->product->id, 'quantity' => 1])->assertCreated();
    $gateway = Mockery::mock(App\Plugins\OrderManagement\src\Contracts\PaymentInitiator::class);
    $gateway->shouldReceive('getGatewayId')->andReturn('paymob');
    $gateway->shouldReceive('createPaymentIntention')->once()
        ->andReturn(App\Plugins\OrderManagement\src\Contracts\PaymentIntention::created('https://example.test/payment'));
    $this->app->instance(App\Plugins\OrderManagement\src\Contracts\PaymentInitiator::class, $gateway);
    $this->postJson('/api/orders/place', [
        'branch_id' => $this->branch->id, 'payment_method' => 'card', 'type' => 'web_takeaway',
    ])->assertOk()->assertJsonPath('data.requires_payment', true);
    $this->getJson('/api/cart')->assertOk()->assertJsonCount(0, 'data.items');
});

it('keeps a guest cart after online payment initialization fails', function (): void {
    auth()->logout();
    $this->postJson('/api/cart/items', ['product_id' => $this->product->id, 'quantity' => 1])->assertCreated();
    $gateway = Mockery::mock(App\Plugins\OrderManagement\src\Contracts\PaymentInitiator::class);
    $gateway->shouldReceive('getGatewayId')->andReturn('paymob');
    $gateway->shouldReceive('createPaymentIntention')->once()
        ->andReturn(App\Plugins\OrderManagement\src\Contracts\PaymentIntention::failed('Payment provider unavailable'));
    $this->app->instance(App\Plugins\OrderManagement\src\Contracts\PaymentInitiator::class, $gateway);
    $this->postJson('/api/orders/place', [
        'branch_id' => $this->branch->id, 'payment_method' => 'card', 'type' => 'web_takeaway',
        'guest_data' => ['name' => 'Guest Customer', 'phone' => '01012345678'],
    ])->assertUnprocessable();
    $this->getJson('/api/cart')->assertOk()->assertJsonPath('data.items.0.product_id', $this->product->id);
});

it('keeps the cart and leaves an ambiguous gateway exception pending for reconciliation', function (): void {
    $this->postJson('/api/cart/items', ['product_id' => $this->product->id, 'quantity' => 1])->assertCreated();
    $gateway = Mockery::mock(App\Plugins\OrderManagement\src\Contracts\PaymentInitiator::class);
    $gateway->shouldReceive('getGatewayId')->andReturn('paymob');
    $gateway->shouldReceive('createPaymentIntention')->once()->andThrow(new RuntimeException('simulated network failure'));
    $this->app->instance(App\Plugins\OrderManagement\src\Contracts\PaymentInitiator::class, $gateway);
    $this->postJson('/api/orders/place', [
        'branch_id' => $this->branch->id, 'payment_method' => 'card', 'type' => 'web_takeaway',
    ])->assertUnprocessable()->assertJsonPath('message', 'Failed to place order');
    $order = Order::firstOrFail();
    expect($order->status->value)->toBe('pending')
        ->and($order->payment_status->value)->toBe('pending');
    $this->getJson('/api/cart')->assertOk()->assertJsonPath('data.items.0.product_id', $this->product->id);
});

it('allows a customer to retry the retained cart after payment initialization fails', function (): void {
    $this->postJson('/api/cart/items', ['product_id' => $this->product->id, 'quantity' => 1])->assertCreated();
    $gateway = Mockery::mock(App\Plugins\OrderManagement\src\Contracts\PaymentInitiator::class);
    $gateway->shouldReceive('getGatewayId')->andReturn('paymob');
    $gateway->shouldReceive('createPaymentIntention')->twice()->andReturn(
        App\Plugins\OrderManagement\src\Contracts\PaymentIntention::failed('Payment provider unavailable'),
        App\Plugins\OrderManagement\src\Contracts\PaymentIntention::created('https://example.test/payment'),
    );
    $this->app->instance(App\Plugins\OrderManagement\src\Contracts\PaymentInitiator::class, $gateway);
    $payload = ['branch_id' => $this->branch->id, 'payment_method' => 'card', 'type' => 'web_takeaway'];
    $this->postJson('/api/orders/place', $payload)->assertUnprocessable();
    $this->getJson('/api/cart')->assertOk()->assertJsonCount(1, 'data.items');
    $retry = $this->postJson('/api/orders/place', $payload)->assertOk()->assertJsonPath('data.requires_payment', true);
    expect(Order::where('status', 'cancelled')->count())->toBe(1)
        ->and(Order::whereKey($retry->json('data.order_id'))->where('status', 'pending')->exists())->toBeTrue();
    $this->getJson('/api/cart')->assertOk()->assertJsonCount(0, 'data.items');
});

it('does not delete products added while the payment gateway is initializing', function (): void {
    $this->postJson('/api/cart/items', ['product_id' => $this->product->id, 'quantity' => 1])->assertCreated();
    $added = Product::factory()->create(['name' => 'Added During Payment', 'base_price' => 30, 'price_after_discount' => null, 'is_active' => true]);
    $user = auth()->user();
    $gateway = Mockery::mock(App\Plugins\OrderManagement\src\Contracts\PaymentInitiator::class);
    $gateway->shouldReceive('getGatewayId')->andReturn('paymob');
    $gateway->shouldReceive('createPaymentIntention')->once()->andReturnUsing(function () use ($user, $added) {
        app(App\Plugins\CartEngine\src\Services\Contracts\CartServiceInterface::class)->addItem($user, $added->id, null, '1');

        return App\Plugins\OrderManagement\src\Contracts\PaymentIntention::created('https://example.test/payment');
    });
    $this->app->instance(App\Plugins\OrderManagement\src\Contracts\PaymentInitiator::class, $gateway);
    $this->postJson('/api/orders/place', [
        'branch_id' => $this->branch->id, 'payment_method' => 'card', 'type' => 'web_takeaway',
    ])->assertOk();
    $this->getJson('/api/cart')->assertOk()->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.product_id', $added->id);
});

it('preserves an ordered cart line when its quantity changes during payment initialization', function (): void {
    $this->postJson('/api/cart/items', ['product_id' => $this->product->id, 'quantity' => 1])->assertCreated();
    $user = auth()->user();
    $product = $this->product;
    $gateway = Mockery::mock(App\Plugins\OrderManagement\src\Contracts\PaymentInitiator::class);
    $gateway->shouldReceive('getGatewayId')->andReturn('paymob');
    $gateway->shouldReceive('createPaymentIntention')->once()->andReturnUsing(function () use ($user, $product) {
        app(App\Plugins\CartEngine\src\Services\Contracts\CartServiceInterface::class)->addItem($user, $product->id, null, '1');

        return App\Plugins\OrderManagement\src\Contracts\PaymentIntention::created('https://example.test/payment');
    });
    $this->app->instance(App\Plugins\OrderManagement\src\Contracts\PaymentInitiator::class, $gateway);
    $response = $this->postJson('/api/orders/place', [
        'branch_id' => $this->branch->id, 'payment_method' => 'card', 'type' => 'web_takeaway',
    ])->assertOk();
    $this->getJson('/api/orders/'.$response->json('data.order_id'))->assertOk()->assertJsonPath('data.items.0.quantity', '1.000');
    $this->getJson('/api/cart')->assertOk()->assertJsonPath('data.items.0.quantity', 2);
});

it('removes purchased guest cart lines after online payment initialization succeeds', function (): void {
    auth()->logout();
    $this->postJson('/api/cart/items', ['product_id' => $this->product->id, 'quantity' => 1])->assertCreated();
    $gateway = Mockery::mock(App\Plugins\OrderManagement\src\Contracts\PaymentInitiator::class);
    $gateway->shouldReceive('getGatewayId')->andReturn('paymob');
    $gateway->shouldReceive('createPaymentIntention')->once()
        ->andReturn(App\Plugins\OrderManagement\src\Contracts\PaymentIntention::created('https://example.test/payment'));
    $this->app->instance(App\Plugins\OrderManagement\src\Contracts\PaymentInitiator::class, $gateway);
    $this->postJson('/api/orders/place', [
        'branch_id' => $this->branch->id, 'payment_method' => 'card', 'type' => 'web_takeaway',
        'guest_data' => ['name' => 'Guest Customer', 'phone' => '01012345678'],
    ])->assertOk()->assertJsonPath('data.requires_payment', true);
    $this->getJson('/api/cart')->assertOk()->assertJsonCount(0, 'data.items');
});

it('retains weighted variant and extra selections after a failed gateway attempt and removes them after a successful retry', function (): void {
    $weight = App\Plugins\CatalogProducts\src\Models\WeightOption::factory()->create(['unit' => 'kg']);
    $value = $weight->values()->create(['value' => '0.250', 'label' => '250g', 'sort_order' => 1]);
    $option = App\Plugins\CatalogProducts\src\Models\ExtraOption::factory()->create(['min_selections' => 0, 'allow_multiple' => true]);
    $extra = App\Plugins\CatalogProducts\src\Models\ExtraOptionItem::factory()->create([
        'extra_option_id' => $option->id, 'name' => 'Extra', 'price' => 10, 'allow_quantity' => true,
    ]);
    $this->product->update(['sell_by_weight' => true, 'weight_options_id' => $weight->id, 'extra_option_id' => $option->id]);
    $variant = App\Plugins\CatalogProducts\src\Models\ProductVariant::factory()->create([
        'product_id' => $this->product->id, 'name' => 'Variant', 'price' => 120, 'is_available' => true,
    ]);
    $this->postJson('/api/cart/items', [
        'product_id' => $this->product->id, 'variant_id' => $variant->id, 'quantity' => '0.25',
        'weight_option_value_id' => $value->id, 'weight_multiplier' => 2, 'extras' => [['id' => $extra->id, 'quantity' => 2]],
    ])->assertCreated();
    $gateway = Mockery::mock(App\Plugins\OrderManagement\src\Contracts\PaymentInitiator::class);
    $gateway->shouldReceive('getGatewayId')->andReturn('paymob');
    $gateway->shouldReceive('createPaymentIntention')->twice()->andReturn(
        App\Plugins\OrderManagement\src\Contracts\PaymentIntention::failed('Payment provider unavailable'),
        App\Plugins\OrderManagement\src\Contracts\PaymentIntention::created('https://example.test/payment'),
    );
    $this->app->instance(App\Plugins\OrderManagement\src\Contracts\PaymentInitiator::class, $gateway);
    $payload = ['branch_id' => $this->branch->id, 'payment_method' => 'card', 'type' => 'web_takeaway'];
    $this->postJson('/api/orders/place', $payload)->assertUnprocessable();
    $this->getJson('/api/cart')->assertOk()->assertJsonPath('data.items.0.variant.id', $variant->id)
        ->assertJsonPath('data.items.0.weight_option_value.id', $value->id)
        ->assertJsonPath('data.items.0.weight_multiplier', 2)
        ->assertJsonPath('data.items.0.extras.0.quantity', 2);
    $this->postJson('/api/orders/place', $payload)->assertOk();
    $this->getJson('/api/cart')->assertOk()->assertJsonCount(0, 'data.items');
});
