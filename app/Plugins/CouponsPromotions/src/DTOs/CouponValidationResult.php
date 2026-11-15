<?php

declare(strict_types=1);

namespace App\Plugins\CouponsPromotions\src\DTOs;

final readonly class CouponValidationResult
{
    public function __construct(
        public bool $valid,
        public string $message,
    ) {}

    public static function valid(string $message = 'Coupon is valid'): self
    {
        return new self(valid: true, message: $message);
    }

    public static function invalid(string $message): self
    {
        return new self(valid: false, message: $message);
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function message(): string
    {
        return $this->message;
    }
}
