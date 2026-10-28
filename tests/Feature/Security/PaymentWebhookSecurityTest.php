<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Core\Plugins\Enums\PluginStatus;
use App\Enums\PaymentStatus;
use App\Models\TenantPlugin;
use App\Plugins\OrderManagement\src\Enums\OrderPosStatus;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\PaymentProcessing\src\DTOs\PaymentProcessingConfig;
use App\Plugins\PaymentProcessing\src\Models\PaymentEvent;
use App\Plugins\PaymentProcessing\src\Services\KashierService;
use App\Plugins\PaymentProcessing\src\Services\PaymentGatewayFactory;
use App\Plugins\PaymentProcessing\src\Services\PaymobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PaymentWebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const string PAYMOB_SECRET = 'paymob_test_secret_123';

    private const string KASHIER_API_KEY = 'kashier_test_api_key_456';

    protected function setUp(): void
    {
        parent::setUp();

        TenantPlugin::firstOrCreate([
            'tenant_id' => (string) self::$testTenant->id,
            'plugin_id' => 'payment-processing',
        ], [
            'status' => PluginStatus::ACTIVE,
        ]);
    }

    public function test_webhook_with_empty_tenant_secret_returns_503_with_no_change(): void
    {
        $this->setConfig(activeGateway: 'paymob', paymobHmac: '', kashierKey: '');

        $order = Order::factory()->create([
            'merchant_order_id' => 'ORD-EMPTY-SECRET',
            'payment_status' => PaymentStatus::PENDING,
            'total' => 100.00,
        ]);

        $payload = $this->buildPaymobPayload($order, 'TXN-EMPTY-1', true);

        $response = $this->postJson('/api/webhooks/paymob?hmac=dummy_hmac', $payload);

        $response->assertStatus(503);
        $this->assertSame(PaymentStatus::PENDING, $order->fresh()->payment_status);

        // Test Kashier empty secret
        $kashierPayload = $this->buildKashierPayload($order, 'TXN-EMPTY-2', 'SUCCESS');
        $kashierResponse = $this->withHeaders([
            'X-Kashier-Signature' => 'dummy_signature',
        ])->postJson('/api/webhooks/kashier', $kashierPayload);

        $kashierResponse->assertStatus(503);
        $this->assertSame(PaymentStatus::PENDING, $order->fresh()->payment_status);
    }

    public function test_kashier_webhook_on_paymob_active_tenant_is_validated_with_kashier_algorithm_and_succeeds(): void
    {
        // Tenant has Paymob as active gateway
        $this->setConfig(activeGateway: 'paymob', paymobHmac: self::PAYMOB_SECRET, kashierKey: self::KASHIER_API_KEY);

        $order = Order::factory()->create([
            'merchant_order_id' => 'ORD-KASH-CROSS',
            'payment_status' => PaymentStatus::PENDING,
            'total' => 150.00,
        ]);

        $payload = $this->buildKashierPayload($order, 'TXN-KASH-100', 'SUCCESS');
        $signature = $this->computeKashierSignature($payload['data'], self::KASHIER_API_KEY);

        $response = $this->withHeaders([
            'X-Kashier-Signature' => $signature,
        ])->postJson('/api/webhooks/kashier', $payload);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $fresh = $order->fresh();
        $this->assertSame(PaymentStatus::COMPLETED, $fresh->payment_status);
        $this->assertSame('TXN-KASH-100', $fresh->transaction_id);
        $this->assertSame(OrderPosStatus::READY, $fresh->pos_status);
    }

    public function test_amount_mismatch_is_rejected(): void
    {
        $this->setConfig();

        $order = Order::factory()->create([
            'merchant_order_id' => 'ORD-AMT-MISMATCH',
            'payment_status' => PaymentStatus::PENDING,
            'total' => 200.00, // Expected 20000 cents
        ]);

        // Send 10000 cents instead of 20000 cents
        $payload = $this->buildPaymobPayload($order, 'TXN-AMT-FAIL', true, ['amount_cents' => 10000]);
        $hmac = $this->computePaymobHmac($payload['obj'], self::PAYMOB_SECRET);

        $response = $this->postJson("/api/webhooks/paymob?hmac={$hmac}", $payload);

        $response->assertStatus(400);
        $response->assertJsonPath('error', 'Amount mismatch');

        $fresh = $order->fresh();
        $this->assertSame(PaymentStatus::PENDING, $fresh->payment_status);
    }

    public function test_currency_mismatch_is_rejected(): void
    {
        $this->setConfig();

        $order = Order::factory()->create([
            'merchant_order_id' => 'ORD-CURR-MISMATCH',
            'payment_status' => PaymentStatus::PENDING,
            'total' => 200.00,
        ]);

        // Send USD currency instead of configured EGP
        $payload = $this->buildPaymobPayload($order, 'TXN-CURR-FAIL', true, ['currency' => 'USD']);
        $hmac = $this->computePaymobHmac($payload['obj'], self::PAYMOB_SECRET);

        $response = $this->postJson("/api/webhooks/paymob?hmac={$hmac}", $payload);

        $response->assertStatus(400);
        $response->assertJsonPath('error', 'Currency mismatch');

        $fresh = $order->fresh();
        $this->assertSame(PaymentStatus::PENDING, $fresh->payment_status);
    }

    public function test_replayed_transaction_id_changes_nothing(): void
    {
        $this->setConfig();

        $order = Order::factory()->create([
            'merchant_order_id' => 'ORD-REPLAY-1',
            'payment_status' => PaymentStatus::PENDING,
            'total' => 100.00,
        ]);

        $payload = $this->buildPaymobPayload($order, 'TXN-REPLAY-99', true);
        $hmac = $this->computePaymobHmac($payload['obj'], self::PAYMOB_SECRET);

        // First attempt succeeds
        $firstResponse = $this->postJson("/api/webhooks/paymob?hmac={$hmac}", $payload);
        $firstResponse->assertOk();

        $fresh = $order->fresh();
        $this->assertSame(PaymentStatus::COMPLETED, $fresh->payment_status);
        $firstUpdatedAt = $fresh->updated_at;

        // Replay attempt with same (gateway, transaction_id)
        $secondResponse = $this->postJson("/api/webhooks/paymob?hmac={$hmac}", $payload);
        $secondResponse->assertOk();

        // Verify state is untouched and count of payment events is 1
        $this->assertSame(1, PaymentEvent::where('gateway', 'paymob')->where('transaction_id', 'TXN-REPLAY-99')->count());
        $this->assertSame(PaymentStatus::COMPLETED, $order->fresh()->payment_status);
    }

    public function test_completed_to_failed_is_ignored(): void
    {
        $this->setConfig();

        $order = Order::factory()->create([
            'merchant_order_id' => 'ORD-SM-TEST',
            'payment_status' => PaymentStatus::COMPLETED,
            'total' => 100.00,
        ]);

        $payload = $this->buildPaymobPayload($order, 'TXN-FAIL-ATTEMPT', false);
        $hmac = $this->computePaymobHmac($payload['obj'], self::PAYMOB_SECRET);

        $response = $this->postJson("/api/webhooks/paymob?hmac={$hmac}", $payload);

        // Gateway still gets 200 OK
        $response->assertOk();

        // But order state remains completed
        $this->assertSame(PaymentStatus::COMPLETED, $order->fresh()->payment_status);
    }

    public function test_webhook_missing_mandatory_kashier_signature_key_is_rejected(): void
    {
        $this->setConfig();

        $order = Order::factory()->create([
            'merchant_order_id' => 'ORD-KASH-MISSING-KEY',
            'payment_status' => PaymentStatus::PENDING,
            'total' => 100.00,
        ]);

        // Missing 'amount' from signatureKeys
        $payload = [
            'event' => 'pay',
            'data' => [
                'merchantOrderId' => 'ORD-KASH-MISSING-KEY',
                'amount' => 100.00,
                'currency' => 'EGP',
                'status' => 'SUCCESS',
                'transactionId' => 'TXN-KASH-BAD-KEYS',
                'signatureKeys' => ['merchantOrderId', 'currency', 'status', 'transactionId'], // amount is missing!
            ],
        ];

        $signature = $this->computeKashierSignature($payload['data'], self::KASHIER_API_KEY);

        $response = $this->withHeaders([
            'X-Kashier-Signature' => $signature,
        ])->postJson('/api/webhooks/kashier', $payload);

        $response->assertStatus(400);
        $response->assertJsonPath('error', 'Invalid webhook signature');

        $this->assertSame(PaymentStatus::PENDING, $order->fresh()->payment_status);
    }

    public function test_paymob_webhook_ignores_hmac_in_body_when_query_string_hmac_is_missing(): void
    {
        $this->setConfig();

        $order = Order::factory()->create([
            'merchant_order_id' => 'ORD-HMAC-BODY',
            'payment_status' => PaymentStatus::PENDING,
            'total' => 100.00,
        ]);

        $payload = $this->buildPaymobPayload($order, 'TXN-BODY-HMAC', true);
        $hmac = $this->computePaymobHmac($payload['obj'], self::PAYMOB_SECRET);

        // Put HMAC in body, not query string
        $payload['hmac'] = $hmac;

        $response = $this->postJson('/api/webhooks/paymob', $payload);

        $response->assertStatus(400);
        $response->assertJsonPath('error', 'Missing HMAC signature');
    }

    public function test_customer_can_retry_payment_after_decline_and_succeed(): void
    {
        $this->setConfig();

        $order = Order::factory()->create([
            'merchant_order_id' => 'ORD-RETRY-SUCCESS',
            'payment_status' => PaymentStatus::PENDING,
            'total' => 100.00,
        ]);

        // 1. Decline webhook (txn A) -> failed
        $payloadA = $this->buildPaymobPayload($order, 'TXN-A-DECLINED', false);
        $hmacA = $this->computePaymobHmac($payloadA['obj'], self::PAYMOB_SECRET);

        $responseA = $this->postJson("/api/webhooks/paymob?hmac={$hmacA}", $payloadA);
        $responseA->assertOk();

        $orderAfterDecline = $order->fresh();
        $this->assertSame(PaymentStatus::FAILED, $orderAfterDecline->payment_status);
        $this->assertNotSame(OrderPosStatus::READY, $orderAfterDecline->pos_status);

        // 2. Success webhook (txn B) -> completed + pos_status READY
        $payloadB = $this->buildPaymobPayload($order, 'TXN-B-SUCCESS', true);
        $hmacB = $this->computePaymobHmac($payloadB['obj'], self::PAYMOB_SECRET);

        $responseB = $this->postJson("/api/webhooks/paymob?hmac={$hmacB}", $payloadB);
        $responseB->assertOk();

        $orderAfterSuccess = $order->fresh();
        $this->assertSame(PaymentStatus::COMPLETED, $orderAfterSuccess->payment_status);
        $this->assertSame('TXN-B-SUCCESS', $orderAfterSuccess->transaction_id);
        $this->assertSame(OrderPosStatus::READY, $orderAfterSuccess->pos_status);

        // 3. Replaying txn A afterwards changes nothing
        $replayResponseA = $this->postJson("/api/webhooks/paymob?hmac={$hmacA}", $payloadA);
        $replayResponseA->assertOk();

        $orderAfterReplay = $order->fresh();
        $this->assertSame(PaymentStatus::COMPLETED, $orderAfterReplay->payment_status);
        $this->assertSame('TXN-B-SUCCESS', $orderAfterReplay->transaction_id);
        $this->assertSame(OrderPosStatus::READY, $orderAfterReplay->pos_status);
    }

    private function setConfig(
        string $activeGateway = 'paymob',
        string $paymobHmac = self::PAYMOB_SECRET,
        string $kashierKey = self::KASHIER_API_KEY
    ): void {
        $config = new PaymentProcessingConfig(
            activeGateway: $activeGateway,
            paymobHmacSecret: $paymobHmac,
            paymobCurrency: 'EGP',
            kashierApiKey: $kashierKey,
            kashierCurrency: 'EGP'
        );

        app()->instance(PaymentProcessingConfig::class, $config);
        app()->forgetInstance(PaymobService::class);
        app()->forgetInstance(KashierService::class);
        app()->forgetInstance(PaymentGatewayFactory::class);
    }

    private function buildPaymobPayload(Order $order, string $txnId, bool $success, array $overrides = []): array
    {
        $amountCents = $overrides['amount_cents'] ?? (int) round(((float) $order->total) * 100);
        $currency = $overrides['currency'] ?? 'EGP';

        $obj = [
            'id' => $txnId,
            'amount_cents' => $amountCents,
            'created_at' => '2026-10-28T12:00:00.000000',
            'currency' => $currency,
            'error_occured' => false,
            'has_parent_transaction' => false,
            'integration_id' => 12345,
            'is_3d_secure' => true,
            'is_auth' => false,
            'is_capture' => false,
            'is_refunded' => false,
            'is_standalone_payment' => true,
            'is_voided' => false,
            'order' => [
                'id' => 99999,
                'merchant_order_id' => $order->merchant_order_id,
            ],
            'owner' => 1,
            'pending' => false,
            'source_data' => [
                'pan' => '2346',
                'sub_type' => 'MasterCard',
                'type' => 'card',
            ],
            'success' => $success,
        ];

        return [
            'type' => 'TRANSACTION',
            'obj' => $obj,
        ];
    }

    private function computePaymobHmac(array $data, string $secret): string
    {
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
            $val = $data;
            foreach ($parts as $p) {
                $val = $val[$p] ?? '';
            }
            if (is_bool($val)) {
                $concatenated .= $val ? 'true' : 'false';
            } else {
                $concatenated .= (string) $val;
            }
        }

        return hash_hmac('sha512', $concatenated, $secret);
    }

    private function buildKashierPayload(Order $order, string $txnId, string $status): array
    {
        return [
            'event' => 'pay',
            'data' => [
                'merchantOrderId' => $order->merchant_order_id,
                'amount' => (float) $order->total,
                'currency' => 'EGP',
                'status' => $status,
                'transactionId' => $txnId,
                'signatureKeys' => ['merchantOrderId', 'amount', 'currency', 'status', 'transactionId'],
            ],
        ];
    }

    private function computeKashierSignature(array $data, string $apiKey): string
    {
        $signatureKeys = $data['signatureKeys'];
        sort($signatureKeys);

        $signatureData = [];
        foreach ($signatureKeys as $key) {
            if (isset($data[$key])) {
                $signatureData[$key] = $data[$key];
            }
        }

        $queryString = http_build_query($signatureData, '', '&', PHP_QUERY_RFC3986);

        return hash_hmac('sha256', $queryString, $apiKey, false);
    }
}
