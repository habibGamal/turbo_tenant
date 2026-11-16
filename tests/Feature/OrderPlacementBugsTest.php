<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CouponsPromotions\src\Models\Coupon;
use App\Plugins\GuestCheckout\src\Models\GuestUser;
use App\Plugins\LocationsDelivery\src\Models\Address;
use App\Plugins\LocationsDelivery\src\Models\Area;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\LocationsDelivery\src\Models\Governorate;
use App\Plugins\OrderManagement\src\Contracts\PaymentInitiator;
use App\Plugins\OrderManagement\src\DTOs\OrderManagementConfig;
use App\Plugins\OrderManagement\src\Events\OrderCreated;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Settings\OrderSettingKey;
use App\Plugins\StoreSettings\src\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

final class OrderPlacementBugsTest extends TestCase
{
    use RefreshDatabase;

    private SettingService $settingService;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settingService = app(SettingService::class);
        $this->branch = Branch::factory()->create(['is_active' => true]);
    }

    /**
     * Bug 1.2: Tax setting ignored.
     */
    public function test_bug_1_2_tax_rate_read_from_tenant_settings_as_fraction(): void
    {
        $this->settingService->set(OrderSettingKey::TAX_RATE, '14');

        $config = app(OrderManagementConfig::class);
        $this->assertSame(0.14, $config->getTaxRate());

        // When missing or invalid, returns 0.0
        $this->settingService->set(OrderSettingKey::TAX_RATE, 'invalid');
        $this->assertSame(0.0, $config->getTaxRate());

        $this->settingService->set(OrderSettingKey::TAX_RATE, '0');
        $this->assertSame(0.0, $config->getTaxRate());
    }

    /**
     * Bug 1.3: Minimum order amount never enforced.
     */
    public function test_bug_1_3_minimum_order_amount_enforced(): void
    {
        $this->settingService->set(OrderSettingKey::MIN_ORDER_AMOUNT, '100');

        $product = Product::factory()->create(['base_price' => 40.00, 'is_active' => true]);

        // Put 1 item of 40.00 in cart (subtotal = 40.00 < 100.00)
        $this->postJson(route('api.cart.store'), [
            'product_id' => $product->id,
            'quantity' => '1',
        ])->assertSuccessful();

        $response = $this->postJson(route('api.orders.place'), [
            'branch_id' => $this->branch->id,
            'payment_method' => 'cod',
            'type' => 'web_takeaway',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'status' => 'error',
        ]);
        $this->assertStringContainsString('100', json_encode($response->json()) ?: '');
        $this->assertSame(0, Order::count());
    }

    /**
     * Bug 1.5: OrderCreated fires after commit with items and context; bare Order::create does not fire it.
     */
    public function test_bug_1_5_bare_order_create_does_not_dispatch_order_created(): void
    {
        Event::fake([OrderCreated::class]);

        Order::factory()->create();

        Event::assertNotDispatched(OrderCreated::class);
    }

    public function test_bug_1_5_order_created_listener_sees_items_and_validation_context(): void
    {
        $product = Product::factory()->create(['base_price' => 50.00, 'is_active' => true]);

        $this->postJson(route('api.cart.store'), [
            'product_id' => $product->id,
            'quantity' => '2',
        ])->assertSuccessful();

        $itemsCountWhenEventFired = null;
        $contextWhenEventFired = null;

        Event::listen(OrderCreated::class, function (OrderCreated $event) use (&$itemsCountWhenEventFired, &$contextWhenEventFired) {
            $itemsCountWhenEventFired = $event->order->items()->count();
            $contextWhenEventFired = $event->validationContext;
        });

        $response = $this->postJson(route('api.orders.place'), [
            'branch_id' => $this->branch->id,
            'payment_method' => 'cod',
            'type' => 'web_takeaway',
        ]);

        $response->assertSuccessful();
        $this->assertSame(1, $itemsCountWhenEventFired);
        $this->assertNotNull($contextWhenEventFired);
    }

    public function test_bug_1_5_order_created_not_dispatched_when_creation_fails(): void
    {
        Event::fake([OrderCreated::class]);

        // Attempting to place order with empty cart should fail
        $response = $this->postJson(route('api.orders.place'), [
            'branch_id' => $this->branch->id,
            'payment_method' => 'cod',
            'type' => 'web_takeaway',
        ]);

        $response->assertStatus(422);
        Event::assertNotDispatched(OrderCreated::class);
    }

    /**
     * Bug 1.6: Coupon usage released when payment fails.
     */
    public function test_bug_1_6_coupon_usage_released_when_payment_fails(): void
    {
        $coupon = Coupon::factory()->create([
            'code' => 'SAVE20',
            'type' => 'fixed',
            'value' => 20.00,
            'is_active' => true,
            'usage_count' => 0,
            'total_consumed' => 0.00,
        ]);

        $product = Product::factory()->create(['base_price' => 100.00, 'is_active' => true]);

        $this->postJson(route('api.cart.store'), [
            'product_id' => $product->id,
            'quantity' => '1',
        ])->assertSuccessful();

        // Mock payment gateway to fail
        $mockGateway = Mockery::mock(PaymentInitiator::class);
        $mockGateway->shouldReceive('getGatewayId')->andReturn('paymob');
        $mockGateway->shouldReceive('createPaymentIntention')
            ->once()
            ->andReturn(\App\Plugins\OrderManagement\src\Contracts\PaymentIntention::failed('Card declined'));
        $this->app->instance(PaymentInitiator::class, $mockGateway);

        $response = $this->postJson(route('api.orders.place'), [
            'branch_id' => $this->branch->id,
            'payment_method' => 'card',
            'type' => 'web_takeaway',
            'coupon_id' => $coupon->id,
        ]);

        $response->assertStatus(422);

        $coupon->refresh();
        $this->assertSame(0, $coupon->usage_count);
        $this->assertEquals(0.00, $coupon->total_consumed);
    }

    /**
     * Bug 1.13: Order JSON exposes internals.
     */
    public function test_bug_1_13_orders_index_and_show_hide_internal_attributes(): void
    {
        $order = Order::factory()->create([
            'user_id' => auth()->id(),
            'branch_id' => $this->branch->id,
            'payment_data' => ['token' => 'secret_123', 'cvv' => '999'],
            'transaction_id' => 'TXN-999999',
            'merchant_order_id' => 'MERCH-888888',
            'paymob_order_id' => 'PAYMOB-777777',
            'shift_id' => 42,
            'pos_failure_reason' => 'Connection timed out to terminal 3',
        ]);

        // Test Index
        $indexResponse = $this->getJson(route('api.orders.index'));
        $indexResponse->assertSuccessful();

        $indexData = $indexResponse->json('data.0');
        $this->assertArrayNotHasKey('payment_data', $indexData);
        $this->assertArrayNotHasKey('transaction_id', $indexData);
        $this->assertArrayNotHasKey('merchant_order_id', $indexData);
        $this->assertArrayNotHasKey('paymob_order_id', $indexData);
        $this->assertArrayNotHasKey('shift_id', $indexData);
        $this->assertArrayNotHasKey('pos_failure_reason', $indexData);

        // Test Show
        $showResponse = $this->getJson(route('api.orders.show', ['orderId' => $order->id]));
        $showResponse->assertSuccessful();

        $showData = $showResponse->json('data');
        $this->assertArrayNotHasKey('payment_data', $showData);
        $this->assertArrayNotHasKey('transaction_id', $showData);
        $this->assertArrayNotHasKey('merchant_order_id', $showData);
        $this->assertArrayNotHasKey('paymob_order_id', $showData);
        $this->assertArrayNotHasKey('shift_id', $showData);
        $this->assertArrayNotHasKey('pos_failure_reason', $showData);
    }

    /**
     * Bug 1.14: Guest delivery orders lose their address.
     */
    public function test_bug_1_14_guest_delivery_order_snapshots_address_and_remains_frozen(): void
    {
        \Illuminate\Support\Facades\Auth::logout();

        $governorate = Governorate::factory()->create(['name' => 'Cairo Gov']);
        $area = Area::factory()->create([
            'governorate_id' => $governorate->id,
            'name' => 'Zamalek',
        ]);

        $product = Product::factory()->create(['base_price' => 50.00, 'is_active' => true]);

        $this->postJson(route('api.cart.store'), [
            'product_id' => $product->id,
            'quantity' => '1',
        ])->assertSuccessful();

        $guestData = [
            'name' => 'Guest Customer',
            'phone' => '01012345678',
            'email' => 'guest@example.com',
            'street' => '26 July Street',
            'building' => 'عمارة 14B',
            'floor' => '3',
            'apartment' => '12',
            'city' => 'Cairo',
            'area_id' => $area->id,
        ];

        $response = $this->postJson(route('api.orders.place'), [
            'branch_id' => $this->branch->id,
            'payment_method' => 'cod',
            'type' => 'web_delivery',
            'guest_data' => $guestData,
        ]);

        $response->assertSuccessful();
        $orderId = $response->json('data.order_id');
        $order = Order::findOrFail($orderId);

        $this->assertNotNull($order->delivery_address);
        $this->assertSame('26 July Street', $order->delivery_address['street']);
        $this->assertSame('عمارة 14B', $order->delivery_address['building']);
        $this->assertSame('3', $order->delivery_address['floor']);
        $this->assertSame('12', $order->delivery_address['apartment']);
        $this->assertSame('Cairo', $order->delivery_address['city']);
        $this->assertSame($area->id, $order->delivery_address['area_id']);
        $this->assertSame('Zamalek', $order->delivery_address['area_name']);
        $this->assertSame('Cairo Gov', $order->delivery_address['governorate_name']);

        // Update GuestUser record later
        $guestUser = GuestUser::where('phone', '01012345678')->firstOrFail();
        $guestUser->update([
            'street' => 'Completely Different Street',
            'building' => 'New Tower 99',
            'city' => 'Alexandria',
        ]);

        $order->refresh();
        $this->assertSame('26 July Street', $order->delivery_address['street']);
        $this->assertSame('عمارة 14B', $order->delivery_address['building']);
        $this->assertSame('Cairo', $order->delivery_address['city']);
    }

    /**
     * Bug 1.15: Inactive branch accepted.
     */
    public function test_bug_1_15_inactive_branch_fails_validation_and_no_order_created(): void
    {
        $inactiveBranch = Branch::factory()->create(['is_active' => false]);
        $product = Product::factory()->create(['base_price' => 50.00, 'is_active' => true]);

        $this->postJson(route('api.cart.store'), [
            'product_id' => $product->id,
            'quantity' => '1',
        ])->assertSuccessful();

        $response = $this->postJson(route('api.orders.place'), [
            'branch_id' => $inactiveBranch->id,
            'payment_method' => 'cod',
            'type' => 'web_takeaway',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'status' => 'error',
            'errors' => [
                'branch_id' => [
                    'The selected branch id is invalid.',
                ],
            ],
        ]);
        $this->assertSame(0, Order::count());
    }
}
