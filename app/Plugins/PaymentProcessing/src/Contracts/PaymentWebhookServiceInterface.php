<?php

declare(strict_types=1);

namespace App\Plugins\PaymentProcessing\src\Contracts;

use App\Plugins\OrderManagement\src\Contracts\PaymentGatewayInterface;

interface PaymentWebhookServiceInterface
{
    /**
     * Handle webhook notification from payment gateway.
     *
     * @param  array<string, mixed>  $webhookData
     * @return array<string, mixed>
     */
    public function handleWebhook(array $webhookData, string $signature, PaymentGatewayInterface $gateway): array;
}
