<?php

declare(strict_types=1);

namespace App\Plugins\PaymentProcessing\src\Contracts;

use App\Plugins\OrderManagement\src\Contracts\PaymentInitiator;
use App\Plugins\PaymentProcessing\src\DTOs\GatewayWebhookData;
use App\Plugins\PaymentProcessing\src\DTOs\RefundResult;

/**
 * Interface for payment gateway implementations.
 * Allows switching between different payment providers (Paymob, Kashier, etc.)
 */
interface PaymentGatewayInterface extends PaymentInitiator
{
    /**
     * Validate HMAC signature from webhook.
     *
     * @param  array  $data  Webhook payload data
     * @param  string  $receivedHmac  The HMAC signature received
     */
    public function validateHmac(array $data, string $receivedHmac): bool;

    /**
     * Validate HMAC signature from callback redirect.
     *
     * @param  array  $data  Callback query parameters
     * @param  string  $receivedHmac  The HMAC signature received
     */
    public function validateCallbackHmac(array $data, string $receivedHmac): bool;

    /**
     * Process webhook callback data.
     *
     * @param  array  $webhookData  Raw webhook payload
     */
    public function processWebhook(array $webhookData): GatewayWebhookData;

    /**
     * Process a refund for a transaction.
     *
     * @param  string  $transactionId  The transaction to refund
     * @param  float  $amount  The amount to refund
     */
    public function refundTransaction(string $transactionId, float $amount): RefundResult;

    /**
     * Convert from smallest currency unit to main unit.
     */
    public function convertFromSmallestUnit(int $amountCents): float;

    /**
     * Get the configured currency for this gateway.
     */
    public function getCurrency(): string;
}
