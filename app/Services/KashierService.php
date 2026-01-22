<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SettingKey;
use App\Interfaces\PaymentGatewayInterface;
use App\Models\Order;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Kashier Payment Gateway Service.
 * Handles payment processing through Kashier's iFrame checkout.
 */
final class KashierService implements PaymentGatewayInterface
{
    private string $merchantId;

    private string $apiKey;

    private string $secretKey;

    private string $mode;

    private string $currency;

    private string $allowedMethods;

    public function __construct(
        private readonly SettingService $settingService
    ) {
        $this->merchantId = $this->settingService->get(SettingKey::KASHIER_MERCHANT_ID, '');
        $this->apiKey = $this->settingService->get(SettingKey::KASHIER_API_KEY, '');
        $this->secretKey = $this->settingService->get(SettingKey::KASHIER_SECRET_KEY, '');
        $this->mode = $this->settingService->get(SettingKey::KASHIER_MODE, 'test');
        $this->currency = $this->settingService->get(SettingKey::KASHIER_CURRENCY, 'EGP');
        $this->allowedMethods = $this->settingService->get(SettingKey::KASHIER_ALLOWED_METHODS, 'card');
    }

    /**
     * Get the unique identifier for this gateway.
     */
    public function getGatewayId(): string
    {
        return 'kashier';
    }

    /**
     * Create a payment intention for an order.
     * Returns payment data needed for the Kashier iFrame SDK.
     */
    public function createPaymentIntention(
        Order $order,
        array $billingData,
        string $redirectionUrl,
        string $notificationUrl
    ): array {
        try {
            $merchantOrderId = $this->generateMerchantOrderId($order);
            $amount = number_format((float) $order->total, 2, '.', '');

            // Generate HMAC-SHA256 hash for Kashier
            $path = "/?payment={$this->merchantId}.{$merchantOrderId}.{$amount}.{$this->currency}";
            $hash = hash_hmac('sha256', $path, $this->apiKey);

            // Update order with merchant_order_id
            $order->update([
                'merchant_order_id' => $merchantOrderId,
                'payment_status' => 'pending',
            ]);

            Log::info('Kashier payment data generated', [
                'order_id' => $order->id,
                'merchant_order_id' => $merchantOrderId,
                'amount' => $amount,
                'currency' => $this->currency,
                'mode' => $this->mode,
            ]);

            // Return data for the frontend Kashier component
            $kashierParams = [
                'merchantId' => $this->merchantId,
                'orderId' => $merchantOrderId,
                'amount' => $amount,
                'currency' => $this->currency,
                'hash' => $hash,
                'mode' => $this->mode,
                'merchantRedirect' => $redirectionUrl,
                'serverWebhook' => $notificationUrl,
                'failureRedirect' => str_replace('/callback', '/failure', $redirectionUrl),
                'displayMode' => 'ar',
                'allowedMethods' => $this->allowedMethods,
                'paymentRequestId' => uniqid('pr_'),
            ];

            // For Kashier, the frontend will load the iFrame SDK directly
            // We return a special checkout URL that renders our Kashier component
            $checkoutUrl = url("/payments/{$order->id}/kashier");

            return [
                'success' => true,
                'data' => $kashierParams,
                'checkout_url' => $checkoutUrl,
                'kashier_params' => $kashierParams,
            ];
        } catch (Exception $e) {
            Log::error('Kashier payment intention exception', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Payment service error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Validate HMAC signature from webhook.
     */
    public function validateHmac(array $data, string $receivedHmac): bool
    {
        return $this->validateWebhookPayload($data, $receivedHmac);
    }

    /**
     * Validate HMAC signature from callback redirect.
     * For Kashier, callback validation relies on webhook verification.
     */
    public function validateCallbackHmac(array $data, string $receivedHmac): bool
    {
        // For URL redirect parameters, rely on webhook for verification
        if (isset($data['merchantOrderId']) || isset($data['orderReference'])) {
            return true;
        }

        // For responses with signature
        if (!isset($data['signature'])) {
            Log::warning('No signature found in Kashier payment response');
            return false;
        }

        $queryString = '';
        foreach ($data as $key => $value) {
            if ($key === 'signature' || $key === 'mode') {
                continue;
            }
            $queryString .= "&{$key}={$value}";
        }

        $queryString = ltrim($queryString, '&');
        $expectedSignature = hash_hmac('sha256', $queryString, $this->apiKey);

        return hash_equals($expectedSignature, $data['signature']);
    }

    /**
     * Process webhook callback data from Kashier.
     */
    public function processWebhook(array $webhookData): array
    {
        try {
            $data = $webhookData['data'] ?? $webhookData;

            $transactionId = $data['transactionId'] ?? null;
            $merchantOrderId = $data['merchantOrderId'] ?? null;
            $status = $data['status'] ?? 'UNKNOWN';
            $amount = $data['amount'] ?? 0;

            // Determine payment status
            $paymentStatus = match (strtoupper($status)) {
                'SUCCESS', 'CAPTURED' => 'completed',
                'PENDING' => 'processing',
                'REFUNDED' => 'refunded',
                default => 'failed',
            };

            return [
                'transaction_id' => $transactionId,
                'merchant_order_id' => $merchantOrderId,
                'payment_status' => $paymentStatus,
                'amount_cents' => (int) ($amount * 100),
                'success' => $paymentStatus === 'completed',
                'pending' => $paymentStatus === 'processing',
                'payment_data' => json_encode($data),
            ];
        } catch (Exception $e) {
            Log::error('Error processing Kashier webhook', [
                'error' => $e->getMessage(),
                'webhook_data' => $webhookData,
            ]);

            throw $e;
        }
    }

    /**
     * Refund a transaction through Kashier API.
     */
    public function refundTransaction(string $transactionId, float $amount): array
    {
        $baseUrl = $this->mode === 'live'
            ? 'https://fep.kashier.io'
            : 'https://test-fep.kashier.io';

        $url = "{$baseUrl}/v3/orders/{$transactionId}/";

        $payload = [
            'apiOperation' => 'REFUND',
            'reason' => 'Order refund',
            'transaction' => [
                'amount' => (float) number_format($amount, 2, '.', ''),
            ],
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => $this->secretKey,
                'accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
                ->withOptions([
                    'verify' => config('app.env') === 'production',
                ])
                ->put($url, $payload);

            $responseData = $response->json();

            if (
                $response->successful() &&
                ($responseData['status'] ?? null) === 'SUCCESS' &&
                ($responseData['response']['status'] ?? null) === 'REFUNDED'
            ) {
                Log::info('Kashier refund successful', [
                    'transaction_id' => $transactionId,
                    'amount' => $amount,
                    'refund_transaction_id' => $responseData['response']['transactionId'] ?? null,
                ]);

                return [
                    'success' => true,
                    'data' => $responseData,
                ];
            }

            Log::error('Kashier refund failed', $responseData);

            return [
                'success' => false,
                'error' => $responseData['message'] ?? 'Refund failed',
            ];
        } catch (Exception $e) {
            Log::error('Kashier refund exception', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Refund service error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Convert from smallest currency unit to main unit.
     */
    public function convertFromSmallestUnit(int $amountCents): float
    {
        return $amountCents / 100;
    }

    /**
     * Validate webhook payload signature from Kashier.
     */
    private function validateWebhookPayload(array $webhookData, string $kashierSignature): bool
    {
        try {
            $data = $webhookData['data'] ?? $webhookData;

            if (!isset($data['signatureKeys']) || !is_array($data['signatureKeys'])) {
                Log::warning('Kashier webhook missing signatureKeys');
                return false;
            }

            $signatureKeys = $data['signatureKeys'];
            sort($signatureKeys);

            $signatureData = [];
            foreach ($signatureKeys as $key) {
                if (isset($data[$key])) {
                    $signatureData[$key] = $data[$key];
                }
            }

            $queryString = http_build_query($signatureData, '', '&', PHP_QUERY_RFC3986);
            $expectedSignature = hash_hmac('sha256', $queryString, $this->apiKey, false);

            Log::info('Kashier webhook signature validation', [
                'query_string' => $queryString,
                'expected_signature' => $expectedSignature,
                'received_signature' => $kashierSignature,
            ]);

            return hash_equals($expectedSignature, $kashierSignature);
        } catch (Exception $e) {
            Log::error('Error validating Kashier webhook signature', [
                'exception' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Generate unique merchant order ID for Kashier.
     */
    private function generateMerchantOrderId(Order $order): string
    {
        return sprintf(
            'ORD-%d-%s-%s',
            $order->id,
            date('Ymd'),
            strtoupper(substr(uniqid(), -6))
        );
    }

    /**
     * Get the API base URL based on mode.
     */
    public function getApiBaseUrl(): string
    {
        return $this->mode === 'live'
            ? 'https://api.kashier.io'
            : 'https://test-api.kashier.io';
    }
}
