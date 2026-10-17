<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Pipelines\Contracts;

use App\Plugins\OrderManagement\src\Pipelines\Context\PricingCalculationContext;
use Closure;

interface PricingCalculationPipeInterface
{
    public function handle(PricingCalculationContext $context, Closure $next): mixed;
}
