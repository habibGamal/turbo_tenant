<?php

declare(strict_types=1);

namespace App\Plugins\CartEngine\src\Services\Contracts;

interface ProductValidatorInterface
{
    public function ensureProductIsActive(int $productId): void;

    /**
     * @param  array<int, mixed>  $extras
     */
    public function validateOptions(int $productId, ?int $variantId, ?int $weightOptionValueId, array $extras = []): void;

    public function validateUpdateWeightOption(int $productId, ?int $weightOptionValueId): void;
}
