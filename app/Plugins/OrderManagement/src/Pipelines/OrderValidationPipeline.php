<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Pipelines;

use App\Plugins\OrderManagement\src\Pipelines\Context\OrderValidationContext;
use Illuminate\Pipeline\Pipeline;

final readonly class OrderValidationPipeline
{
    public function __construct(
        private OrderPipelineRegistry $registry,
        private Pipeline $pipeline
    ) {}

    public function run(OrderValidationContext $context): OrderValidationContext
    {
        return $this->pipeline
            ->send($context)
            ->through($this->registry->getValidationPipes())
            ->then(fn (OrderValidationContext $ctx): OrderValidationContext => $ctx);
    }

    public function process(OrderValidationContext $context): OrderValidationContext
    {
        return $this->run($context);
    }
}
