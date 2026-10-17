<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\DTOs;

final readonly class PosIntegrationConfig
{
    public function __construct(
        public string $masterRepoUrl = '',
        public bool $autoAcceptOrders = false,
        public int $timeoutSeconds = 30,
        public bool $enabled = true
    ) {}
}
