<?php

declare(strict_types=1);

namespace App\Core\Plugins\Services;

use App\Core\Plugins\Enums\PluginStatus;
use App\Models\Plugin;
use App\Models\Tenant;
use App\Models\TenantPlugin;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

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

        $existingRecord = TenantPlugin::query()
            ->where('tenant_id', (string) $tenant->id)
            ->where('plugin_id', $manifest->id)
            ->first();

        if ($existingRecord?->isActive()) {
            return;
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
        $resolvedSettings = ! empty($settings) ? $settings : ($existingRecord !== null ? $existingRecord->settings : []);
        $installedAt = $existingRecord !== null ? $existingRecord->installed_at : now();
        $recordExisted = $existingRecord !== null;
        $previousStatus = $existingRecord !== null ? $existingRecord->status : null;

        // Ensure plugin exists in central catalog before attaching to tenant
        Plugin::firstOrCreate(
            ['id' => $manifest->id],
            [
                'name' => $manifest->name,
                'version' => $manifest->version,
                'category' => $manifest->category,
                'description' => $manifest->description,
                'author' => $manifest->author,
                'is_core' => $manifest->isCore,
                'dependencies' => $manifest->dependencies,
            ]
        );

        TenantPlugin::updateOrCreate(
            [
                'tenant_id' => (string) $tenant->id,
                'plugin_id' => $manifest->id,
            ],
            [
                'status' => PluginStatus::ACTIVE,
                'settings' => $resolvedSettings,
                'activated_at' => now(),
                'installed_at' => $installedAt,
            ]
        );

        $this->pluginManager->resetTenant($tenant);

        // 5. Call onActivate hook with compensation on failure
        $pluginInstance = $this->pluginManager->resolvePluginInstance($manifest);
        if ($pluginInstance !== null) {
            try {
                $pluginInstance->onActivate($tenant);
            } catch (Throwable $e) {
                // Compensate: restore previous record state if onActivate throws.
                // Migrations are not rolled back (different connection); tenant schema changes remain.
                if (! $recordExisted) {
                    TenantPlugin::query()
                        ->where('tenant_id', (string) $tenant->id)
                        ->where('plugin_id', $manifest->id)
                        ->delete();
                } else {
                    TenantPlugin::query()
                        ->where('tenant_id', (string) $tenant->id)
                        ->where('plugin_id', $manifest->id)
                        ->update([
                            'status' => $previousStatus,
                        ]);
                }

                $this->pluginManager->resetTenant($tenant);

                throw $e;
            }
        }
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
        $dependents = $this->resolver->getDependents($pluginId, $activeIds, $this->registry->all());
        if (! empty($dependents)) {
            $dependentId = $dependents[0];
            throw new RuntimeException("Cannot deactivate '{$pluginId}'. Plugin '{$dependentId}' depends on it.");
        }

        $record = TenantPlugin::query()
            ->where('tenant_id', (string) $tenant->id)
            ->where('plugin_id', $pluginId)
            ->first();

        if ($record !== null) {
            $record->update(['status' => PluginStatus::DEACTIVATED]);
        }

        $this->pluginManager->resetTenant($tenant);

        if ($targetManifest !== null) {
            $pluginInstance = $this->pluginManager->resolvePluginInstance($targetManifest);
            $pluginInstance?->onDeactivate($tenant);
        }
    }

    /**
     * Uninstall a plugin for a specific tenant (rolls back migrations and purges files).
     */
    public function uninstall(Tenant $tenant, string $pluginId): void
    {
        $targetManifest = $this->registry->get($pluginId);
        if ($targetManifest !== null && $targetManifest->isCore) {
            throw new RuntimeException("Plugin '{$pluginId}' is a mandatory core plugin and cannot be deactivated or uninstalled.");
        }

        if ($this->pluginManager->isPluginActive($pluginId, $tenant)) {
            $this->deactivate($tenant, $pluginId);
        }

        if ($targetManifest !== null) {
            $pluginInstance = $this->pluginManager->resolvePluginInstance($targetManifest);
            $pluginInstance?->onUninstall($tenant);
        }

        if ($targetManifest?->tenantMigrationsPath !== null) {
            $tenant->run(function () use ($targetManifest) {
                Artisan::call('migrate:reset', [
                    '--path' => $targetManifest->tenantMigrationsPath,
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
