<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Services\Contracts;

use App\Plugins\OrderManagement\src\DTOs\OrderPlacementResult;
use App\Plugins\OrderManagement\src\DTOs\PlaceOrderData;
use App\Plugins\OrderManagement\src\Models\Order;

interface OrderPaymentDispatcherInterface
{
    /**
     * Dispatch payment intention to the active gateway and format the outcome.
     */
    public function dispatchPayment(Order $order, PlaceOrderData $data): OrderPlacementResult;
}
