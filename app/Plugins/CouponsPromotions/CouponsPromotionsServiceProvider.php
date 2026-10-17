<?php

declare(strict_types=1);

namespace App\Plugins\CouponsPromotions;

use App\Plugins\CouponsPromotions\src\DTOs\CouponsPromotionsConfig;
use App\Plugins\CouponsPromotions\src\Listeners\RecordCouponUsageListener;
use App\Plugins\CouponsPromotions\src\Models\Coupon;
use App\Plugins\CouponsPromotions\src\Pipelines\Pipes\ApplyCouponDiscountPipe;
use App\Plugins\CouponsPromotions\src\Pipelines\Pipes\ValidateCouponPipe;
use App\Plugins\CouponsPromotions\src\Services\CouponService;
use App\Plugins\OrderManagement\src\Events\OrderCreated;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Pipelines\OrderPipelineRegistry;
use App\Plugins\StoreSettings\src\Services\SettingService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class CouponsPromotionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CouponsPromotionsConfig::class, function ($app) {
            $settings = $app->make(SettingService::class);

            return new CouponsPromotionsConfig(
                enabled: (bool) $settings->get('coupons-promotions.enabled', true)
            );
        });

        $this->app->singleton(CouponService::class);
    }

    public function boot(): void
    {
        // 1. Dynamic relationship on Order model
        Order::resolveRelationUsing('coupon', function (Order $order) {
            return $order->belongsTo(Coupon::class, 'coupon_id');
        });

        // 2. Listen to OrderCreated event
        Event::listen(OrderCreated::class, RecordCouponUsageListener::class);

        // 3. Load routes
        if (file_exists(__DIR__.'/routes/tenant.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/tenant.php');
        }
    }
}
