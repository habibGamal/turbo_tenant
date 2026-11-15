<?php

declare(strict_types=1);

namespace App\Plugins\CouponsPromotions;

use App\Core\Plugins\AbstractTenantPlugin;
use App\Models\Tenant;
use App\Plugins\CouponsPromotions\src\Pipelines\Pipes\ApplyCouponDiscountPipe;
use App\Plugins\CouponsPromotions\src\Pipelines\Pipes\ValidateCouponPipe;
use App\Plugins\OrderManagement\src\Contracts\OrderPipelineRegistrar;

final class CouponsPromotionsPlugin extends AbstractTenantPlugin
{
    public function bootForTenant(Tenant $tenant): void
    {
        $registry = app(OrderPipelineRegistrar::class);
        $registry->registerValidationPipe(ValidateCouponPipe::class, 350);
        $registry->registerPricingPipe(ApplyCouponDiscountPipe::class, 200);
    }
}
