<?php

declare(strict_types=1);

namespace App\Plugins\ComboPackages;

use App\Core\Plugins\AbstractTenantPlugin;
use App\Models\Tenant;

final class ComboPackagesPlugin extends AbstractTenantPlugin
{
    public function bootForTenant(Tenant $tenant): void
    {
        // Runtime tenant initialization
    }

    public function onActivate(Tenant $tenant): void
    {
        // Post-activation setup
    }

    public function onDeactivate(Tenant $tenant): void
    {
        // Deactivation cleanup
    }

    public function onUninstall(Tenant $tenant): void
    {
        // Pre-purge cleanup
    }
}
