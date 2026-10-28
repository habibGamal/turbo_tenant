<?php

declare(strict_types=1);

namespace App\Logging;

use Illuminate\Log\Logger;

final class AddRedaction
{
    public function __construct(
        private readonly RedactSensitiveContextProcessor $processor,
    ) {}

    public function __invoke(Logger $logger): void
    {
        $logger->pushProcessor($this->processor);
    }
}
