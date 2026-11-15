<?php

declare(strict_types=1);

namespace App\Plugins\PaymentProcessing\src\Contracts;

use App\Plugins\PaymentProcessing\src\DTOs\WebhookResult;

interface PaymentWebhookServiceInterface
{
    /**
     * Handle webhook notification from payment gateway.
     *
     * @param  array<string, mixed>  $webhookData
     */
    public function handleWebhook(array $webhookData, string $signature, PaymentGatewayInterface $gateway): WebhookResult;
}
