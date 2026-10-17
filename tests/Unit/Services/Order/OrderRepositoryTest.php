<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Order;

use App\Plugins\OrderManagement\src\DTOs\OrderTotals;
use App\Plugins\OrderManagement\src\DTOs\PlaceOrderData;
use App\Plugins\PosIntegration\src\Enums\OrderPosStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Models\User;
use App\Plugins\CartEngine\src\Services\CartService;
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

        $this->repository = app(OrderRepository::class);
        $this->cartService = app(CartService::class);
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

        $cart = $this->cartService->getCart($user);

        $totals = new OrderTotals(
            subTotal: 80.00,
            discount: 0.00,
            tax: 11.20,
            service: 4.00,
            deliveryFee: 0.00,
            total: 95.20
        );

        $order = $this->repository->createOrderWithItems($data, $cart['items'], $totals, null);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertSame($user->id, $order->user_id);
        $this->assertSame($this->branch->id, $order->branch_id);
        $this->assertSame('Extra napkins', $order->note);
        $this->assertEquals(95.20, (float) $order->total);

        $this->assertCount(1, $order->items);
        $this->assertSame('Pizza', $order->items->first()->product_name);

        // Cart is cleared
        $freshCart = $this->cartService->getCart($user);
        $this->assertEmpty($freshCart['items']);
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
        $this->assertSame(OrderPosStatus::READY, $finalized->pos_status);
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
}
