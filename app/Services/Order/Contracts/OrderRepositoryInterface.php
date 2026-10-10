<?php

declare(strict_types=1);

namespace App\Services\Order\Contracts;

use App\DTOs\Order\OrderTotals;
use App\DTOs\Order\PlaceOrderData;
use App\Models\Coupon;
use App\Models\Order;

interface OrderRepositoryInterface
{
    /**
     * Persist order, items, extras, apply coupon usage and clear cart in a transaction.
     *
     * @param  array<int, array<string, mixed>>  $cartItems
     */
    public function createOrderWithItems(
        PlaceOrderData $data,
        array $cartItems,
        OrderTotals $totals,
        ?Coupon $coupon
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
