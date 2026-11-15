<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Contracts;

final readonly class ImportedProduct
{
    public function __construct(
        public string $name,
        public float $basePrice,
        public ?float $priceAfterDiscount,
        public string $categoryName,
        public ?string $categoryImage = null,
        public ?string $image = null,
    ) {}
}
