<?php

declare(strict_types=1);

namespace App\Services\Cart\Contracts;

interface ProductValidatorInterface
{
    public function ensureProductIsActive(int $productId): void;
}
