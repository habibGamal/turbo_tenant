<?php

declare(strict_types=1);

namespace App\Plugins\CouponsPromotions;

use App\Core\Plugins\AbstractTenantPlugin;
use App\Models\Tenant;
use App\Plugins\CouponsPromotions\src\Pipelines\Pipes\ApplyCouponDiscountPipe;
use App\Plugins\CouponsPromotions\src\Pipelines\Pipes\ValidateCouponPipe;
use App\Plugins\OrderManagement\src\Pipelines\OrderPipelineRegistry;

final class CouponsPromotionsPlugin extends AbstractTenantPlugin
{
    public function bootForTenant(Tenant $tenant): void
    {
        if (app()->bound(OrderPipelineRegistry::class)) {
            $registry = app(OrderPipelineRegistry::class);
            $registry->registerValidationPipe(ValidateCouponPipe::class, 350);
            $registry->registerPricingPipe(ApplyCouponDiscountPipe::class, 200);
        }
    }

    public function onActivate(Tenant $tenant): void {}

    public function onDeactivate(Tenant $tenant): void {}

    public function onUninstall(Tenant $tenant): void {}
}
