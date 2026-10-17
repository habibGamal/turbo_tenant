<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Pipelines;

use App\Plugins\OrderManagement\src\Pipelines\Context\PricingCalculationContext;
use Illuminate\Pipeline\Pipeline;

final readonly class PricingCalculationPipeline
{
    public function __construct(
        private OrderPipelineRegistry $registry,
        private Pipeline $pipeline
    ) {}

    public function run(PricingCalculationContext $context): PricingCalculationContext
    {
        return $this->pipeline
            ->send($context)
            ->through($this->registry->getPricingPipes())
            ->then(fn (PricingCalculationContext $ctx): PricingCalculationContext => $ctx);
    }

    public function process(PricingCalculationContext $context): PricingCalculationContext
    {
        return $this->run($context);
    }
}
