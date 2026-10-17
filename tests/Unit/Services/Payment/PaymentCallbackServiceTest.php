<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Payment;

use App\Plugins\PosIntegration\src\Enums\OrderPosStatus;
use App\Enums\PaymentStatus;
use App\Plugins\PaymentProcessing\src\Contracts\PaymentGatewayInterface;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\PaymentProcessing\src\Services\Contracts\PaymentCallbackServiceInterface;
use App\Plugins\PaymentProcessing\src\Services\PaymentCallbackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
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

    public function test_handles_kashier_success_callback(): void
    {
        $order = Order::factory()->create(['payment_status' => PaymentStatus::PENDING]);

        $callbackData = [
            'paymentStatus' => 'SUCCESS',
            'transactionId' => 'KASH-SUCCESS-1',
            'amount' => 150,
            'currency' => 'EGP',
        ];

        $result = $this->callbackService->handleCallback($order->id, $callbackData);

        $this->assertTrue($result['success']);
        $this->assertSame('Payment successful', $result['message']);

        $fresh = $order->fresh();
        $this->assertSame('completed', $fresh->payment_status->value ?? $fresh->payment_status);
        $this->assertSame('KASH-SUCCESS-1', $fresh->transaction_id);
        $this->assertSame(OrderPosStatus::READY, $fresh->pos_status);
    }

    public function test_handles_kashier_failure_callback(): void
    {
        $order = Order::factory()->create(['payment_status' => PaymentStatus::PENDING]);

        $callbackData = [
            'paymentStatus' => 'FAILED',
            'transactionId' => 'KASH-FAIL-1',
        ];

        $result = $this->callbackService->handleCallback($order->id, $callbackData);

        $this->assertFalse($result['success']);
        $this->assertSame('Payment failed', $result['error']);

        $fresh = $order->fresh();
        $this->assertSame('failed', $fresh->payment_status->value ?? $fresh->payment_status);
        $this->assertSame('cancelled', $fresh->status->value ?? $fresh->status);
    }

    public function test_handles_paymob_success_callback(): void
    {
        $order = Order::factory()->create(['payment_status' => PaymentStatus::PENDING]);

        $callbackData = [
            'success' => 'true',
            'pending' => 'false',
            'id' => 'PM-SUCCESS-1',
            'amount_cents' => 20000,
            'currency' => 'EGP',
        ];

        $result = $this->callbackService->handleCallback($order->id, $callbackData);

        $this->assertTrue($result['success']);
        $this->assertSame('Payment successful', $result['message']);

        $fresh = $order->fresh();
        $this->assertSame('completed', $fresh->payment_status->value ?? $fresh->payment_status);
        $this->assertSame('PM-SUCCESS-1', $fresh->transaction_id);
        $this->assertSame(OrderPosStatus::READY, $fresh->pos_status);
    }

    public function test_handles_paymob_pending_callback(): void
    {
        $order = Order::factory()->create(['payment_status' => PaymentStatus::PENDING]);

        $callbackData = [
            'success' => 'false',
            'pending' => 'true',
            'id' => 'PM-PENDING-1',
            'amount_cents' => 20000,
            'currency' => 'EGP',
        ];

        $result = $this->callbackService->handleCallback($order->id, $callbackData);

        $this->assertTrue($result['success']);
        $this->assertSame('Payment is being processed', $result['message']);

        $fresh = $order->fresh();
        $this->assertSame('processing', $fresh->payment_status->value ?? $fresh->payment_status);
    }

    public function test_handles_invalid_signature_callback(): void
    {
        $mockGateway = Mockery::mock(PaymentGatewayInterface::class);
        $mockGateway->shouldReceive('validateCallbackHmac')->andReturn(false);

        $service = new PaymentCallbackService($mockGateway);

        $order = Order::factory()->create(['payment_status' => PaymentStatus::PENDING]);

        $callbackData = [
            'success' => 'true',
            'pending' => 'false',
            'id' => 'PM-SIG-FAIL',
            'hmac' => 'invalid_hmac',
        ];

        $result = $service->handleCallback($order->id, $callbackData);

        $this->assertFalse($result['success']);
        $this->assertSame('Invalid payment signature', $result['error']);
    }
}
