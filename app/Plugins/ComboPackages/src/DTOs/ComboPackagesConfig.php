<?php

declare(strict_types=1);

namespace App\Plugins\ComboPackages\src\DTOs;

final readonly class ComboPackagesConfig
{
    public function __construct(
        public bool $enabled = true
        // Add your typed configuration properties here
    ) {}
}
