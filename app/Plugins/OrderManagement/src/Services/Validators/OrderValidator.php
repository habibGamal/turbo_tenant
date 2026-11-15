<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Services\Validators;

use App\Plugins\CartEngine\src\DTOs\CartLine;
use App\Plugins\OrderManagement\src\DTOs\PlaceOrderData;
use App\Plugins\OrderManagement\src\Pipelines\Context\OrderValidationContext;
use App\Plugins\OrderManagement\src\Pipelines\OrderValidationPipeline;
use App\Plugins\OrderManagement\src\Services\Contracts\OrderValidatorInterface;

final class OrderValidator implements OrderValidatorInterface
{
    public function __construct(
        private OrderValidationPipeline $validationPipeline
    ) {}

    /**
     * @param  list<CartLine>  $lines
     */
    public function validate(PlaceOrderData $data, array $lines): OrderValidationContext
    {
        $context = new OrderValidationContext($data, $lines);

        return $this->validationPipeline->process($context);
    }
}
