<?php

declare(strict_types=1);

namespace App\Plugins\PaymentProcessing;

use App\Core\Plugins\AbstractTenantPlugin;
use App\Models\Tenant;
use App\Plugins\OrderManagement\src\Contracts\OrderPipelineRegistrar;
use App\Plugins\PaymentProcessing\src\Pipelines\Pipes\ApplyCodFeePipe;

final class PaymentProcessingPlugin extends AbstractTenantPlugin
{
    public function bootForTenant(Tenant $tenant): void
    {
        app(OrderPipelineRegistrar::class)->registerPricingPipe(ApplyCodFeePipe::class, 450);
    }
}
