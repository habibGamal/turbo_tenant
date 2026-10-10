<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTOs\Order\PlaceOrderData;
use App\Enums\OrderPosStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SettingKey;
use App\Interfaces\PaymentGatewayInterface;
use App\Models\Address;
use App\Models\Area;
use App\Models\Branch;
use App\Models\Coupon;
use App\Models\ExtraOption;
use App\Models\ExtraOptionItem;
use App\Models\Governorate;
use App\Models\GuestUser;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Services\Order\Contracts\PlaceOrderServiceInterface;
use App\Services\Payment\Contracts\PaymentCallbackServiceInterface;
use App\Services\Payment\Contracts\PaymentWebhookServiceInterface;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use Mockery;
use Tests\TestCase;

final class PlaceOrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private PlaceOrderServiceInterface $placeOrderService;

    private PaymentCallbackServiceInterface $callbackService;

    private PaymentWebhookServiceInterface $webhookService;

    private CartService $cartService;

    private SettingService $settingService;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cartService = app(CartService::class);
        $this->settingService = app(SettingService::class);
        $this->placeOrderService = app(PlaceOrderServiceInterface::class);
        $this->callbackService = app(PaymentCallbackServiceInterface::class);
        $this->webhookService = app(PaymentWebhookServiceInterface::class);

        $this->branch = Branch::factory()->create();
        Session::flush();
    }

    public function test_cannot_place_order_with_empty_cart(): void
    {
        $user = User::factory()->create();

        $data = new PlaceOrderData(
            user: $user,
            branchId: $this->branch->id,
            paymentMethod: PaymentMethod::COD
        );

        $result = $this->placeOrderService->placeOrder($data);

        $this->assertFalse($result->isSuccessful());
        $this->assertSame('Cart is empty', $result->errorMessage);
    }

    public function test_cannot_place_order_with_inactive_products(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'is_active' => true,
            'base_price' => 50.00,
            'price_after_discount' => null,
            'sell_by_weight' => false,
        ]);

        $this->cartService->addItem($user, $product->id, null, '1');

        // Deactivate product after adding to cart
        $product->update(['is_active' => false]);

        $data = new PlaceOrderData(
            user: $user,
            branchId: $this->branch->id,
            paymentMethod: PaymentMethod::COD
        );

        $result = $this->placeOrderService->placeOrder($data);

        $this->assertFalse($result->isSuccessful());
        $this->assertStringContainsString('Some products are unavailable', (string) $result->errorMessage);
    }

    public function test_cannot_place_order_when_store_is_closed_and_orders_not_accepted(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'is_active' => true,
            'base_price' => 50.00,
            'price_after_discount' => null,
            'sell_by_weight' => false,
        ]);
        $this->cartService->addItem($user, $product->id, null, '1');

        $currentDay = now()->format('l');
        $this->settingService->set(
            SettingKey::WORK_TIMES,
            json_encode([
                [
                    'day' => $currentDay,
                    'closed' => true,
                ],
            ])
        );
        $this->settingService->set(SettingKey::ACCEPT_ORDERS_AFTER_WORK_TIMES, 'false');

        $data = new PlaceOrderData(
            user: $user,
            branchId: $this->branch->id,
            paymentMethod: PaymentMethod::COD
        );

        $result = $this->placeOrderService->placeOrder($data);

        $this->assertFalse($result->isSuccessful());
        $this->assertStringContainsString('We are currently closed', (string) $result->errorMessage);
    }

    public function test_can_place_order_when_store_closed_if_accept_after_hours_is_true(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'is_active' => true,
            'base_price' => 50.00,
            'price_after_discount' => null,
            'sell_by_weight' => false,
        ]);
        $this->cartService->addItem($user, $product->id, null, '1');

        $currentDay = now()->format('l');
        $this->settingService->set(
            SettingKey::WORK_TIMES,
            json_encode([
                [
                    'day' => $currentDay,
                    'closed' => true,
                ],
            ])
        );
        $this->settingService->set(SettingKey::ACCEPT_ORDERS_AFTER_WORK_TIMES, 'true');

        $data = new PlaceOrderData(
            user: $user,
            branchId: $this->branch->id,
            paymentMethod: PaymentMethod::COD,
            type: 'web_takeaway'
        );

        $result = $this->placeOrderService->placeOrder($data);

        $this->assertTrue($result->isSuccessful());
        $this->assertNotNull($result->order);
    }

    public function test_fails_with_invalid_coupon_id(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'is_active' => true,
            'base_price' => 50.00,
            'price_after_discount' => null,
            'sell_by_weight' => false,
        ]);
        $this->cartService->addItem($user, $product->id, null, '1');

        $data = new PlaceOrderData(
            user: $user,
            branchId: $this->branch->id,
            paymentMethod: PaymentMethod::COD,
            couponId: 999999
        );

        $result = $this->placeOrderService->placeOrder($data);

        $this->assertFalse($result->isSuccessful());
        $this->assertSame('Invalid coupon', $result->errorMessage);
    }

    public function test_fails_when_coupon_is_inactive(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'is_active' => true,
            'base_price' => 50.00,
            'price_after_discount' => null,
            'sell_by_weight' => false,
        ]);
        $this->cartService->addItem($user, $product->id, null, '1');

        $coupon = Coupon::factory()->create([
            'is_active' => false,
        ]);

        $data = new PlaceOrderData(
            user: $user,
            branchId: $this->branch->id,
            paymentMethod: PaymentMethod::COD,
            couponId: $coupon->id
        );

        $result = $this->placeOrderService->placeOrder($data);

        $this->assertFalse($result->isSuccessful());
        $this->assertStringContainsString('not active', (string) $result->errorMessage);
    }

    public function test_places_cod_order_for_registered_user_with_calculations_and_items(): void
    {
        Config::set('app.tax_rate', 0.14);
        Config::set('app.service_rate', 0.05);
        $this->settingService->set(SettingKey::COD_FEE, 10.00);

        $governorate = Governorate::factory()->create();
        $area = Area::factory()->create([
            'governorate_id' => $governorate->id,
            'shipping_cost' => 20.00,
        ]);
        $user = User::factory()->create();
        $address = Address::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
        ]);

        $extraOption = ExtraOption::factory()->create(['name' => 'Sauces']);
        $extraItem = ExtraOptionItem::factory()->create([
            'extra_option_id' => $extraOption->id,
            'name' => 'Cheese',
            'price' => 15.00,
        ]);

        $product = Product::factory()->create([
            'name' => 'Burger',
            'base_price' => 100.00,
            'price_after_discount' => null,
            'sell_by_weight' => false,
            'extra_option_id' => $extraOption->id,
            'is_active' => true,
        ]);

        $this->cartService->addItem(
            user: $user,
            productId: $product->id,
            variantId: null,
            quantity: '2',
            extras: [$extraItem->id]
        );

        $data = new PlaceOrderData(
            user: $user,
            branchId: $this->branch->id,
            paymentMethod: PaymentMethod::COD,
            addressId: $address->id,
            note: 'Deliver quickly',
            type: 'web_delivery'
        );

        $result = $this->placeOrderService->placeOrder($data);

        $this->assertTrue($result->isSuccessful());
        $this->assertFalse($result->requiresPayment());

        /** @var Order $order */
        $order = $result->order;
        $this->assertInstanceOf(Order::class, $order);
        $this->assertSame($user->id, $order->user_id);
        $this->assertNull($order->guest_user_id);
        $this->assertSame($this->branch->id, $order->branch_id);
        $this->assertSame($address->id, $order->address_id);
        $this->assertSame('Deliver quickly', $order->note);
        $this->assertSame('web_delivery', $order->type);
        $this->assertSame(PaymentStatus::PENDING, $order->payment_status);
        $this->assertSame(OrderPosStatus::READY, $order->pos_status);
        $this->assertStringStartsWith('ORD-', $order->order_number);

        $this->assertEquals(230.00, (float) $order->sub_total);
        $this->assertEquals(0.00, (float) $order->discount);
        $this->assertEquals(32.20, (float) $order->tax);
        $this->assertEquals(21.50, (float) $order->service);
        $this->assertEquals(20.00, (float) $order->delivery_fee);
        $this->assertEquals(303.70, (float) $order->total);

        // Items check
        $this->assertCount(1, $order->items);
        $firstItem = $order->items->first();
        $this->assertSame('Burger', $firstItem->product_name);
        $this->assertSame(2, (int) $firstItem->quantity);
        $this->assertEquals(100.00, (float) $firstItem->unit_price);
        $this->assertEquals(230.00, (float) $firstItem->total);

        // Extras check
        $this->assertCount(1, $firstItem->extras);
        $firstExtra = $firstItem->extras->first();
        $this->assertSame('Cheese', $firstExtra->extra_name);
        $this->assertEquals(15.00, (float) $firstExtra->extra_price);

        // Cart is cleared
        $cart = $this->cartService->getCart($user);
        $this->assertEmpty($cart['items']);
    }

    public function test_places_order_for_guest_user(): void
    {
        $governorate = Governorate::factory()->create();
        $area = Area::factory()->create([
            'governorate_id' => $governorate->id,
            'shipping_cost' => 25.00,
        ]);

        $guest = GuestUser::factory()->create([
            'name' => 'Guest Customer',
            'phone' => '01012345678',
            'area_id' => $area->id,
        ]);

        $product = Product::factory()->create([
            'base_price' => 80.00,
            'price_after_discount' => null,
            'sell_by_weight' => false,
            'is_active' => true,
        ]);

        $this->cartService->addItem(
            user: null,
            productId: $product->id,
            variantId: null,
            quantity: '1'
        );

        $data = new PlaceOrderData(
            user: $guest,
            branchId: $this->branch->id,
            paymentMethod: PaymentMethod::COD,
            type: 'web_delivery'
        );

        $result = $this->placeOrderService->placeOrder($data);

        $this->assertTrue($result->isSuccessful());
        $order = $result->order;
        $this->assertSame($guest->id, $order->guest_user_id);
        $this->assertNull($order->user_id);
        $this->assertEquals(25.00, (float) $order->delivery_fee);
    }

    public function test_places_takeaway_order_with_zero_delivery_fee(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'base_price' => 50.00,
            'price_after_discount' => null,
            'sell_by_weight' => false,
            'is_active' => true,
        ]);
        $this->cartService->addItem($user, $product->id, null, '1');

        $data = new PlaceOrderData(
            user: $user,
            branchId: $this->branch->id,
            paymentMethod: PaymentMethod::COD,
            type: 'web_takeaway'
        );

        $result = $this->placeOrderService->placeOrder($data);

        $this->assertTrue($result->isSuccessful());
        $this->assertEquals(0.00, (float) $result->order->delivery_fee);
    }

    public function test_places_order_with_valid_coupon_and_tracks_usage(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'base_price' => 100.00,
            'price_after_discount' => null,
            'sell_by_weight' => false,
            'is_active' => true,
        ]);
        $this->cartService->addItem($user, $product->id, null, '1');

        $coupon = Coupon::factory()->create([
            'code' => 'SAVE10',
            'type' => 'fixed',
            'value' => 10.00,
            'is_active' => true,
            'usage_count' => 0,
        ]);

        $data = new PlaceOrderData(
            user: $user,
            branchId: $this->branch->id,
            paymentMethod: PaymentMethod::COD,
            couponId: $coupon->id,
            type: 'web_takeaway'
        );

        $result = $this->placeOrderService->placeOrder($data);

        $this->assertTrue($result->isSuccessful());
        $this->assertEquals(10.00, (float) $result->order->discount);
        $this->assertSame(1, $coupon->fresh()->usage_count);
    }

    public function test_places_card_order_with_payment_gateway_success(): void
    {
        $user = User::factory()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);
        $product = Product::factory()->create([
            'base_price' => 100.00,
            'price_after_discount' => null,
            'sell_by_weight' => false,
            'is_active' => true,
        ]);
        $this->cartService->addItem($user, $product->id, null, '1');

        $mockGateway = Mockery::mock(PaymentGatewayInterface::class);
        $mockGateway->shouldReceive('getGatewayId')->andReturn('paymob');
        $mockGateway->shouldReceive('createPaymentIntention')
            ->once()
            ->andReturn([
                'success' => true,
                'checkout_url' => 'https://accept.paymob.com/checkout/12345',
                'data' => ['id' => 12345],
            ]);

        $this->app->instance(PaymentGatewayInterface::class, $mockGateway);
        $service = $this->app->make(PlaceOrderServiceInterface::class);

        $data = new PlaceOrderData(
            user: $user,
            branchId: $this->branch->id,
            paymentMethod: PaymentMethod::CARD,
            type: 'web_takeaway'
        );

        $result = $service->placeOrder($data);

        $this->assertTrue($result->isSuccessful());
        $this->assertTrue($result->requiresPayment());
        $this->assertSame('https://accept.paymob.com/checkout/12345', $result->checkoutUrl);
        $this->assertSame(['id' => 12345], $result->paymentData);
    }

    public function test_places_card_order_with_gateway_failure_cancels_order(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'base_price' => 100.00,
            'price_after_discount' => null,
            'sell_by_weight' => false,
            'is_active' => true,
        ]);
        $this->cartService->addItem($user, $product->id, null, '1');

        $mockGateway = Mockery::mock(PaymentGatewayInterface::class);
        $mockGateway->shouldReceive('getGatewayId')->andReturn('paymob');
        $mockGateway->shouldReceive('createPaymentIntention')
            ->once()
            ->andReturn([
                'success' => false,
                'error' => 'Gateway error',
            ]);

        $this->app->instance(PaymentGatewayInterface::class, $mockGateway);
        $service = $this->app->make(PlaceOrderServiceInterface::class);

        $data = new PlaceOrderData(
            user: $user,
            branchId: $this->branch->id,
            paymentMethod: PaymentMethod::CARD,
            type: 'web_takeaway'
        );

        $result = $service->placeOrder($data);

        $this->assertFalse($result->isSuccessful());
        $this->assertSame('Gateway error', $result->errorMessage);

        $order = $result->order;
        $this->assertSame('cancelled', $order->status->value ?? $order->status);
        $this->assertSame(PaymentStatus::FAILED, $order->payment_status);
    }

    public function test_handles_kashier_successful_payment_callback(): void
    {
        $order = Order::factory()->create([
            'payment_status' => PaymentStatus::PENDING,
        ]);

        $callbackData = [
            'data' => [
                'paymentStatus' => 'SUCCESS',
                'transactionId' => 'KASH-123456',
                'amount' => 100,
                'currency' => 'EGP',
                'cardBrand' => 'Visa',
            ],
        ];

        $result = $this->callbackService->handleCallback($order->id, $callbackData);

        $this->assertTrue($result['success']);
        $this->assertSame('Payment successful', $result['message']);

        $freshOrder = $order->fresh();
        $this->assertSame('completed', $freshOrder->payment_status->value ?? $freshOrder->payment_status);
        $this->assertSame('KASH-123456', $freshOrder->transaction_id);
        $this->assertSame(OrderPosStatus::READY, $freshOrder->pos_status);
    }

    public function test_handles_paymob_successful_payment_callback(): void
    {
        $order = Order::factory()->create([
            'payment_status' => PaymentStatus::PENDING,
        ]);

        $callbackData = [
            'data' => [
                'success' => 'true',
                'pending' => 'false',
                'id' => 'PM-999888',
                'amount_cents' => 10000,
                'currency' => 'EGP',
            ],
        ];

        $result = $this->callbackService->handleCallback($order->id, $callbackData);

        $this->assertTrue($result['success']);
        $this->assertSame('Payment successful', $result['message']);

        $freshOrder = $order->fresh();
        $this->assertSame('completed', $freshOrder->payment_status->value ?? $freshOrder->payment_status);
        $this->assertSame('PM-999888', $freshOrder->transaction_id);
        $this->assertSame(OrderPosStatus::READY, $freshOrder->pos_status);
    }

    public function test_handles_webhook_with_valid_hmac(): void
    {
        $order = Order::factory()->create([
            'merchant_order_id' => 'MERCH-777',
            'payment_status' => PaymentStatus::PENDING,
        ]);

        $mockGateway = Mockery::mock(PaymentGatewayInterface::class);
        $mockGateway->shouldReceive('validateHmac')->andReturn(true);
        $mockGateway->shouldReceive('processWebhook')->andReturn([
            'merchant_order_id' => 'MERCH-777',
            'transaction_id' => 'TXN-999',
            'payment_status' => 'completed',
            'payment_data' => ['source' => 'card'],
        ]);

        $this->app->instance(PaymentGatewayInterface::class, $mockGateway);
        $webhookService = $this->app->make(PaymentWebhookServiceInterface::class);

        $webhookData = ['obj' => ['id' => 123]];
        $result = $webhookService->handleWebhook($webhookData, 'valid_hmac_signature');

        $this->assertTrue($result['success']);
        $this->assertSame('TXN-999', $order->fresh()->transaction_id);
        $this->assertSame('completed', $order->fresh()->payment_status->value ?? $order->fresh()->payment_status);
        $this->assertSame(OrderPosStatus::READY, $order->fresh()->pos_status);
    }
}
