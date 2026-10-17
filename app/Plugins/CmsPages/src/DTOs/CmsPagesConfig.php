<?php

declare(strict_types=1);

namespace App\Plugins\CmsPages\src\DTOs;

final readonly class CmsPagesConfig
{
    public function __construct(
        public bool $enabled = true
        // Add your typed configuration properties here
    ) {}
}
