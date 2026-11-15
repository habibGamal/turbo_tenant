<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Payment;

use App\Plugins\OrderManagement\src\Contracts\PaymentIntention;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\PaymentProcessing\src\DTOs\GatewayWebhookData;
use App\Plugins\PaymentProcessing\src\DTOs\PaymentProcessingConfig;
use App\Plugins\PaymentProcessing\src\DTOs\RefundResult;
use App\Plugins\PaymentProcessing\src\Services\PaymobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class PaymobServiceTest extends TestCase
{
    use RefreshDatabase;

    private const string HMAC_SECRET = 'test_paymob_hmac_secret';

    private PaymobService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $config = new PaymentProcessingConfig(
            paymobSecretKey: 'test_secret_key',
            paymobPublicKey: 'test_public_key',
            paymobBaseUrl: 'https://accept.paymob.com',
            paymobIntegrationIds: [12345],
            paymobHmacSecret: self::HMAC_SECRET,
            paymobCurrency: 'EGP',
        );

        $this->service = new PaymobService($config);
    }

    public function test_create_payment_intention_returns_payment_intention_on_success(): void
    {
        $order = Order::factory()->create([
            'total' => 150.00,
        ]);

        Http::fake([
            'https://accept.paymob.com/v1/intention/' => Http::response([
                'id' => 'intent_999',
                'client_secret' => 'cs_secret_123',
            ], 200),
        ]);

        $result = $this->service->createPaymentIntention(
            $order,
            ['email' => 'buyer@example.com'],
            'https://store.test/callback',
            'https://store.test/webhook'
        );

        $this->assertInstanceOf(PaymentIntention::class, $result);
        $this->assertTrue($result->successful);
        $this->assertNotNull($result->checkoutUrl);
        $this->assertStringContainsString('cs_secret_123', $result->checkoutUrl);
        $this->assertSame('intent_999', $result->data['id'] ?? null);
        $this->assertNull($result->error);
    }

    public function test_create_payment_intention_returns_payment_intention_on_failure(): void
    {
        $order = Order::factory()->create([
            'total' => 150.00,
        ]);

        Http::fake([
            'https://accept.paymob.com/v1/intention/' => Http::response([
                'detail' => 'Invalid integration ID',
            ], 400),
        ]);

        $result = $this->service->createPaymentIntention(
            $order,
            ['email' => 'buyer@example.com'],
            'https://store.test/callback',
            'https://store.test/webhook'
        );

        $this->assertInstanceOf(PaymentIntention::class, $result);
        $this->assertFalse($result->successful);
        $this->assertSame('Invalid integration ID', $result->error);
        $this->assertNull($result->checkoutUrl);
    }

    public function test_refund_transaction_returns_refund_result_on_success(): void
    {
        Http::fake([
            'https://accept.paymob.com/api/acceptance/void_refund/refund' => Http::response([
                'id' => 'ref_555',
                'success' => true,
            ], 200),
        ]);

        $result = $this->service->refundTransaction('12345', 50.00);

        $this->assertInstanceOf(RefundResult::class, $result);
        $this->assertTrue($result->successful);
        $this->assertSame('ref_555', $result->data['id'] ?? null);
        $this->assertNull($result->error);
    }

    public function test_refund_transaction_returns_refund_result_on_failure(): void
    {
        Http::fake([
            'https://accept.paymob.com/api/acceptance/void_refund/refund' => Http::response([
                'detail' => 'Transaction not found',
            ], 400),
        ]);

        $result = $this->service->refundTransaction('12345', 50.00);

        $this->assertInstanceOf(RefundResult::class, $result);
        $this->assertFalse($result->successful);
        $this->assertSame('Transaction not found', $result->error);
    }

    public function test_process_webhook_returns_gateway_webhook_data_and_unwraps_nested_obj(): void
    {
        $rawPayload = [
            'type' => 'TRANSACTION',
            'obj' => [
                'id' => 98765,
                'success' => true,
                'pending' => false,
                'amount_cents' => 15000,
                'currency' => 'EGP',
                'order' => [
                    'merchant_order_id' => 'ORD-PAYMOB-1',
                ],
                'source_data' => [
                    'type' => 'card',
                ],
            ],
        ];

        $result = $this->service->processWebhook($rawPayload);

        $this->assertInstanceOf(GatewayWebhookData::class, $result);
        $this->assertSame('98765', $result->transactionId);
        $this->assertSame('ORD-PAYMOB-1', $result->merchantOrderId);
        $this->assertSame(15000, $result->amountCents);
        $this->assertSame('EGP', $result->currency);
        $this->assertTrue($result->successful);
        $this->assertSame('completed', $result->paymentStatus);
    }

    public function test_validate_hmac_unwraps_nested_obj_payload_and_validates_signature(): void
    {
        $transaction = [
            'amount_cents' => 10000,
            'created_at' => '2026-10-30T10:00:00.000000',
            'currency' => 'EGP',
            'error_occured' => false,
            'has_parent_transaction' => false,
            'id' => 112233,
            'integration_id' => 12345,
            'is_3d_secure' => true,
            'is_auth' => false,
            'is_capture' => false,
            'is_refunded' => false,
            'is_standalone_payment' => true,
            'is_voided' => false,
            'order' => [
                'id' => 8888,
            ],
            'owner' => 1,
            'pending' => false,
            'source_data' => [
                'pan' => '1234',
                'sub_type' => 'Visa',
                'type' => 'card',
            ],
            'success' => true,
        ];

        // Compute expected HMAC from transaction object
        $keys = [
            'amount_cents',
            'created_at',
            'currency',
            'error_occured',
            'has_parent_transaction',
            'id',
            'integration_id',
            'is_3d_secure',
            'is_auth',
            'is_capture',
            'is_refunded',
            'is_standalone_payment',
            'is_voided',
            'order.id',
            'owner',
            'pending',
            'source_data.pan',
            'source_data.sub_type',
            'source_data.type',
            'success',
        ];

        $concatenated = '';
        foreach ($keys as $key) {
            $parts = explode('.', $key);
            $val = $transaction;
            foreach ($parts as $p) {
                $val = $val[$p] ?? '';
            }
            if (is_bool($val)) {
                $concatenated .= $val ? 'true' : 'false';
            } else {
                $concatenated .= (string) $val;
            }
        }

        $validHmac = hash_hmac('sha512', $concatenated, self::HMAC_SECRET);

        // Nested payload as sent by Paymob
        $wrappedPayload = [
            'type' => 'TRANSACTION',
            'obj' => $transaction,
        ];

        $this->assertTrue($this->service->validateHmac($wrappedPayload, $validHmac));
        $this->assertFalse($this->service->validateHmac($wrappedPayload, 'invalid_hmac'));
    }
}
