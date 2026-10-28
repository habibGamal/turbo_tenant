<?php

declare(strict_types=1);

namespace App\Plugins\MediaOptimizer;

use App\Core\Plugins\AbstractTenantPlugin;
use App\Models\Tenant;

final class MediaOptimizerPlugin extends AbstractTenantPlugin
{
    public function bootForTenant(Tenant $tenant): void
    {
        // Core foundation plugin hooks for active tenant if needed
    }

    public function onActivate(Tenant $tenant): void
    {
        // Default configurations or tenant directories
    }

    public function onDeactivate(Tenant $tenant): void
    {
        // Cleanup if deactivated
    }

    public function onUninstall(Tenant $tenant): void
    {
        // Cleanup if uninstalled
    }
}
