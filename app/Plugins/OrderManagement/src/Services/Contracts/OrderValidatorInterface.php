<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Services\Contracts;

use App\Plugins\OrderManagement\src\DTOs\PlaceOrderData;
use App\Plugins\OrderManagement\src\Pipelines\Context\OrderValidationContext;

interface OrderValidatorInterface
{
    /**
     * Validate pre-order conditions using the pipeline.
     *
     * @param  array<string, mixed>  $cart
     *
     * @throws \App\Plugins\OrderManagement\src\Exceptions\OrderValidationException
     */
    public function validate(PlaceOrderData $data, array $cart): OrderValidationContext;
}
