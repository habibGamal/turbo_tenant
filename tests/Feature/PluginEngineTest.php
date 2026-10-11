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

    \App\Models\Tenant::withoutEvents(fn () => \App\Models\Tenant::firstOrCreate(['id' => 'test_suite_tenant']));
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
        'category' => \App\Core\Plugins\Enums\PluginCategory::FOUNDATION,
        'is_core' => true,
    ]);

    $coreManifest = new \App\Core\Plugins\DTOs\PluginManifest(
        id: 'core-test',
        name: 'Core Test Plugin',
        version: '1.0.0',
        category: \App\Core\Plugins\Enums\PluginCategory::FOUNDATION,
        basePath: 'app/Plugins/CoreTest',
        isCore: true
    );

    $registry->all()->put('core-test', $coreManifest);
    $lifecycle->activate($tenant, 'core-test');

    expect(fn () => $lifecycle->deactivate($tenant, 'core-test'))
        ->toThrow(RuntimeException::class, "Plugin 'core-test' is a mandatory core plugin and cannot be deactivated or uninstalled.");
});


