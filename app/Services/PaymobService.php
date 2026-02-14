<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SettingKey;
use App\Interfaces\PaymentGatewayInterface;
use App\Models\Order;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class PaymobService implements PaymentGatewayInterface
{
    private string $baseUrl;

    private string $secretKey;

    private string $publicKey;

    private array $integrationIds;

    private string $hmacSecret;

    public function __construct(
        private readonly SettingService $settingService
    ) {
        $this->baseUrl = $this->settingService->get(SettingKey::PAYMOB_BASE_URL, 'https://accept.paymob.com');
        $this->secretKey = $this->settingService->get(SettingKey::PAYMOB_SECRET_KEY, '');
        $this->publicKey = $this->settingService->get(SettingKey::PAYMOB_PUBLIC_KEY, '');

        // Parse integration IDs from comma-separated string
        $integrationIdsString = $this->settingService->get(SettingKey::PAYMOB_INTEGRATION_IDS, '');
        $this->integrationIds = array_map(
            fn ($id) => (int) $id,
            array_filter(explode(',', $integrationIdsString))
        );

        $this->hmacSecret = $this->settingService->get(SettingKey::PAYMOB_HMAC_SECRET, '');
    }

    /**
     * Get the unique identifier for this gateway.
     */
    public function getGatewayId(): string
    {
        return 'paymob';
    }

    /**
     * Create a payment intention for an order
     */
    public function createPaymentIntention(Order $order, array $billingData, string $redirectionUrl, string $notificationUrl): array
    {
        $merchantOrderId = $this->generateMerchantOrderId($order);
        logger()->info('$this->integrationIds', ['integration_ids' => $this->integrationIds]);
        $payload = [
            'amount' => $this->convertToSmallestUnit($order->total),
            'currency' => $this->settingService->get(SettingKey::PAYMOB_CURRENCY, 'EGP'),
            'payment_methods' => $this->integrationIds,
            // 'payment_methods' => [4874920],
            'items' => [],
            'billing_data' => $this->prepareBillingData($billingData),
            'customer' => [
                'first_name' => $billingData['first_name'] ?? 'NA',
                'last_name' => $billingData['last_name'] ?? 'NA',
                'email' => $billingData['email'] ?? 'customer@example.com',
                'phone_number' => $billingData['phone_number'] ?? '+201000000000',
            ],
            'merchant_order_id' => $merchantOrderId,
            'special_reference' => $merchantOrderId,
            'extras' => [
                'order_id' => (string) $order->id,
            ],
            'redirection_url' => $redirectionUrl,
            'notification_url' => $notificationUrl,
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Token '.$this->secretKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl.'/v1/intention/', $payload);

            if ($response->successful()) {
                $data = $response->json();

                // Update order with merchant_order_id and paymob_order_id
                $order->update([
                    'merchant_order_id' => $merchantOrderId,
                    'paymob_order_id' => $data['id'] ?? null,
                    'payment_status' => 'pending',
                ]);

                Log::info('Paymob payment intention created', [
                    'order_id' => $order->id,
                    'intention_id' => $data['id'] ?? null,
                    'merchant_order_id' => $merchantOrderId,
                ]);

                return [
                    'success' => true,
                    'data' => $data,
                    'checkout_url' => $this->buildCheckoutUrl($data['client_secret']),
                ];
            }

            Log::error('Paymob payment intention failed', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return [
                'success' => false,
                'error' => $response->json()['detail'] ?? 'Failed to create payment intention',
            ];
        } catch (Exception $e) {
            Log::error('Paymob payment intention exception', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Payment service error: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Build the unified checkout URL
     */
    public function buildCheckoutUrl(string $clientSecret): string
    {
        return $this->baseUrl.'/unifiedcheckout/?'.http_build_query([
            'publicKey' => $this->publicKey,
            'clientSecret' => $clientSecret,
        ]);
    }

    /**
     * Validate HMAC signature from webhook
     */
    public function validateHmac(array $data, string $receivedHmac): bool
    {
        $concatenatedString = $this->buildHmacStringForWebhook($data);
        $calculatedHmac = hash_hmac('sha512', $concatenatedString, $this->hmacSecret);

        return hash_equals($calculatedHmac, $receivedHmac);
    }

    /**
     * Validate HMAC signature from callback redirect
     */
    public function validateCallbackHmac(array $data, string $receivedHmac): bool
    {
        $concatenatedString = $this->buildHmacStringForCallback($data);
        $calculatedHmac = hash_hmac('sha512', $concatenatedString, $this->hmacSecret);

        return hash_equals($calculatedHmac, $receivedHmac);
    }

    /**
     * Process webhook callback
     */
    public function processWebhook(array $webhookData): array
    {
        try {
            $transaction = $webhookData['obj'] ?? [];

            $transactionId = $transaction['id'] ?? null;
            $success = $transaction['success'] ?? false;
            $pending = $transaction['pending'] ?? false;
            $amountCents = $transaction['amount_cents'] ?? 0;
            $merchantOrderId = $transaction['order']['merchant_order_id'] ?? null;
            $paymentMethod = $transaction['source_data']['type'] ?? 'unknown';
            $isRefunded = $transaction['is_refunded'] ?? false;
            $isVoided = $transaction['is_voided'] ?? false;

            // Determine payment status
            $paymentStatus = $this->determinePaymentStatus($success, $pending, $isRefunded, $isVoided);

            return [
                'transaction_id' => $transactionId,
                'merchant_order_id' => $merchantOrderId,
                'payment_status' => $paymentStatus,
                'payment_method' => $paymentMethod,
                'amount_cents' => $amountCents,
                'success' => $success,
                'pending' => $pending,
                'payment_data' => json_encode($transaction),
            ];
        } catch (Exception $e) {
            Log::error('Error processing Paymob webhook', [
                'error' => $e->getMessage(),
                'webhook_data' => $webhookData,
            ]);

            throw $e;
        }
    }

    /**
     * Refund a transaction
     */
    public function refundTransaction(string $transactionId, float $amount): array
    {
        $payload = [
            'transaction_id' => (int) $transactionId,
            'amount_cents' => $this->convertToSmallestUnit($amount),
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Token '.$this->secretKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl.'/api/acceptance/void_refund/refund', $payload);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('Paymob refund successful', [
                    'transaction_id' => $transactionId,
                    'refund_id' => $data['id'] ?? null,
                    'amount' => $amount,
                ]);

                return [
                    'success' => true,
                    'data' => $data,
                ];
            }

            Log::error('Paymob refund failed', [
                'transaction_id' => $transactionId,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return [
                'success' => false,
                'error' => $response->json()['detail'] ?? 'Refund failed',
            ];
        } catch (Exception $e) {
            Log::error('Paymob refund exception', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Refund service error: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Convert from smallest currency unit to main unit
     */
    public function convertFromSmallestUnit(int $amountCents): float
    {
        return $amountCents / 100;
    }

    /**
     * Build HMAC string from webhook transaction data
     */
    private function buildHmacStringForWebhook(array $data): string
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
            $value = $this->getNestedValue($data, $key);
            $concatenated .= $this->convertValueToString($value);
        }

        return $concatenated;
    }

    /**
     * Build HMAC string from callback redirect data
     */
    private function buildHmacStringForCallback(array $data): string
    {
        // Keys for callback redirect as per Paymob documentation
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
            'order',
            'owner',
            'pending',
            'source_data_pan',
            'source_data_sub_type',
            'source_data_type',
            'success',
        ];

        $concatenated = '';
        foreach ($keys as $key) {
            $value = $data[$key] ?? '';
            $concatenated .= $this->convertValueToString($value);
        }

        return $concatenated;
    }

    /**
     * Get nested value from array using dot notation
     */
    private function getNestedValue(array $data, string $key): mixed
    {
        $keys = explode('.', $key);
        $value = $data;

        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                return '';
            }
        }

        return $value;
    }

    /**
     * Convert value to string for HMAC calculation
     */
    private function convertValueToString(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return '';
        }

        return (string) $value;
    }

    /**
     * Determine payment status based on transaction flags
     */
    private function determinePaymentStatus(bool $success, bool $pending, bool $isRefunded, bool $isVoided): string
    {
        if ($isRefunded) {
            return 'refunded';
        }

        if ($isVoided) {
            return 'failed';
        }

        if ($success && ! $pending) {
            return 'completed';
        }

        if ($pending) {
            return 'processing';
        }

        return 'failed';
    }

    /**
     * Convert amount to smallest currency unit (cents/piasters)
     */
    private function convertToSmallestUnit(float $amount): int
    {
        return (int) round($amount * 100);
    }

    /**
     * Generate unique merchant order ID
     */
    private function generateMerchantOrderId(Order $order): string
    {
        return 'ORD_'.$order->order_number.'_'.time();
    }

    /**
     * Prepare billing data with required fields
     */
    private function prepareBillingData(array $billingData): array
    {
        return [
            'first_name' => $billingData['first_name'] ?? 'NA',
            'last_name' => $billingData['last_name'] ?? 'NA',
            'email' => $billingData['email'] ?? 'customer@example.com',
            'phone_number' => $billingData['phone_number'] ?? '+201000000000',
            'apartment' => $billingData['apartment'] ?? 'NA',
            'floor' => $billingData['floor'] ?? 'NA',
            'street' => $billingData['street'] ?? 'NA',
            'building' => $billingData['building'] ?? 'NA',
            'city' => $billingData['city'] ?? 'Cairo',
            'country' => $billingData['country'] ?? 'EG',
            'postal_code' => $billingData['postal_code'] ?? 'NA',
        ];
    }
}
