<?php

declare(strict_types=1);

namespace App\Plugins\CouponsPromotions;

use App\Core\Plugins\AbstractTenantPlugin;
use App\Models\Tenant;
use App\Plugins\CouponsPromotions\src\Listeners\RecordCouponUsageListener;
use App\Plugins\CouponsPromotions\src\Models\Coupon;
use App\Plugins\CouponsPromotions\src\Pipelines\Pipes\ApplyCouponDiscountPipe;
use App\Plugins\CouponsPromotions\src\Pipelines\Pipes\ValidateCouponPipe;
use App\Plugins\OrderManagement\src\Events\OrderCreated;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Pipelines\OrderPipelineRegistry;
use Illuminate\Support\Facades\Event;

final class CouponsPromotionsPlugin extends AbstractTenantPlugin
{
    public function bootForTenant(Tenant $tenant): void
    {
        Order::resolveRelationUsing('coupon', function (Order $order) {
            return $order->belongsTo(Coupon::class, 'coupon_id');
        });

        if (app()->bound(OrderPipelineRegistry::class)) {
            $registry = app(OrderPipelineRegistry::class);
            $registry->registerValidationPipe(ValidateCouponPipe::class, 350);
            $registry->registerPricingPipe(ApplyCouponDiscountPipe::class, 200);
        }
    }

    public function onActivate(Tenant $tenant): void
    {
    }

    public function onDeactivate(Tenant $tenant): void
    {
    }

    public function onUninstall(Tenant $tenant): void
    {
    }
}
