<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Services\Contracts;

use App\Plugins\OrderManagement\src\DTOs\OrderTotals;
use App\Plugins\OrderManagement\src\DTOs\PlaceOrderData;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Pipelines\Context\OrderValidationContext;

interface OrderRepositoryInterface
{
    /**
     * Persist order, items, extras, and clear cart in a transaction.
     *
     * @param  array<int, array<string, mixed>>  $cartItems
     */
    public function createOrderWithItems(
        PlaceOrderData $data,
        array $cartItems,
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
