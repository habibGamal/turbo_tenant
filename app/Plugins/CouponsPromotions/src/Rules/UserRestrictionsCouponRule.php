<?php

declare(strict_types=1);

namespace App\Plugins\CouponsPromotions\src\Rules;

use App\Plugins\CouponsPromotions\src\DTOs\CouponContext;
use App\Plugins\CouponsPromotions\src\Models\Coupon;
use App\Plugins\OrderManagement\src\Contracts\CustomerOrderHistory;

final readonly class UserRestrictionsCouponRule implements CouponRule
{
    private const MESSAGE = 'This coupon is not available for your account';

    public function __construct(
        private CustomerOrderHistory $orderHistory,
    ) {}

    public function check(Coupon $coupon, CouponContext $context): ?string
    {
        $restrictions = $coupon->conditions['usage_restrictions'] ?? [];

        $customer = $context->customer;
        $userId = $customer !== null && ! $customer->isGuest() ? (int) $customer->customerId() : null;
        $hasIdentity = $userId !== null || $context->phone !== null || $context->guestId !== null;

        if (! empty($restrictions['user_specific'])) {
            if ($userId === null) {
                return self::MESSAGE;
            }

            $userIds = $restrictions['user_ids'] ?? [];
            if (! empty($userIds) && ! in_array($userId, $userIds)) {
                return self::MESSAGE;
            }
        }

        if (! empty($restrictions['first_order_only'])
            && $hasIdentity
            && $this->orderHistory->countActiveOrders($userId, $context->phone, null, $context->guestId) > 0) {
            return self::MESSAGE;
        }

        $maxPerUser = $restrictions['max_per_user'] ?? $restrictions['max_usage_per_user'] ?? null;
        if ($maxPerUser !== null
            && $hasIdentity
            && $this->orderHistory->countActiveOrders($userId, $context->phone, (int) $coupon->id, $context->guestId) >= (int) $maxPerUser) {
            return self::MESSAGE;
        }

        return null;
    }
}
