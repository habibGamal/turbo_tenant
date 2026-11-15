<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Services;

use App\Plugins\OrderManagement\src\Contracts\CustomerOrderHistory;
use App\Plugins\OrderManagement\src\Enums\OrderStatus;
use App\Plugins\OrderManagement\src\Models\Order;
use Illuminate\Database\Eloquent\Builder;

final class EloquentCustomerOrderHistory implements CustomerOrderHistory
{
    public function countActiveOrders(?int $userId, ?string $phone, ?int $couponId = null, ?int $guestUserId = null): int
    {
        $hasPhone = $phone !== null && $phone !== '';

        if ($userId === null && ! $hasPhone && $guestUserId === null) {
            return 0;
        }

        $query = Order::query()->where('status', '!=', OrderStatus::CANCELLED->value);

        if ($couponId !== null) {
            $query->where('coupon_id', $couponId);
        }

        if ($userId !== null) {
            return $query->where('user_id', $userId)->count();
        }

        return $query
            ->where(function (Builder $guestQuery) use ($hasPhone, $phone, $guestUserId): void {
                if ($hasPhone) {
                    $guestQuery->orWhere('customer_phone', $phone);
                }

                if ($guestUserId !== null) {
                    $guestQuery->orWhere('guest_user_id', $guestUserId);
                }
            })
            ->count();
    }
}
