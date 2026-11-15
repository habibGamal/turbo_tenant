<?php

declare(strict_types=1);

namespace App\Plugins\CartEngine\src\DTOs;

final readonly class CartLineExtra
{
    public function __construct(
        public int $extraOptionItemId,
        public string $name,
        public ?string $nameAr,
        public float $unitPrice,
        public int $quantity
    ) {}
}
