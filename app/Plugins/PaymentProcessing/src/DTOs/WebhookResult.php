<?php

declare(strict_types=1);

namespace App\Plugins\PaymentProcessing\src\DTOs;

use App\Plugins\OrderManagement\src\Models\Order;

final readonly class WebhookResult
{
    public function __construct(
        public bool $successful,
        public ?Order $order = null,
        public mixed $paymentData = null,
        public ?string $error = null,
        public bool $duplicate = false,
    ) {}

    public static function success(?Order $order = null, mixed $paymentData = null, bool $duplicate = false): self
    {
        return new self(
            successful: true,
            order: $order,
            paymentData: $paymentData,
            duplicate: $duplicate,
        );
    }

    public static function failed(string $error): self
    {
        return new self(
            successful: false,
            error: $error,
        );
    }
}
