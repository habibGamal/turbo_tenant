<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Payment;

use App\Enums\OrderPosStatus;
use App\Enums\PaymentStatus;
use App\Interfaces\PaymentGatewayInterface;
use App\Models\Order;
use App\Services\Payment\PaymentWebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

final class PaymentWebhookServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_returns_error_on_invalid_signature(): void
    {
        $mockGateway = Mockery::mock(PaymentGatewayInterface::class);
        $mockGateway->shouldReceive('validateHmac')->once()->andReturn(false);

        $service = new PaymentWebhookService($mockGateway);

        $result = $service->handleWebhook(['obj' => ['id' => 1]], 'bad_signature');

        $this->assertFalse($result['success']);
        $this->assertSame('Invalid webhook signature', $result['error']);
    }

    public function test_webhook_returns_error_when_order_not_found(): void
    {
        $mockGateway = Mockery::mock(PaymentGatewayInterface::class);
        $mockGateway->shouldReceive('validateHmac')->once()->andReturn(true);
        $mockGateway->shouldReceive('processWebhook')->once()->andReturn([
            'merchant_order_id' => 'NON_EXISTENT',
        ]);

        $service = new PaymentWebhookService($mockGateway);

        $result = $service->handleWebhook(['obj' => ['id' => 1]], 'good_signature');

        $this->assertFalse($result['success']);
        $this->assertSame('Order not found', $result['error']);
    }

    public function test_webhook_updates_order_status_and_pos_status_on_completed(): void
    {
        $order = Order::factory()->create([
            'merchant_order_id' => 'MERCH-ORDER-123',
            'payment_status' => PaymentStatus::PENDING,
        ]);

        $mockGateway = Mockery::mock(PaymentGatewayInterface::class);
        $mockGateway->shouldReceive('validateHmac')->once()->andReturn(true);
        $mockGateway->shouldReceive('processWebhook')->once()->andReturn([
            'merchant_order_id' => 'MERCH-ORDER-123',
            'transaction_id' => 'TXN-ABC',
            'payment_status' => 'completed',
            'payment_data' => ['foo' => 'bar'],
        ]);

        $service = new PaymentWebhookService($mockGateway);

        $result = $service->handleWebhook(['obj' => ['id' => 1]], 'good_signature');

        $this->assertTrue($result['success']);

        $fresh = $order->fresh();
        $this->assertSame('completed', $fresh->payment_status->value ?? $fresh->payment_status);
        $this->assertSame('TXN-ABC', $fresh->transaction_id);
        $this->assertSame(OrderPosStatus::READY, $fresh->pos_status);
    }
}
