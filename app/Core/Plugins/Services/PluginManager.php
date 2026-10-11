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
use Illuminate\Support\Collection;
use RuntimeException;

final class PluginManager
{
    /**
     * @var array<string, array<string, TenantPluginInterface>>
     */
    private array $bootedTenantPlugins = [];

    public function __construct(
        private readonly Application $app,
        private readonly PluginRegistry $registry,
        private readonly PluginDependencyResolver $resolver
    ) {}

    /**
     * Boot all active plugins for the given tenant in topological dependency order.
     */
    public function bootTenant(Tenant $tenant, bool $force = false): void
    {
        $tenantId = (string) $tenant->id;

        if (! $force && isset($this->bootedTenantPlugins[$tenantId])) {
            return;
        }

        $activeRecords = TenantPlugin::query()
            ->where('tenant_id', $tenantId)
            ->where('status', PluginStatus::ACTIVE)
            ->get();

        $activeManifests = collect();

        foreach ($activeRecords as $record) {
            $manifest = $this->registry->get($record->plugin_id);
            if ($manifest !== null) {
                $activeManifests->put($manifest->id, $manifest);
            }
        }

        // Topologically sort so dependencies boot first
        $sortedManifests = $this->resolver->sort($activeManifests);

        $booted = [];

        foreach ($sortedManifests as $manifest) {
            // 1. Register Service Providers
            foreach ($manifest->providers as $providerClass) {
                if (class_exists($providerClass)) {
                    $this->app->register($providerClass);
                }
            }

            // 2. Instantiate and boot plugin contract if defined
            $pluginInstance = $this->resolvePluginInstance($manifest);
            if ($pluginInstance !== null) {
                $pluginInstance->bootForTenant($tenant);
                $booted[$manifest->id] = $pluginInstance;
            }
        }

        $this->bootedTenantPlugins[$tenantId] = $booted;
    }

    public function resetTenant(Tenant $tenant): void
    {
        unset($this->bootedTenantPlugins[(string) $tenant->id]);
    }

    public function isPluginActive(string $pluginId, ?Tenant $tenant = null): bool
    {
        $tenant = $tenant ?? (function_exists('tenant') ? tenant() : null);

        if (! $tenant) {
            return false;
        }

        $tenantId = (string) $tenant->id;

        if (isset($this->bootedTenantPlugins[$tenantId])) {
            return isset($this->bootedTenantPlugins[$tenantId][$pluginId]);
        }

        return TenantPlugin::query()
            ->where('tenant_id', $tenantId)
            ->where('plugin_id', $pluginId)
            ->where('status', PluginStatus::ACTIVE)
            ->exists();
    }

    /**
     * @return array<string>
     */
    public function getActivePluginIds(?Tenant $tenant = null): array
    {
        $tenant = $tenant ?? (function_exists('tenant') ? tenant() : null);

        if (! $tenant) {
            return [];
        }

        $tenantId = (string) $tenant->id;

        if (isset($this->bootedTenantPlugins[$tenantId])) {
            return array_keys($this->bootedTenantPlugins[$tenantId]);
        }

        return TenantPlugin::query()
            ->where('tenant_id', $tenantId)
            ->where('status', PluginStatus::ACTIVE)
            ->pluck('plugin_id')
            ->all();
    }

    /**
     * Resolve active Filament plugins for tenant panel injection.
     *
     * @return array<FilamentPluginContract>
     */
    public function getActiveFilamentPluginsForTenant(Tenant $tenant): array
    {
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

    private function resolvePluginInstance(PluginManifest $manifest): ?TenantPluginInterface
    {
        $studlyName = str_replace('-', '', ucwords($manifest->id, '-'));
        $pluginClass = "App\\Plugins\\{$studlyName}\\{$studlyName}Plugin";

        if (class_exists($pluginClass) && is_subclass_of($pluginClass, TenantPluginInterface::class)) {
            return $this->app->make($pluginClass, ['manifest' => $manifest]);
        }

        return null;
    }

}
