<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Order;

use App\Models\User;
use App\Plugins\CartEngine\src\Services\CartService;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\GuestCheckout\src\Models\GuestUser;
use App\Plugins\LocationsDelivery\src\Contracts\AddressBook;
use App\Plugins\LocationsDelivery\src\Models\Address;
use App\Plugins\LocationsDelivery\src\Models\Area;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\OrderManagement\src\DTOs\OrderTotals;
use App\Plugins\OrderManagement\src\DTOs\PlaceOrderData;
use App\Plugins\OrderManagement\src\Enums\OrderStatus;
use App\Plugins\OrderManagement\src\Enums\PaymentMethod;
use App\Plugins\OrderManagement\src\Enums\PaymentStatus;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Pipelines\Context\OrderValidationContext;
use App\Plugins\OrderManagement\src\Services\Repositories\OrderRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OrderRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private OrderRepository $repository;

    private CartService $cartService;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cartService = app(CartService::class);
        $this->repository = new OrderRepository(
            $this->cartService,
            app(AddressBook::class)
        );
        $this->branch = Branch::factory()->create();
    }

    public function test_creates_order_and_items_and_clears_cart(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Pizza',
            'base_price' => 80.00,
            'is_active' => true,
        ]);

        $this->cartService->addItem($user, $product->id, null, '1');

        $data = new PlaceOrderData(
            user: $user,
            branchId: $this->branch->id,
            paymentMethod: PaymentMethod::COD,
            note: 'Extra napkins'
        );

        $lines = $this->cartService->getLines($user);

        $totals = new OrderTotals(
            subTotal: 80.00,
            discount: 0.00,
            tax: 11.20,
            service: 4.00,
            deliveryFee: 0.00,
            total: 95.20
        );

        $order = $this->repository->createOrderWithItems($data, $lines, $totals, null);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertSame($user->id, $order->user_id);
        $this->assertSame($this->branch->id, $order->branch_id);
        $this->assertSame('Extra napkins', $order->note);
        $this->assertEquals(95.20, (float) $order->total);

        $this->assertCount(1, $order->items);
        $this->assertSame('Pizza', $order->items->first()->product_name);

        // Cart is cleared
        $this->assertSame([], $this->cartService->getLines($user));
    }

    public function test_finalize_offline_order_sets_statuses(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::PENDING,
            'payment_status' => PaymentStatus::PENDING,
        ]);

        $finalized = $this->repository->finalizeOfflineOrder($order);

        $this->assertSame(OrderStatus::PENDING, $finalized->status);
        $this->assertSame(PaymentStatus::PENDING, $finalized->payment_status);
        // finalizeOfflineOrder must not touch pos_status (handled via OrderFinalized event)
        $this->assertSame('not_ready', $finalized->pos_status->value ?? (string) $finalized->pos_status);
    }

    public function test_mark_order_as_payment_failed_cancels_order(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::PENDING,
            'payment_status' => PaymentStatus::PENDING,
        ]);

        $this->repository->markOrderAsPaymentFailed($order);

        $fresh = $order->fresh();
        $this->assertSame('cancelled', $fresh->status->value ?? $fresh->status);
        $this->assertSame(PaymentStatus::FAILED, $fresh->payment_status);
    }

    public function test_create_order_with_address_uses_injected_address_book_snapshot(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->create([
            'user_id' => $user->id,
            'street' => 'Original St',
            'phone_number' => '01122334455',
        ]);

        $addressBookMock = $this->createMock(AddressBook::class);
        $addressBookMock->expects($this->once())
            ->method('snapshotAddress')
            ->with($address->id)
            ->willReturn([
                'id' => $address->id,
                'street' => 'Sunset Blvd',
                'building' => '101',
                'phone_number' => '01122334455',
                'area_name' => 'Downtown',
                'governorate_name' => 'Capital',
            ]);

        $repo = new OrderRepository(
            $this->cartService,
            $addressBookMock
        );

        $data = new PlaceOrderData(
            user: $user,
            branchId: $this->branch->id,
            paymentMethod: PaymentMethod::COD,
            addressId: $address->id,
            type: 'web_delivery'
        );

        $totals = new OrderTotals(
            subTotal: 50.00,
            discount: 0.00,
            tax: 0.00,
            service: 0.00,
            deliveryFee: 10.00,
            total: 60.00
        );

        $order = $repo->createOrderWithItems($data, [], $totals, null);

        $this->assertSame($address->id, $order->address_id);
        $this->assertIsArray($order->delivery_address);
        $this->assertSame('Sunset Blvd', $order->delivery_address['street']);
        $this->assertSame('Downtown', $order->delivery_address['area_name']);
        $this->assertSame('Capital', $order->delivery_address['governorate_name']);
        $this->assertSame('01122334455', $order->customer_phone);
    }

    public function test_create_order_with_guest_delivery_uses_injected_address_book_labels(): void
    {
        $area = Area::factory()->create();

        $guestUser = GuestUser::factory()->create([
            'name' => 'Guest Jane',
            'phone' => '01099887766',
            'street' => 'Coastal Road',
            'building' => 'Tower A',
            'floor' => '5',
            'apartment' => '502',
            'city' => 'Alexandria',
            'area_id' => $area->id,
        ]);

        $addressBookMock = $this->createMock(AddressBook::class);
        $addressBookMock->expects($this->once())
            ->method('areaLabels')
            ->with($area->id)
            ->willReturn([
                'area_name' => 'North Shore',
                'governorate_name' => 'Alexandria',
            ]);

        $repo = new OrderRepository(
            $this->cartService,
            $addressBookMock
        );

        $data = new PlaceOrderData(
            user: $guestUser,
            branchId: $this->branch->id,
            paymentMethod: PaymentMethod::COD,
            type: 'web_delivery'
        );

        $totals = new OrderTotals(
            subTotal: 100.00,
            discount: 0.00,
            tax: 0.00,
            service: 0.00,
            deliveryFee: 15.00,
            total: 115.00
        );

        $order = $repo->createOrderWithItems($data, [], $totals, null);

        $this->assertSame($guestUser->id, $order->guest_user_id);
        $this->assertNull($order->user_id);
        $this->assertIsArray($order->delivery_address);
        $this->assertSame('Coastal Road', $order->delivery_address['street']);
        $this->assertSame('Tower A', $order->delivery_address['building']);
        $this->assertSame('5', $order->delivery_address['floor']);
        $this->assertSame('502', $order->delivery_address['apartment']);
        $this->assertSame('Alexandria', $order->delivery_address['city']);
        $this->assertSame($area->id, $order->delivery_address['area_id']);
        $this->assertSame('North Shore', $order->delivery_address['area_name']);
        $this->assertSame('Alexandria', $order->delivery_address['governorate_name']);
        $this->assertSame('Guest Jane', $order->customer_name);
        $this->assertSame('01099887766', $order->customer_phone);
    }

    public function test_create_order_fills_coupon_fields_only_from_applied_promotion_in_validation_context(): void
    {
        $user = User::factory()->create();
        $couponId = (int) \Illuminate\Support\Facades\DB::table('coupons')->insertGetId([
            'code' => 'SUMMER50',
            'name' => 'Summer 50',
            'type' => 'fixed',
            'value' => 10,
            'is_active' => true,
            'expiry_date' => now()->addDays(5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $data = new PlaceOrderData(
            user: $user,
            branchId: $this->branch->id,
            paymentMethod: PaymentMethod::COD,
            couponId: 999
        );

        $totals = new OrderTotals(
            subTotal: 50.00,
            discount: 10.00,
            tax: 0.00,
            service: 0.00,
            deliveryFee: 0.00,
            total: 40.00
        );

        $context = new OrderValidationContext($data, []);
        $context->applyPromotion($couponId, 'SUMMER50');

        $order = $this->repository->createOrderWithItems($data, [], $totals, $context);

        $this->assertSame($couponId, $order->coupon_id);
        $this->assertSame('SUMMER50', $order->coupon_code);
    }

    public function test_create_order_stores_null_coupon_fields_when_no_promotion_applied(): void
    {
        $user = User::factory()->create();
        $data = new PlaceOrderData(
            user: $user,
            branchId: $this->branch->id,
            paymentMethod: PaymentMethod::COD,
            couponId: 999
        );

        $totals = new OrderTotals(
            subTotal: 50.00,
            discount: 0.00,
            tax: 0.00,
            service: 0.00,
            deliveryFee: 0.00,
            total: 50.00
        );

        // Even with couponId in data, without an applied promotion on the context it stays null
        $context = new OrderValidationContext($data, []);

        $order = $this->repository->createOrderWithItems($data, [], $totals, $context);

        $this->assertNull($order->coupon_id);
        $this->assertNull($order->coupon_code);
    }
}
