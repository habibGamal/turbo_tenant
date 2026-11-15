<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Contracts;

interface ProductPriceUpdater
{
    public function updatePrices(int $productId, float $basePrice, ?float $priceAfterDiscount): void;
}
