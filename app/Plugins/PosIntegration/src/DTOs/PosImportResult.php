<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\DTOs;

final readonly class PosImportResult
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(
        public int $count,
        public int $failedCount,
        public array $errors = []
    ) {}
}
