<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\Models\Order;

/**
 * Interface for payment gateway implementations.
 * Allows switching between different payment providers (Paymob, Kashier, etc.)
 */
interface PaymentGatewayInterface
{
    /**
     * Get the unique identifier for this gateway.
     */
    public function getGatewayId(): string;

    /**
     * Create a payment intention for an order.
     *
     * @param Order $order The order to create payment for
     * @param array $billingData Customer billing information
     * @param string $redirectionUrl URL to redirect after successful payment
     * @param string $notificationUrl Webhook URL for payment notifications
     * @return array{success: bool, data?: array, checkout_url?: string, error?: string}
     */
    public function createPaymentIntention(
        Order $order,
        array $billingData,
        string $redirectionUrl,
        string $notificationUrl
    ): array;

    /**
     * Validate HMAC signature from webhook.
     *
     * @param array $data Webhook payload data
     * @param string $receivedHmac The HMAC signature received
     */
    public function validateHmac(array $data, string $receivedHmac): bool;

    /**
     * Validate HMAC signature from callback redirect.
     *
     * @param array $data Callback query parameters
     * @param string $receivedHmac The HMAC signature received
     */
    public function validateCallbackHmac(array $data, string $receivedHmac): bool;

    /**
     * Process webhook callback data.
     *
     * @param array $webhookData Raw webhook payload
     * @return array Processed payment data
     */
    public function processWebhook(array $webhookData): array;

    /**
     * Process a refund for a transaction.
     *
     * @param string $transactionId The transaction to refund
     * @param float $amount The amount to refund
     * @return array{success: bool, data?: array, error?: string}
     */
    public function refundTransaction(string $transactionId, float $amount): array;

    /**
     * Convert from smallest currency unit to main unit.
     */
    public function convertFromSmallestUnit(int $amountCents): float;
}
