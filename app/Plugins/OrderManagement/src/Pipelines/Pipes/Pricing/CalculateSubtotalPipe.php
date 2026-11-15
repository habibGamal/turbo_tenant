<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Pipelines\Pipes\Pricing;

use App\Plugins\CartEngine\src\DTOs\CartLine;
use App\Plugins\OrderManagement\src\Pipelines\Context\PricingCalculationContext;
use App\Plugins\OrderManagement\src\Pipelines\Contracts\PricingCalculationPipeInterface;
use Closure;

final class CalculateSubtotalPipe implements PricingCalculationPipeInterface
{
    public function handle(PricingCalculationContext $context, Closure $next): mixed
    {
        $context->subTotal = (float) array_reduce(
            $context->lines,
            fn (float $total, CartLine $line): float => $total + $line->subtotal,
            0.0
        );

        return $next($context);
    }
}
