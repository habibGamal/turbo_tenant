<?php

declare(strict_types=1);

namespace App\Plugins\GuestCheckout;

use App\Core\Plugins\AbstractTenantPlugin;
use App\Models\Tenant;
use App\Plugins\GuestCheckout\src\Pipelines\Pipes\GuestCheckoutPermissionPipe;
use App\Plugins\OrderManagement\src\Contracts\OrderPipelineRegistrar;

final class GuestCheckoutPlugin extends AbstractTenantPlugin
{
    public function bootForTenant(Tenant $tenant): void
    {
        app(OrderPipelineRegistrar::class)->registerValidationPipe(GuestCheckoutPermissionPipe::class, 325);
    }
}
