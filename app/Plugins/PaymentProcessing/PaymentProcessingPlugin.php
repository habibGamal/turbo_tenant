<?php

declare(strict_types=1);

namespace App\Plugins\PaymentProcessing;

use App\Core\Plugins\AbstractTenantPlugin;
use App\Models\Tenant;
use App\Plugins\OrderManagement\src\Pipelines\OrderPipelineRegistry;
use App\Plugins\PaymentProcessing\src\Pipelines\Pipes\ApplyCodFeePipe;

final class PaymentProcessingPlugin extends AbstractTenantPlugin
{
    public function bootForTenant(Tenant $tenant): void
    {
        if (app()->bound(OrderPipelineRegistry::class)) {
            app(OrderPipelineRegistry::class)->registerPricingPipe(ApplyCodFeePipe::class, 450);
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
