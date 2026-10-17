<?php

declare(strict_types=1);

namespace App\Plugins\CouponsPromotions\src\Listeners;

use App\Plugins\CouponsPromotions\src\Models\Coupon;
use App\Plugins\CouponsPromotions\src\Services\CouponService;
use App\Plugins\OrderManagement\src\Events\OrderCreated;

final readonly class RecordCouponUsageListener
{
    public function __construct(private CouponService $couponService) {}

    public function handle(OrderCreated $event): void
    {
        $order = $event->order;
        if ($order->coupon_id && (float) $order->discount > 0) {
            $coupon = Coupon::find($order->coupon_id);
            if ($coupon) {
                $this->couponService->applyCoupon($coupon, (float) $order->discount);
            }
        }
    }
}
