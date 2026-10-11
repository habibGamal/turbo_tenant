<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Plugins\Services\PluginLifecycleService;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Throwable;

final class TenantPluginActivateCommand extends Command
{
    protected $signature = 'tenant:plugin:activate {tenant : The ID of the tenant} {plugin : The ID of the plugin}';

    protected $description = 'Activate and migrate a plugin for a specific tenant';

    public function handle(PluginLifecycleService $lifecycle): int
    {
        $tenantId = (string) $this->argument('tenant');
        $pluginId = (string) $this->argument('plugin');

        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            $this->error("Tenant '{$tenantId}' not found.");

            return self::FAILURE;
        }

        $this->info("Activating plugin '{$pluginId}' for tenant '{$tenantId}'...");

        try {
            $lifecycle->activate($tenant, $pluginId);
            $this->info("Plugin '{$pluginId}' successfully activated and migrated for tenant '{$tenantId}'.");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Failed to activate plugin: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
