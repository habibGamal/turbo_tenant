<?php

declare(strict_types=1);

use App\Core\Plugins\Enums\PluginStatus;
use App\Core\Plugins\Services\PluginDependencyResolver;
use App\Core\Plugins\Services\PluginLifecycleService;
use App\Core\Plugins\Services\PluginManager;
use App\Core\Plugins\Services\PluginRegistry;
use App\Models\Plugin;
use App\Models\TenantPlugin;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Artisan::call('migrate', [
        '--path' => 'database/migrations',
        '--force' => true,
    ]);

    App\Models\Tenant::withoutEvents(fn () => App\Models\Tenant::firstOrCreate(['id' => 'test_suite_tenant']));
    app(PluginRegistry::class)->discover()->syncDatabase();
});

it('discovers sample-greeting plugin from disk and syncs with database', function () {
    $registry = app(PluginRegistry::class);
    $registry->discover();

    expect($registry->has('sample-greeting'))->toBeTrue();

    $registry->syncDatabase();

    expect(Plugin::find('sample-greeting'))->not->toBeNull()
        ->and(Plugin::find('sample-greeting')->name)->toBe('Sample Greeting Feature');
});

it('resolves dependency topology correctly', function () {
    $registry = app(PluginRegistry::class);
    $resolver = app(PluginDependencyResolver::class);

    $manifests = $registry->all();
    $sorted = $resolver->sort($manifests);

    expect(array_keys($sorted))->toContain('sample-greeting');
});

it('activates and migrates sample-greeting for tenant', function () {
    $tenant = static::$testTenant;
    $lifecycle = app(PluginLifecycleService::class);
    $registry = app(PluginRegistry::class);

    $registry->syncDatabase();
    $lifecycle->activate($tenant, 'sample-greeting');

    $record = TenantPlugin::where('tenant_id', (string) $tenant->id)
        ->where('plugin_id', 'sample-greeting')
        ->first();

    expect($record)->not->toBeNull()
        ->and($record->status)->toBe(PluginStatus::ACTIVE);

    // Verify tenant table was migrated
    $hasTable = $tenant->run(fn () => Schema::hasTable('sample_greetings'));
    expect($hasTable)->toBeTrue();
});

it('boots active plugins into service container and config for tenant', function () {
    $tenant = static::$testTenant;
    $lifecycle = app(PluginLifecycleService::class);
    $registry = app(PluginRegistry::class);
    $manager = app(PluginManager::class);

    $registry->syncDatabase();
    $lifecycle->activate($tenant, 'sample-greeting');

    $manager->bootTenant($tenant);

    expect(app()->bound('sample-greeting.message'))->toBeTrue()
        ->and(app('sample-greeting.message'))->toBe('Hello from SampleGreetingPlugin!')
        ->and(config('sample-greeting.active_tenant'))->toBe((string) $tenant->id);
});

it('deactivates and uninstalls sample-greeting cleanly from tenant', function () {
    $tenant = static::$testTenant;
    $lifecycle = app(PluginLifecycleService::class);
    $registry = app(PluginRegistry::class);

    $registry->syncDatabase();
    $lifecycle->activate($tenant, 'sample-greeting');

    // Deactivate preserves table
    $lifecycle->deactivate($tenant, 'sample-greeting');
    $record = TenantPlugin::where('tenant_id', (string) $tenant->id)
        ->where('plugin_id', 'sample-greeting')
        ->first();
    expect($record->status)->toBe(PluginStatus::DEACTIVATED);

    $hasTable = $tenant->run(fn () => Schema::hasTable('sample_greetings'));
    expect($hasTable)->toBeTrue();

    // Uninstall rolls back table and deletes pivot
    $lifecycle->uninstall($tenant, 'sample-greeting');

    expect(TenantPlugin::where('tenant_id', (string) $tenant->id)
        ->where('plugin_id', 'sample-greeting')
        ->exists())->toBeFalse();

    $hasTableAfterUninstall = $tenant->run(fn () => Schema::hasTable('sample_greetings'));
    expect($hasTableAfterUninstall)->toBeFalse();
});

it('prevents deactivating or uninstalling core plugins', function () {
    $tenant = static::$testTenant;
    $lifecycle = app(PluginLifecycleService::class);
    $registry = app(PluginRegistry::class);

    Plugin::create([
        'id' => 'core-test',
        'name' => 'Core Test Plugin',
        'version' => '1.0.0',
        'category' => App\Core\Plugins\Enums\PluginCategory::FOUNDATION,
        'is_core' => true,
    ]);

    $coreManifest = new App\Core\Plugins\DTOs\PluginManifest(
        id: 'core-test',
        name: 'Core Test Plugin',
        version: '1.0.0',
        category: App\Core\Plugins\Enums\PluginCategory::FOUNDATION,
        basePath: 'app/Plugins/CoreTest',
        isCore: true
    );

    $registry->all()->put('core-test', $coreManifest);
    $lifecycle->activate($tenant, 'core-test');

    expect(fn () => $lifecycle->deactivate($tenant, 'core-test'))
        ->toThrow(RuntimeException::class, "Plugin 'core-test' is a mandatory core plugin and cannot be deactivated or uninstalled.");
});

it('discovers plugins, writes manifest cache, and loads from cache, then clears cache', function () {
    $cachePath = app()->bootstrapPath('cache/plugins.php');
    if (file_exists($cachePath)) {
        unlink($cachePath);
    }

    try {
        Artisan::call('plugin:discover');
        expect(file_exists($cachePath))->toBeTrue();

        $cachedData = require $cachePath;
        expect($cachedData)->toBeArray()
            ->and($cachedData)->toHaveKey('sample-greeting')
            ->and($cachedData['sample-greeting'])->toHaveKey('basePath')
            ->and($cachedData['sample-greeting'])->toHaveKey('plugin');

        // Test loading from cache
        $loader = app(App\Core\Plugins\Services\PluginManifestLoader::class);
        $cachedRegistry = new PluginRegistry($loader, app_path('Plugins'), $cachePath);
        expect($cachedRegistry->hasCache())->toBeTrue();

        $manifests = $cachedRegistry->all();
        expect($manifests->has('sample-greeting'))->toBeTrue()
            ->and($manifests->get('sample-greeting')->pluginClass)->toBe('App\\Plugins\\SampleGreeting\\SampleGreetingPlugin');

        // Clear cache via command
        Artisan::call('plugin:clear');
        expect(file_exists($cachePath))->toBeFalse()
            ->and($cachedRegistry->hasCache())->toBeFalse();
    } finally {
        if (file_exists($cachePath)) {
            unlink($cachePath);
        }
    }
});

it('memoizes active plugin IDs per tenant until resetTenant is called', function () {
    $tenant = tenant();
    $lifecycle = app(PluginLifecycleService::class);
    $manager = app(PluginManager::class);

    $lifecycle->activate($tenant, 'sample-greeting');

    // First call queries DB and memoizes
    expect($manager->isPluginActive('sample-greeting', $tenant))->toBeTrue()
        ->and($manager->getActivePluginIds($tenant))->toContain('sample-greeting');

    // Modify DB directly without calling lifecycle/resetTenant
    TenantPlugin::where('tenant_id', (string) $tenant->id)
        ->where('plugin_id', 'sample-greeting')
        ->delete();

    // Still true because active ids are memoized per tenant
    expect($manager->isPluginActive('sample-greeting', $tenant))->toBeTrue()
        ->and($manager->getActivePluginIds($tenant))->toContain('sample-greeting');

    // Invalidate memoization
    $manager->resetTenant($tenant);

    // Now it re-queries DB and sees the record is gone
    expect($manager->isPluginActive('sample-greeting', $tenant))->toBeFalse()
        ->and($manager->getActivePluginIds($tenant))->not->toContain('sample-greeting');
});

it('stores separate arrays for active ids and booted instances on bootTenant', function () {
    $tenant = tenant();
    $lifecycle = app(PluginLifecycleService::class);
    $manager = app(PluginManager::class);

    $lifecycle->activate($tenant, 'sample-greeting');
    $manager->bootTenant($tenant);

    $activeIds = $manager->getActivePluginIds($tenant);
    expect($activeIds)->toBeArray()
        ->and($activeIds)->toContain('sample-greeting');
    foreach ($activeIds as $id) {
        expect($id)->toBeString();
    }

    $bootedInstance = $manager->getBootedPluginInstance('sample-greeting', $tenant);
    expect($bootedInstance)->not->toBeNull()
        ->and($bootedInstance)->toBeInstanceOf(App\Core\Plugins\Contracts\TenantPluginInterface::class);

    $bootedInstances = $manager->getBootedPluginInstances($tenant);
    expect($bootedInstances)->toBeArray()
        ->and($bootedInstances)->toHaveKey('sample-greeting')
        ->and($bootedInstances['sample-greeting'])->toBeInstanceOf(App\Core\Plugins\Contracts\TenantPluginInterface::class);
});

it('resolves active dependents using getDependents and blocks deactivation', function () {
    $tenant = tenant();
    $resolver = app(PluginDependencyResolver::class);
    $lifecycle = app(PluginLifecycleService::class);
    $registry = app(PluginRegistry::class);

    $manifests = $registry->all();
    // In our manifests, order-management has dependencies like cart-engine, locations-delivery
    $dependents = $resolver->getDependents('locations-delivery', ['order-management', 'cart-engine'], $manifests);
    expect($dependents)->toContain('order-management')
        ->and($dependents)->not->toContain('cart-engine');

    // If dependent plugin is NOT in active list, it is not returned
    $dependentsInactive = $resolver->getDependents('locations-delivery', ['cart-engine'], $manifests);
    expect($dependentsInactive)->toBeEmpty();

    // Register synthetic plugins to test lifecycle deactivation blocking
    Plugin::create([
        'id' => 'dep-parent',
        'name' => 'Parent Plugin',
        'version' => '1.0.0',
        'category' => App\Core\Plugins\Enums\PluginCategory::ADDON,
    ]);
    Plugin::create([
        'id' => 'dep-child',
        'name' => 'Child Plugin',
        'version' => '1.0.0',
        'category' => App\Core\Plugins\Enums\PluginCategory::ADDON,
        'dependencies' => ['dep-parent'],
    ]);

    $parentManifest = new App\Core\Plugins\DTOs\PluginManifest(
        id: 'dep-parent',
        name: 'Parent Plugin',
        version: '1.0.0',
        category: App\Core\Plugins\Enums\PluginCategory::ADDON,
        basePath: 'app/Plugins/DepParent',
    );
    $childManifest = new App\Core\Plugins\DTOs\PluginManifest(
        id: 'dep-child',
        name: 'Child Plugin',
        version: '1.0.0',
        category: App\Core\Plugins\Enums\PluginCategory::ADDON,
        basePath: 'app/Plugins/DepChild',
        dependencies: ['dep-parent'],
    );

    $registry->all()->put('dep-parent', $parentManifest);
    $registry->all()->put('dep-child', $childManifest);

    $lifecycle->activate($tenant, 'dep-parent');
    $lifecycle->activate($tenant, 'dep-child');

    expect(fn () => $lifecycle->deactivate($tenant, 'dep-parent'))
        ->toThrow(RuntimeException::class, "Cannot deactivate 'dep-parent'. Plugin 'dep-child' depends on it.");
});
