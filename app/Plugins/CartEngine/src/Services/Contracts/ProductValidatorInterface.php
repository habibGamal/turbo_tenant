<?php

declare(strict_types=1);

namespace App\Plugins\CartEngine\src\Services\Contracts;

interface ProductValidatorInterface
{
    public function ensureProductIsActive(int $productId): void;
}
