<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Payment;

use App\Plugins\OrderManagement\src\Enums\OrderPosStatus;
use App\Plugins\OrderManagement\src\Enums\OrderStatus;
use App\Plugins\OrderManagement\src\Enums\PaymentStatus;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\PaymentProcessing\src\Contracts\PaymentCallbackServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PaymentCallbackServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentCallbackServiceInterface $callbackService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->callbackService = app(PaymentCallbackServiceInterface::class);
    }

    public function test_maps_completed_order_to_success_without_modifying_database(): void
    {
        $order = Order::factory()->create([
            'payment_status' => PaymentStatus::COMPLETED,
            'status' => OrderStatus::PENDING,
        ]);

        $status = $this->callbackService->handleCallback($order->id);

        $this->assertSame('success', $status);

        $fresh = $order->fresh();
        $this->assertSame(PaymentStatus::COMPLETED, $fresh->payment_status);
        $this->assertSame(OrderStatus::PENDING, $fresh->status);
        $this->assertSame(OrderPosStatus::NOT_READY, $fresh->pos_status);
    }

    public function test_maps_failed_order_to_failed_without_modifying_database(): void
    {
        $order = Order::factory()->create([
            'payment_status' => PaymentStatus::FAILED,
            'status' => OrderStatus::PENDING,
        ]);

        $status = $this->callbackService->handleCallback($order->id);

        $this->assertSame('failed', $status);

        $fresh = $order->fresh();
        $this->assertSame(PaymentStatus::FAILED, $fresh->payment_status);
        $this->assertSame(OrderStatus::PENDING, $fresh->status);
    }

    public function test_maps_pending_order_to_pending_without_modifying_database(): void
    {
        $order = Order::factory()->create([
            'payment_status' => PaymentStatus::PENDING,
            'status' => OrderStatus::PENDING,
        ]);

        $status = $this->callbackService->handleCallback($order->id);

        $this->assertSame('pending', $status);

        $fresh = $order->fresh();
        $this->assertSame(PaymentStatus::PENDING, $fresh->payment_status);
        $this->assertSame(OrderStatus::PENDING, $fresh->status);
        $this->assertSame(OrderPosStatus::NOT_READY, $fresh->pos_status);
    }

    public function test_maps_processing_order_to_pending_without_modifying_database(): void
    {
        $order = Order::factory()->create([
            'payment_status' => PaymentStatus::PROCESSING,
            'status' => OrderStatus::PENDING,
        ]);

        $status = $this->callbackService->handleCallback($order->id);

        $this->assertSame('pending', $status);

        $fresh = $order->fresh();
        $this->assertSame(PaymentStatus::PROCESSING, $fresh->payment_status);
        $this->assertSame(OrderStatus::PENDING, $fresh->status);
    }

    public function test_callback_never_cancels_order(): void
    {
        $order = Order::factory()->create([
            'payment_status' => PaymentStatus::PENDING,
            'status' => OrderStatus::PENDING,
        ]);

        $status = $this->callbackService->handleCallback($order->id);

        $this->assertSame('pending', $status);

        $fresh = $order->fresh();
        $this->assertSame(PaymentStatus::PENDING, $fresh->payment_status);
        $this->assertSame(OrderStatus::PENDING, $fresh->status);
    }
}
