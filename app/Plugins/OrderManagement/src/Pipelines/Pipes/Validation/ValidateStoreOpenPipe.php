<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Pipelines\Pipes\Validation;

use App\Exceptions\OrderValidationException;
use App\Plugins\OrderManagement\src\DTOs\OrderManagementConfig;
use App\Plugins\OrderManagement\src\Pipelines\Context\OrderValidationContext;
use App\Plugins\OrderManagement\src\Pipelines\Contracts\OrderValidationPipeInterface;
use Closure;

final readonly class ValidateStoreOpenPipe implements OrderValidationPipeInterface
{
    public function __construct(private OrderManagementConfig $config) {}

    public function handle(OrderValidationContext $context, Closure $next): mixed
    {
        if (! $this->config->isStoreOpen() && ! $this->config->acceptOrdersAfterWorkTimes()) {
            throw OrderValidationException::withMessage('We are currently closed. Please try again during our work hours.');
        }

        return $next($context);
    }
}
