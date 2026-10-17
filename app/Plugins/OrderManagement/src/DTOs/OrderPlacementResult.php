<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\DTOs;

use App\Plugins\OrderManagement\src\Models\Order;

readonly class OrderPlacementResult
{
    /**
     * @param  array<string, mixed>  $paymentData
     */
    public function __construct(
        public bool $successful,
        public ?Order $order = null,
        public bool $requiresPayment = false,
        public ?string $checkoutUrl = null,
        public array $paymentData = [],
        public ?string $errorMessage = null
    ) {}

    /**
     * @param  array<string, mixed>  $paymentData
     */
    public static function success(
        Order $order,
        bool $requiresPayment = false,
        ?string $checkoutUrl = null,
        array $paymentData = []
    ): self {
        return new self(
            successful: true,
            order: $order,
            requiresPayment: $requiresPayment,
            checkoutUrl: $checkoutUrl,
            paymentData: $paymentData
        );
    }

    public static function failure(string $errorMessage, ?Order $order = null): self
    {
        return new self(
            successful: false,
            order: $order,
            errorMessage: $errorMessage
        );
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    public function requiresPayment(): bool
    {
        return $this->requiresPayment;
    }
}
