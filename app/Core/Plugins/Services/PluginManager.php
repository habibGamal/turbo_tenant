<?php

declare(strict_types=1);

namespace App\Core\Plugins\Services;

use App\Core\Plugins\Contracts\TenantPluginInterface;
use App\Core\Plugins\DTOs\PluginManifest;
use App\Core\Plugins\Enums\PluginStatus;
use App\Models\Tenant;
use App\Models\TenantPlugin;
use Filament\Contracts\Plugin as FilamentPluginContract;
use Illuminate\Contracts\Foundation\Application;
use RuntimeException;

final class PluginManager
{
    /**
     * Memoized active plugin IDs per tenant ID.
     *
     * @var array<string, list<string>>
     */
    private array $activePluginIdsByTenant = [];

    /**
     * Booted plugin instances per tenant ID.
     *
     * @var array<string, array<string, TenantPluginInterface>>
     */
    private array $bootedPluginInstancesByTenant = [];

    public function __construct(
        private readonly Application $app,
        private readonly PluginRegistry $registry,
        private readonly PluginDependencyResolver $resolver
    ) {}

    /**
     * Boot all active plugins for the given tenant in topological dependency order.
     */
    public function bootTenant(Tenant $tenant): void
    {
        $tenantId = (string) $tenant->id;

        $this->resetTenantScopedInstances();

        $activeIds = $this->getActivePluginIds($tenant);

        $activeManifests = collect();

        foreach ($activeIds as $pluginId) {
            $manifest = $this->registry->get($pluginId);
            if ($manifest !== null) {
                $activeManifests->put($manifest->id, $manifest);
            }
        }

        // Topologically sort so dependencies boot first
        $sortedManifests = $this->resolver->sort($activeManifests);

        $bootedInstances = [];

        foreach ($sortedManifests as $manifest) {
            // Instantiate and boot plugin contract if defined
            $pluginInstance = $this->resolvePluginInstance($manifest);
            if ($pluginInstance !== null) {
                $pluginInstance->bootForTenant($tenant);
                $bootedInstances[$manifest->id] = $pluginInstance;
            }
        }

        $this->bootedPluginInstancesByTenant[$tenantId] = $bootedInstances;
    }

    /**
     * Forget container singletons declared as tenant-scoped across all plugins.
     */
    public function resetTenantScopedInstances(): void
    {
        foreach ($this->registry->all() as $manifest) {
            foreach ($manifest->tenantScoped as $abstract) {
                $this->app->forgetInstance($abstract);
            }
        }
    }

    public function resetTenant(?Tenant $tenant = null): void
    {
        $this->resetTenantScopedInstances();

        if ($tenant !== null) {
            $tenantId = (string) $tenant->id;
            unset($this->activePluginIdsByTenant[$tenantId]);
            unset($this->bootedPluginInstancesByTenant[$tenantId]);
        } else {
            $this->activePluginIdsByTenant = [];
            $this->bootedPluginInstancesByTenant = [];
        }
    }

    public function onTenancyEnded(): void
    {
        $this->resetTenant();
    }

    public function isPluginActive(string $pluginId, ?Tenant $tenant = null): bool
    {
        $tenant = $tenant ?? tenant();

        if (! $tenant) {
            return false;
        }

        return in_array($pluginId, $this->getActivePluginIds($tenant), true);
    }

    /**
     * @return list<string>
     */
    public function getActivePluginIds(?Tenant $tenant = null): array
    {
        $tenant = $tenant ?? tenant();

        if (! $tenant) {
            return [];
        }

        $tenantId = (string) $tenant->id;

        if (! isset($this->activePluginIdsByTenant[$tenantId])) {
            $this->activePluginIdsByTenant[$tenantId] = TenantPlugin::query()
                ->where('tenant_id', $tenantId)
                ->where('status', PluginStatus::ACTIVE)
                ->pluck('plugin_id')
                ->map(fn ($id) => (string) $id)
                ->values()
                ->all();
        }

        return $this->activePluginIdsByTenant[$tenantId];
    }

    /**
     * Resolve active Filament plugins for tenant panel injection.
     *
     * @return array<FilamentPluginContract>
     */
    public function getActiveFilamentPluginsForTenant(?Tenant $tenant = null): array
    {
        $tenant = $tenant ?? tenant();

        if (! $tenant) {
            return [];
        }

        $activeIds = $this->getActivePluginIds($tenant);
        $filamentPlugins = [];

        foreach ($activeIds as $pluginId) {
            $manifest = $this->registry->get($pluginId);

            if ($manifest?->filamentPlugin && class_exists($manifest->filamentPlugin)) {
                $class = $manifest->filamentPlugin;
                if (is_subclass_of($class, FilamentPluginContract::class)) {
                    $plugin = method_exists($class, 'make') ? $class::make() : new $class();
                    $filamentPlugins[] = $plugin;
                }
            }
        }

        return $filamentPlugins;
    }

    public function resolvePluginInstance(PluginManifest $manifest): ?TenantPluginInterface
    {
        if ($manifest->pluginClass === null) {
            return null;
        }

        $pluginClass = $manifest->pluginClass;

        if (! class_exists($pluginClass)) {
            throw new RuntimeException("Plugin class '{$pluginClass}' specified in manifest for '{$manifest->id}' does not exist.");
        }

        if (! is_subclass_of($pluginClass, TenantPluginInterface::class)) {
            throw new RuntimeException("Plugin class '{$pluginClass}' specified in manifest for '{$manifest->id}' must implement ".TenantPluginInterface::class.'.');
        }

        if ($this->app->bound($pluginClass) && $this->app->isShared($pluginClass)) {
            return $this->app->make($pluginClass);
        }

        return $this->app->make($pluginClass, ['manifest' => $manifest]);
    }

    public function resolvePluginInstanceById(string $pluginId): ?TenantPluginInterface
    {
        $manifest = $this->registry->get($pluginId);

        return $manifest !== null ? $this->resolvePluginInstance($manifest) : null;
    }

    public function getBootedPluginInstance(string $pluginId, ?Tenant $tenant = null): ?TenantPluginInterface
    {
        $tenant = $tenant ?? tenant();

        if (! $tenant) {
            return null;
        }

        return $this->bootedPluginInstancesByTenant[(string) $tenant->id][$pluginId] ?? null;
    }

    /**
     * @return array<string, TenantPluginInterface>
     */
    public function getBootedPluginInstances(?Tenant $tenant = null): array
    {
        $tenant = $tenant ?? tenant();

        if (! $tenant) {
            return [];
        }

        return $this->bootedPluginInstancesByTenant[(string) $tenant->id] ?? [];
    }
}
