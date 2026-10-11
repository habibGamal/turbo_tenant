<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Plugins\Services\PluginLifecycleService;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Throwable;

final class TenantPluginDeactivateCommand extends Command
{
    protected $signature = 'tenant:plugin:deactivate {tenant : The ID of the tenant} {plugin : The ID of the plugin}';

    protected $description = 'Deactivate a plugin for a specific tenant';

    public function handle(PluginLifecycleService $lifecycle): int
    {
        $tenantId = (string) $this->argument('tenant');
        $pluginId = (string) $this->argument('plugin');

        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            $this->error("Tenant '{$tenantId}' not found.");

            return self::FAILURE;
        }

        $this->info("Deactivating plugin '{$pluginId}' for tenant '{$tenantId}'...");

        try {
            $lifecycle->deactivate($tenant, $pluginId);
            $this->info("Plugin '{$pluginId}' successfully deactivated for tenant '{$tenantId}'.");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Failed to deactivate plugin: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
