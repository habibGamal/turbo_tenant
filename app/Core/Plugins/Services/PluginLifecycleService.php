<?php

declare(strict_types=1);

namespace App\Core\Plugins\Services;

use App\Core\Plugins\Enums\PluginStatus;
use App\Models\Tenant;
use App\Models\TenantPlugin;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

final class PluginLifecycleService
{
    public function __construct(
        private readonly PluginRegistry $registry,
        private readonly PluginDependencyResolver $resolver,
        private readonly PluginManager $pluginManager
    ) {}

    /**
     * Activate a plugin for a specific tenant.
     *
     * @param  array<string, mixed>  $settings
     */
    public function activate(Tenant $tenant, string $pluginId, array $settings = []): void
    {
        $manifest = $this->registry->get($pluginId);

        if ($manifest === null) {
            throw new InvalidArgumentException("Plugin '{$pluginId}' is not registered");
        }

        $activeIds = $this->pluginManager->getActivePluginIds($tenant);

        // 1. Check dependencies
        $missing = $this->resolver->getMissingDependencies($manifest, $activeIds);
        if (! empty($missing)) {
            $missingStr = implode(', ', $missing);
            throw new RuntimeException("Cannot activate '{$pluginId}'. Missing active dependencies: {$missingStr}");
        }

        // 2. Run tenant-scoped migrations if present
        if ($manifest->tenantMigrationsPath !== null) {
            $tenant->run(function () use ($manifest) {
                Artisan::call('migrate', [
                    '--path' => $manifest->tenantMigrationsPath,
                    '--realpath' => true,
                    '--force' => true,
                ]);
            });
        }

        // 3. Provision tenant-scoped storage directory
        $tenantStoragePath = "plugins/{$manifest->id}";
        if (! Storage::exists($tenantStoragePath)) {
            Storage::makeDirectory($tenantStoragePath);
        }

        // 4. Update tenant plugin state
        TenantPlugin::updateOrCreate(
            [
                'tenant_id' => (string) $tenant->id,
                'plugin_id' => $manifest->id,
            ],
            [
                'status' => PluginStatus::ACTIVE,
                'settings' => $settings,
                'activated_at' => now(),
                'installed_at' => now(),
            ]
        );

        $this->pluginManager->resetTenant($tenant);
    }


    /**
     * Deactivate a plugin for a specific tenant (preserves DB tables and files).
     */
    public function deactivate(Tenant $tenant, string $pluginId): void
    {
        $targetManifest = $this->registry->get($pluginId);
        if ($targetManifest !== null && $targetManifest->isCore) {
            throw new RuntimeException("Plugin '{$pluginId}' is a mandatory core plugin and cannot be deactivated or uninstalled.");
        }

        $activeIds = $this->pluginManager->getActivePluginIds($tenant);

        // Check if any active plugin depends on this one
        foreach ($activeIds as $activeId) {
            if ($activeId === $pluginId) {
                continue;
            }

            $manifest = $this->registry->get($activeId);
            if ($manifest !== null && in_array($pluginId, $manifest->dependencies, true)) {
                throw new RuntimeException("Cannot deactivate '{$pluginId}'. Plugin '{$activeId}' depends on it.");
            }
        }

        $record = TenantPlugin::query()
            ->where('tenant_id', (string) $tenant->id)
            ->where('plugin_id', $pluginId)
            ->first();

        if ($record !== null) {
            $record->update(['status' => PluginStatus::DEACTIVATED]);
        }

        $this->pluginManager->resetTenant($tenant);
    }

    /**
     * Uninstall a plugin for a specific tenant (rolls back migrations and purges files).
     */
    public function uninstall(Tenant $tenant, string $pluginId): void
    {
        $this->deactivate($tenant, $pluginId);

        $manifest = $this->registry->get($pluginId);

        if ($manifest?->tenantMigrationsPath !== null) {
            $tenant->run(function () use ($manifest) {
                Artisan::call('migrate:rollback', [
                    '--path' => $manifest->tenantMigrationsPath,
                    '--realpath' => true,
                    '--force' => true,
                ]);
            });
        }

        // Purge tenant plugin storage
        $tenantStoragePath = "plugins/{$pluginId}";
        if (Storage::exists($tenantStoragePath)) {
            Storage::deleteDirectory($tenantStoragePath);
        }

        TenantPlugin::query()
            ->where('tenant_id', (string) $tenant->id)
            ->where('plugin_id', $pluginId)
            ->delete();

        $this->pluginManager->resetTenant($tenant);
    }

}
