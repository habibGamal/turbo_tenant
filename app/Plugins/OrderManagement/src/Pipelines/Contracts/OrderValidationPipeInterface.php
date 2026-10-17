<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Pipelines\Contracts;

use App\Plugins\OrderManagement\src\Pipelines\Context\OrderValidationContext;
use Closure;

interface OrderValidationPipeInterface
{
    public function handle(OrderValidationContext $context, Closure $next): mixed;
}
