<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Pipelines\Pipes\Pricing;

use App\Plugins\OrderManagement\src\DTOs\OrderManagementConfig;
use App\Plugins\OrderManagement\src\Pipelines\Context\PricingCalculationContext;
use App\Plugins\OrderManagement\src\Pipelines\Contracts\PricingCalculationPipeInterface;
use App\Plugins\OrderManagement\src\Pipelines\DTOs\PricingAdjustment;
use Closure;

final readonly class CalculateTaxPipe implements PricingCalculationPipeInterface
{
    public function __construct(private OrderManagementConfig $config) {}

    public function handle(PricingCalculationContext $context, Closure $next): mixed
    {
        $discountTotal = abs($context->getAdjustmentTotal(PricingAdjustment::TYPE_DISCOUNT));
        $taxableAmount = max(0.0, $context->subTotal - $discountTotal);

        $taxRate = $this->config->getTaxRate();
        if ($taxRate > 0) {
            $taxAmount = round($taxableAmount * $taxRate, 2);
            $context->addAdjustment(new PricingAdjustment(
                identifier: 'tax:vat',
                label: 'Tax',
                amount: $taxAmount,
                type: PricingAdjustment::TYPE_TAX
            ));
        }

        return $next($context);
    }
}
