<?php

declare(strict_types=1);

namespace App\Services\Order\Contracts;

use App\DTOs\Order\PlaceOrderData;
use App\Models\Coupon;

interface OrderValidatorInterface
{
    /**
     * Validate pre-order conditions and return the validated coupon if one was provided.
     *
     * @param  array<string, mixed>  $cart
     *
     * @throws \App\Exceptions\OrderValidationException
     */
    public function validate(PlaceOrderData $data, array $cart): ?Coupon;
}
