<?php

declare(strict_types=1);

namespace App\Plugins\PaymentProcessing\src\DTOs;

final readonly class GatewayWebhookData
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $transactionId,
        public string $merchantOrderId,
        public int $amountCents,
        public string $currency,
        public bool $successful,
        public string $paymentStatus = 'pending',
        public array $raw = [],
        public ?string $paymentData = null,
    ) {}
}
