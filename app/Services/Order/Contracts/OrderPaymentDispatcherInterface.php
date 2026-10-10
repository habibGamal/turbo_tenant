<?php

declare(strict_types=1);

namespace App\Services\Order\Contracts;

use App\DTOs\Order\OrderPlacementResult;
use App\DTOs\Order\PlaceOrderData;
use App\Models\Order;

interface OrderPaymentDispatcherInterface
{
    /**
     * Dispatch payment intention to the active gateway and format the outcome.
     */
    public function dispatchPayment(Order $order, PlaceOrderData $data): OrderPlacementResult;
}
