<?php

declare(strict_types=1);

namespace App\Plugins\CartEngine\src\DTOs;

final readonly class CartEngineConfig
{
    public function __construct(
        public int $sessionLifetimeDays = 30,
        public int $maxItemsPerCart = 100
    ) {}
}
