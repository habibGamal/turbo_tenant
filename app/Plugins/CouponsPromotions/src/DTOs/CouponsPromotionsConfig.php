<?php

declare(strict_types=1);

namespace App\Plugins\CouponsPromotions\src\DTOs;

final readonly class CouponsPromotionsConfig
{
    public function __construct(
        public bool $enabled = true
        // Add your typed configuration properties here
    ) {}
}
