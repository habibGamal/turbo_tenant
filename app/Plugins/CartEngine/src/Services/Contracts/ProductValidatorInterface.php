<?php

declare(strict_types=1);

namespace App\Plugins\CartEngine\src\Services\Contracts;

interface ProductValidatorInterface
{
    /**
     * @param  list<array{id: int, quantity: int}>  $extras
     */
    public function validateOptions(?int $productId, ?int $variantId, ?int $weightOptionValueId, array $extras = []): void;

    public function validateUpdateWeightOption(?int $productId, ?int $weightOptionValueId): void;
}
