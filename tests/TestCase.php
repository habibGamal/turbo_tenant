<?php

namespace Tests;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected static ?Tenant $testTenant = null;

    protected function setUp(): void
    {
        parent::setUp();

        config(['tenancy.central_domains' => []]);

        $this->ensureTenantExists();
        $this->initializeTenancy();

        $this->actingAs(User::factory()->create([
            'name' => config('app.default_user.name'),
            'email' => config('app.default_user.email'),
            'password' => config('app.default_user.password'),
        ]));

        $this->withoutVite();
    }

    /**
     * Create physical tenant and database once across the entire test suite.
     */
    protected function ensureTenantExists(): void
    {
        $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::firstOrCreate([
            'id' => 'test_suite_tenant',
        ]));

        if (static::$testTenant === null) {
            static::$testTenant = $tenant;
            DB::purge('tenant');
            static::$testTenant->database()->manager()->createDatabase(static::$testTenant);
            static::$testTenant->domains()->firstOrCreate(['domain' => 'localhost']);
        } else {
            static::$testTenant = $tenant;
            static::$testTenant->domains()->firstOrCreate(['domain' => 'localhost']);
        }
    }

    /**
     * Set up tenant context and refresh schema for each individual test.
     */
    protected function initializeTenancy(): void
    {
        tenancy()->initialize(static::$testTenant);

        DB::disconnect('tenant');
        DB::purge('tenant');
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }

        $pluginMigrationPaths = array_filter(
            glob(base_path('app/Plugins/*/database/migrations/tenant')) ?: [],
            fn ($p) => ! str_contains($p, 'SampleGreeting')
        );

        $paths = array_merge(
            [database_path('migrations/tenant')],
            $pluginMigrationPaths
        );

        Artisan::call('migrate:fresh', [
            '--database' => 'tenant',
            '--path' => $paths,
            '--realpath' => true,
            '--force' => true,
        ]);

        if ($this->shouldAutoActivatePlugins() && class_exists(\App\Core\Plugins\Services\PluginRegistry::class)) {
            $registry = app(\App\Core\Plugins\Services\PluginRegistry::class);
            $registry->discover();
            $registry->syncDatabase();

            $manager = app(\App\Core\Plugins\Services\PluginManager::class);
            foreach ($registry->all() as $manifest) {
                if ($manifest->id === 'sample-greeting') {
                    continue;
                }
                \App\Models\TenantPlugin::firstOrCreate([
                    'tenant_id' => (string) static::$testTenant->id,
                    'plugin_id' => $manifest->id,
                ], [
                    'status' => \App\Core\Plugins\Enums\PluginStatus::ACTIVE,
                    'settings' => [],
                    'activated_at' => now(),
                    'installed_at' => now(),
                ]);
            }

            $manager->resetTenant(static::$testTenant);
            $manager->bootTenant(static::$testTenant, true);
        }
    }

    protected function shouldAutoActivatePlugins(): bool
    {
        $class = static::class;

        return ! str_contains($class, 'Phase1CoreFoundationPluginsTest')
            && ! str_contains($class, 'PluginEngineTest');
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        DB::disconnect('tenant');
        DB::purge('tenant');

        parent::tearDown();
    }
}
