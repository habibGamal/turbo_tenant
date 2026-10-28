<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Payment;

use App\Enums\PaymentStatus;
use App\Plugins\OrderManagement\src\Contracts\PaymentGatewayInterface;
use App\Plugins\OrderManagement\src\Enums\OrderPosStatus;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\PaymentProcessing\src\Services\PaymentStateMachine;
use App\Plugins\PaymentProcessing\src\Services\PaymentWebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

final class PaymentWebhookServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentWebhookService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PaymentWebhookService(new PaymentStateMachine());
    }

    public function test_webhook_returns_error_on_invalid_signature(): void
    {
        $mockGateway = Mockery::mock(PaymentGatewayInterface::class);
        $mockGateway->shouldReceive('getGatewayId')->andReturn('paymob');
        $mockGateway->shouldReceive('validateHmac')->once()->andReturn(false);

        $result = $this->service->handleWebhook(['obj' => ['id' => 1]], 'bad_signature', $mockGateway);

        $this->assertFalse($result['success']);
        $this->assertSame('Invalid webhook signature', $result['error']);
    }

    public function test_webhook_returns_error_when_order_not_found(): void
    {
        $mockGateway = Mockery::mock(PaymentGatewayInterface::class);
        $mockGateway->shouldReceive('getGatewayId')->andReturn('paymob');
        $mockGateway->shouldReceive('validateHmac')->once()->andReturn(true);
        $mockGateway->shouldReceive('processWebhook')->once()->andReturn([
            'merchant_order_id' => 'NON_EXISTENT',
            'transaction_id' => 'TXN-999',
            'payment_status' => 'completed',
            'amount_cents' => 10000,
            'currency' => 'EGP',
        ]);

        $result = $this->service->handleWebhook(['obj' => ['id' => 1]], 'good_signature', $mockGateway);

        $this->assertFalse($result['success']);
        $this->assertSame('Order not found', $result['error']);
    }

    public function test_webhook_updates_order_status_and_pos_status_on_completed(): void
    {
        $order = Order::factory()->create([
            'merchant_order_id' => 'MERCH-ORDER-123',
            'payment_status' => PaymentStatus::PENDING,
            'total' => 100.00,
        ]);

        $mockGateway = Mockery::mock(PaymentGatewayInterface::class);
        $mockGateway->shouldReceive('getGatewayId')->andReturn('paymob');
        $mockGateway->shouldReceive('getCurrency')->andReturn('EGP');
        $mockGateway->shouldReceive('validateHmac')->once()->andReturn(true);
        $mockGateway->shouldReceive('processWebhook')->once()->andReturn([
            'merchant_order_id' => 'MERCH-ORDER-123',
            'transaction_id' => 'TXN-ABC',
            'payment_status' => 'completed',
            'amount_cents' => 10000,
            'currency' => 'EGP',
            'payment_data' => json_encode(['foo' => 'bar']),
        ]);

        $result = $this->service->handleWebhook(['obj' => ['id' => 1]], 'good_signature', $mockGateway);

        $this->assertTrue($result['success']);

        $fresh = $order->fresh();
        $this->assertSame(PaymentStatus::COMPLETED, $fresh->payment_status);
        $this->assertSame('TXN-ABC', $fresh->transaction_id);
        $this->assertSame(OrderPosStatus::READY, $fresh->pos_status);

        $this->assertDatabaseHas('payment_events', [
            'gateway' => 'paymob',
            'transaction_id' => 'TXN-ABC',
            'merchant_order_id' => 'MERCH-ORDER-123',
            'result' => 'completed',
        ]);
    }
}
