<?php

declare(strict_types=1);

namespace App\Plugins\ProductReviews\src\DTOs;

final readonly class ProductReviewsConfig
{
    public function __construct(
        public bool $enabled = true
        // Add your typed configuration properties here
    ) {}
}
