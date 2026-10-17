<?php

declare(strict_types=1);

use App\Core\Plugins\Enums\PluginStatus;
use App\Core\Plugins\Services\PluginDependencyResolver;
use App\Core\Plugins\Services\PluginLifecycleService;
use App\Core\Plugins\Services\PluginManager;
use App\Core\Plugins\Services\PluginRegistry;
use App\Models\Plugin;
use App\Models\Tenant;
use App\Models\TenantPlugin;
use App\Plugins\CatalogProducts\src\Filament\CatalogProductsFilamentPlugin;
use App\Plugins\LocationsDelivery\src\Filament\LocationsDeliveryFilamentPlugin;
use App\Plugins\StoreSettings\src\Filament\StoreSettingsFilamentPlugin;
use App\Plugins\StoreSettings\src\Services\SettingService;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    Artisan::call('migrate', [
        '--path' => 'database/migrations',
        '--force' => true,
    ]);

    Tenant::withoutEvents(fn () => Tenant::firstOrCreate(['id' => 'test_suite_tenant']));

    $registry = app(PluginRegistry::class);
    $registry->discover();
    $registry->syncDatabase();
});

it('identifies store-settings as a core plugin and prevents deactivation', function () {
    $tenant = static::$testTenant;
    $lifecycle = app(PluginLifecycleService::class);

    $manifest = app(PluginRegistry::class)->get('store-settings');

    expect($manifest)->not->toBeNull()
        ->and($manifest->isCore)->toBeTrue();

    $lifecycle->activate($tenant, 'store-settings');

    expect(fn () => $lifecycle->deactivate($tenant, 'store-settings'))
        ->toThrow(RuntimeException::class, "Plugin 'store-settings' is a mandatory core plugin and cannot be deactivated or uninstalled.");
});

it('enforces dependency requirements for locations-delivery and catalog-products', function () {
    $tenant = static::$testTenant;
    $lifecycle = app(PluginLifecycleService::class);

    // If store-settings is not active, activating locations-delivery must fail
    expect(fn () => $lifecycle->activate($tenant, 'locations-delivery'))
        ->toThrow(RuntimeException::class, "Missing active dependencies: store-settings");

    // Once store-settings is active, locations-delivery activates cleanly
    $lifecycle->activate($tenant, 'store-settings');
    $lifecycle->activate($tenant, 'locations-delivery');

    $record = TenantPlugin::where('tenant_id', (string) $tenant->id)
        ->where('plugin_id', 'locations-delivery')
        ->first();

    expect($record)->not->toBeNull()
        ->and($record->status)->toBe(PluginStatus::ACTIVE);
});

it('sorts Phase 1 plugins in correct topological dependency order', function () {
    $registry = app(PluginRegistry::class);
    $resolver = app(PluginDependencyResolver::class);

    $manifests = collect([
        'catalog-products' => $registry->get('catalog-products'),
        'locations-delivery' => $registry->get('locations-delivery'),
        'store-settings' => $registry->get('store-settings'),
    ]);

    $sorted = $resolver->sort($manifests);
    $keys = array_keys($sorted);

    // store-settings must be positioned before both locations-delivery and catalog-products
    $storeSettingsIndex = array_search('store-settings', $keys, true);
    $locationsIndex = array_search('locations-delivery', $keys, true);
    $catalogIndex = array_search('catalog-products', $keys, true);

    expect($storeSettingsIndex)->toBeLessThan($locationsIndex)
        ->and($storeSettingsIndex)->toBeLessThan($catalogIndex);
});

it('boots active Phase 1 plugins and returns their Filament panel plugins', function () {
    $tenant = static::$testTenant;
    $lifecycle = app(PluginLifecycleService::class);
    $manager = app(PluginManager::class);

    $lifecycle->activate($tenant, 'store-settings');
    $lifecycle->activate($tenant, 'locations-delivery');
    $lifecycle->activate($tenant, 'catalog-products');

    $manager->bootTenant($tenant);

    expect(app()->bound(SettingService::class))->toBeTrue();

    $filamentPlugins = $manager->getActiveFilamentPluginsForTenant($tenant);
    $classes = array_map(fn ($p) => get_class($p), $filamentPlugins);

    expect($classes)->toContain(StoreSettingsFilamentPlugin::class)
        ->and($classes)->toContain(LocationsDeliveryFilamentPlugin::class)
        ->and($classes)->toContain(CatalogProductsFilamentPlugin::class);
});
