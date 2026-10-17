<?php

declare(strict_types=1);

namespace App\Plugins\CustomerFavorites\src\DTOs;

final readonly class CustomerFavoritesConfig
{
    public function __construct(
        public bool $enabled = true
        // Add your typed configuration properties here
    ) {}
}
