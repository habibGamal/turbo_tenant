<?php

declare(strict_types=1);

namespace App\Plugins\PaymentProcessing\src\DTOs;

final readonly class RefundResult
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public bool $successful,
        public array $data = [],
        public ?string $error = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function success(array $data = []): self
    {
        return new self(
            successful: true,
            data: $data,
        );
    }

    public static function failure(string $error): self
    {
        return new self(
            successful: false,
            error: $error,
        );
    }
}
