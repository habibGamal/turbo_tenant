<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Contracts;

interface CustomerOrderHistory
{
    /**
     * Counts non-cancelled orders for a customer.
     *
     * Registered customers match by user_id. Guests match by customer_phone OR guest_user_id,
     * so a guest's older orders that were saved without a phone still count toward coupon limits.
     */
    public function countActiveOrders(?int $userId, ?string $phone, ?int $couponId = null, ?int $guestUserId = null): int;
}
