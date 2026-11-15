<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Services\Contracts;

use App\Plugins\CartEngine\src\DTOs\CartLine;
use App\Plugins\OrderManagement\src\DTOs\OrderTotals;
use App\Plugins\OrderManagement\src\DTOs\PlaceOrderData;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Pipelines\Context\OrderValidationContext;

interface OrderRepositoryInterface
{
    /**
     * Persist order, items, and extras in a transaction. Offline orders also clear the cart there;
     * online orders retain it until payment initialization succeeds.
     *
     * @param  list<CartLine>  $lines
     */
    public function createOrderWithItems(
        PlaceOrderData $data,
        array $lines,
        OrderTotals $totals,
        ?OrderValidationContext $validationContext = null
    ): Order;

    /**
     * Finalize order when no online payment is required (COD, Credit).
     */
    public function finalizeOfflineOrder(Order $order): Order;

    /**
     * Mark order as cancelled and payment failed upon gateway initialization failure.
     */
    public function markOrderAsPaymentFailed(Order $order): void;
}
