<?php

declare(strict_types=1);

namespace App\Plugins\ComboPackages;

use App\Core\Plugins\AbstractTenantPlugin;
use App\Models\Tenant;
use App\Plugins\ComboPackages\src\Pipelines\ValidatePackageLinesPipe;
use App\Plugins\OrderManagement\src\Contracts\OrderPipelineRegistrar;

final class ComboPackagesPlugin extends AbstractTenantPlugin
{
    public function bootForTenant(Tenant $tenant): void
    {
        app(OrderPipelineRegistrar::class)->registerValidationPipe(ValidatePackageLinesPipe::class, 210);
    }
}
