<?php

declare(strict_types=1);

namespace App\Plugins\CouponsPromotions\src\Listeners;

use App\Plugins\CouponsPromotions\src\Models\Coupon;
use App\Plugins\OrderManagement\src\Events\OrderPaymentFailed;
use Illuminate\Support\Facades\DB;

final readonly class ReleaseCouponUsageListener
{
    public function handle(OrderPaymentFailed $event): void
    {
        $order = $event->order;
        $discount = (float) $order->discount;

        if (! $order->coupon_id || $discount <= 0.0) {
            return;
        }

        $amount = number_format($discount, 2, '.', '');

        Coupon::query()
            ->where('id', $order->coupon_id)
            ->update([
                'usage_count' => DB::raw('CASE WHEN usage_count > 0 THEN usage_count - 1 ELSE 0 END'),
                'total_consumed' => DB::raw('CASE WHEN total_consumed >= '.$amount.' THEN total_consumed - '.$amount.' ELSE 0 END'),
            ]);
    }
}
