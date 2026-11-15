<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\DTOs;

final readonly class SelectionRules
{
    public function __construct(
        public ?int $minSelections,
        public ?int $maxSelections,
        public bool $allowMultiple,
    ) {}
}
