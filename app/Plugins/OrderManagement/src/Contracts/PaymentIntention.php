<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Contracts;

final readonly class PaymentIntention
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public bool $successful,
        public ?string $checkoutUrl = null,
        public array $data = [],
        public ?string $error = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function created(?string $checkoutUrl = null, array $data = []): self
    {
        return new self(
            successful: true,
            checkoutUrl: $checkoutUrl,
            data: $data,
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
