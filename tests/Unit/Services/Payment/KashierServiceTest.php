<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Payment;

use App\Plugins\OrderManagement\src\Contracts\PaymentIntention;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\PaymentProcessing\src\DTOs\GatewayWebhookData;
use App\Plugins\PaymentProcessing\src\DTOs\PaymentProcessingConfig;
use App\Plugins\PaymentProcessing\src\DTOs\RefundResult;
use App\Plugins\PaymentProcessing\src\Services\KashierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class KashierServiceTest extends TestCase
{
    use RefreshDatabase;

    private const string API_KEY = 'test_kashier_api_key_123';

    private const string SECRET_KEY = 'test_kashier_secret_key_456';

    private KashierService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $config = new PaymentProcessingConfig(
            kashierMerchantId: 'MID-12345',
            kashierApiKey: self::API_KEY,
            kashierSecretKey: self::SECRET_KEY,
            kashierMode: 'test',
            kashierCurrency: 'EGP',
            kashierAllowedMethods: 'card'
        );

        $this->service = new KashierService($config);
    }

    public function test_create_payment_intention_returns_payment_intention_on_success(): void
    {
        $order = Order::factory()->create([
            'total' => 200.00,
        ]);

        $result = $this->service->createPaymentIntention(
            $order,
            [],
            'https://store.test/callback',
            'https://store.test/webhook'
        );

        $this->assertInstanceOf(PaymentIntention::class, $result);
        $this->assertTrue($result->successful);
        $this->assertNotNull($result->checkoutUrl);
        $this->assertStringContainsString("/payments/{$order->id}/kashier", $result->checkoutUrl);
        $this->assertSame('MID-12345', $result->data['merchantId'] ?? null);
        $this->assertSame('200.00', $result->data['amount'] ?? null);
        $this->assertNull($result->error);
    }

    public function test_refund_transaction_returns_refund_result_on_success(): void
    {
        Http::fake([
            'https://test-fep.kashier.io/v3/orders/TXN-123/' => Http::response([
                'status' => 'SUCCESS',
                'response' => [
                    'status' => 'REFUNDED',
                    'transactionId' => 'REF-TXN-789',
                ],
            ], 200),
        ]);

        $result = $this->service->refundTransaction('TXN-123', 75.00);

        $this->assertInstanceOf(RefundResult::class, $result);
        $this->assertTrue($result->successful);
        $this->assertSame('SUCCESS', $result->data['status'] ?? null);
        $this->assertNull($result->error);
    }

    public function test_refund_transaction_returns_refund_result_on_failure(): void
    {
        Http::fake([
            'https://test-fep.kashier.io/v3/orders/TXN-123/' => Http::response([
                'status' => 'FAILED',
                'message' => 'Insufficient balance for refund',
            ], 400),
        ]);

        $result = $this->service->refundTransaction('TXN-123', 75.00);

        $this->assertInstanceOf(RefundResult::class, $result);
        $this->assertFalse($result->successful);
        $this->assertSame('Insufficient balance for refund', $result->error);
    }

    public function test_process_webhook_returns_gateway_webhook_data(): void
    {
        $payload = [
            'event' => 'pay',
            'data' => [
                'transactionId' => 'KASH-TXN-555',
                'merchantOrderId' => 'ORD-KASH-1',
                'status' => 'SUCCESS',
                'amount' => 250.50,
                'currency' => 'EGP',
            ],
        ];

        $result = $this->service->processWebhook($payload);

        $this->assertInstanceOf(GatewayWebhookData::class, $result);
        $this->assertSame('KASH-TXN-555', $result->transactionId);
        $this->assertSame('ORD-KASH-1', $result->merchantOrderId);
        $this->assertSame(25050, $result->amountCents);
        $this->assertSame('EGP', $result->currency);
        $this->assertTrue($result->successful);
        $this->assertSame('completed', $result->paymentStatus);
    }

    public function test_validate_hmac_validates_signature(): void
    {
        $data = [
            'merchantOrderId' => 'ORD-123',
            'amount' => 100.0,
            'currency' => 'EGP',
            'status' => 'SUCCESS',
            'transactionId' => 'TXN-999',
            'signatureKeys' => ['amount', 'currency', 'merchantOrderId', 'status', 'transactionId'],
        ];

        $signatureKeys = $data['signatureKeys'];
        sort($signatureKeys);

        $signatureData = [];
        foreach ($signatureKeys as $key) {
            $signatureData[$key] = $data[$key];
        }

        $queryString = http_build_query($signatureData, '', '&', PHP_QUERY_RFC3986);
        $validSignature = hash_hmac('sha256', $queryString, self::API_KEY, false);

        $this->assertTrue($this->service->validateHmac($data, $validSignature));
        $this->assertFalse($this->service->validateHmac($data, 'invalid_signature'));
    }
}
