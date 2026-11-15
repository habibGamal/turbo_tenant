<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Payment;

use App\Plugins\OrderManagement\src\Enums\OrderPosStatus;
use App\Plugins\OrderManagement\src\Enums\PaymentStatus;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\PaymentProcessing\src\Contracts\PaymentGatewayInterface;
use App\Plugins\PaymentProcessing\src\DTOs\GatewayWebhookData;
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
        $this->service = new PaymentWebhookService(app(PaymentStateMachine::class));
    }

    public function test_webhook_returns_error_on_invalid_signature(): void
    {
        $mockGateway = Mockery::mock(PaymentGatewayInterface::class);
        $mockGateway->shouldReceive('getGatewayId')->andReturn('paymob');
        $mockGateway->shouldReceive('validateHmac')->once()->andReturn(false);

        $result = $this->service->handleWebhook(['obj' => ['id' => 1]], 'bad_signature', $mockGateway);

        $this->assertFalse($result->successful);
        $this->assertSame('Invalid webhook signature', $result->error);
    }

    public function test_webhook_returns_error_when_order_not_found(): void
    {
        $mockGateway = Mockery::mock(PaymentGatewayInterface::class);
        $mockGateway->shouldReceive('getGatewayId')->andReturn('paymob');
        $mockGateway->shouldReceive('validateHmac')->once()->andReturn(true);
        $mockGateway->shouldReceive('processWebhook')->once()->andReturn(new GatewayWebhookData(
            transactionId: 'TXN-999',
            merchantOrderId: 'NON_EXISTENT',
            amountCents: 10000,
            currency: 'EGP',
            successful: true,
            paymentStatus: 'completed',
        ));

        $result = $this->service->handleWebhook(['obj' => ['id' => 1]], 'good_signature', $mockGateway);

        $this->assertFalse($result->successful);
        $this->assertSame('Order not found', $result->error);
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
        $mockGateway->shouldReceive('processWebhook')->once()->andReturn(new GatewayWebhookData(
            transactionId: 'TXN-ABC',
            merchantOrderId: 'MERCH-ORDER-123',
            amountCents: 10000,
            currency: 'EGP',
            successful: true,
            paymentStatus: 'completed',
            paymentData: json_encode(['foo' => 'bar']),
        ));

        $result = $this->service->handleWebhook(['obj' => ['id' => 1]], 'good_signature', $mockGateway);

        $this->assertTrue($result->successful);
        $this->assertSame($order->id, $result->order?->id);

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

    public function test_webhook_service_is_gateway_agnostic_and_does_not_contain_paymob_obj(): void
    {
        $fileContents = file_get_contents(app_path('Plugins/PaymentProcessing/src/Services/PaymentWebhookService.php'));

        $this->assertStringNotContainsString("'obj'", $fileContents);
        $this->assertStringNotContainsString('"obj"', $fileContents);
    }

    public function test_webhook_service_passes_raw_payload_to_gateway_validate_hmac(): void
    {
        $rawPayload = [
            'custom_key' => 'custom_val',
            'nested' => ['data' => 123],
        ];

        $mockGateway = Mockery::mock(PaymentGatewayInterface::class);
        $mockGateway->shouldReceive('getGatewayId')->andReturn('generic');
        $mockGateway->shouldReceive('validateHmac')
            ->once()
            ->with($rawPayload, 'sig_xyz')
            ->andReturn(false);

        $result = $this->service->handleWebhook($rawPayload, 'sig_xyz', $mockGateway);

        $this->assertFalse($result->successful);
        $this->assertSame('Invalid webhook signature', $result->error);
    }
}
