<?php

declare(strict_types=1);

namespace App\Plugins\HeroBanners\src\DTOs;

final readonly class HeroBannersConfig
{
    public function __construct(
        public bool $enabled = true
        // Add your typed configuration properties here
    ) {}
}
