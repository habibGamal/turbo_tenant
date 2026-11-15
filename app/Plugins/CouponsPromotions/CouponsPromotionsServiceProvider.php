<?php

declare(strict_types=1);

namespace App\Plugins\CouponsPromotions;

use App\Plugins\CouponsPromotions\src\Http\Rules\CouponOrderRulesContributor;
use App\Plugins\CouponsPromotions\src\Models\Coupon;
use App\Plugins\CouponsPromotions\src\Rules\ActiveCouponRule;
use App\Plugins\CouponsPromotions\src\Rules\ApplicableItemsCouponRule;
use App\Plugins\CouponsPromotions\src\Rules\ExpiryCouponRule;
use App\Plugins\CouponsPromotions\src\Rules\MaxOrderTotalCouponRule;
use App\Plugins\CouponsPromotions\src\Rules\MaxUsageCouponRule;
use App\Plugins\CouponsPromotions\src\Rules\MinOrderTotalCouponRule;
use App\Plugins\CouponsPromotions\src\Rules\ShippingLocationCouponRule;
use App\Plugins\CouponsPromotions\src\Rules\TimeRestrictionsCouponRule;
use App\Plugins\CouponsPromotions\src\Rules\UserRestrictionsCouponRule;
use App\Plugins\CouponsPromotions\src\Services\CouponService;
use App\Plugins\OrderManagement\src\Models\Order;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class CouponsPromotionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CouponService::class, function (Application $app): CouponService {
            $rules = [
                $app->make(ActiveCouponRule::class),
                $app->make(ExpiryCouponRule::class),
                $app->make(MaxUsageCouponRule::class),
                $app->make(MinOrderTotalCouponRule::class),
                $app->make(MaxOrderTotalCouponRule::class),
                $app->make(ApplicableItemsCouponRule::class),
                $app->make(ShippingLocationCouponRule::class),
                $app->make(UserRestrictionsCouponRule::class),
                $app->make(TimeRestrictionsCouponRule::class),
            ];

            return new CouponService($rules);
        });

        $this->app->tag([CouponOrderRulesContributor::class], 'order.placement.rules');
    }

    public function boot(): void
    {
        $this->registerRelations();

        // 3. Load routes
        if (file_exists(__DIR__.'/routes/tenant.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/tenant.php');
        }
    }

    private function registerRelations(): void
    {
        // 1. Dynamic relationship on Order model
        Order::resolveRelationUsing('coupon', function (Order $order) {
            return $order->belongsTo(Coupon::class, 'coupon_id');
        });
    }
}
