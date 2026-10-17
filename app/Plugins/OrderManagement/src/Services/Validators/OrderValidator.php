<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Services\Validators;

use App\Plugins\OrderManagement\src\DTOs\PlaceOrderData;
use App\Plugins\OrderManagement\src\Pipelines\Context\OrderValidationContext;
use App\Plugins\OrderManagement\src\Pipelines\OrderValidationPipeline;
use App\Plugins\OrderManagement\src\Services\Contracts\OrderValidatorInterface;

class OrderValidator implements OrderValidatorInterface
{
    public function __construct(
        private OrderValidationPipeline $validationPipeline
    ) {}

    /**
     * @param  array<string, mixed>  $cart
     */
    public function validate(PlaceOrderData $data, array $cart): OrderValidationContext
    {
        $context = new OrderValidationContext($data, $cart);

        return $this->validationPipeline->process($context);
    }
}
