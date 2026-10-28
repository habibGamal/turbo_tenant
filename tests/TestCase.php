<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected static ?Tenant $testTenant = null;

    protected static bool $tenantMigrated = false;

    protected function setUp(): void
    {
        parent::setUp();

        // One tenant database file per test process, so parallel test runs don't wipe each other's tables.
        config([
            'tenancy.central_domains' => [],
            'tenancy.database.prefix' => 'tenant_p'.getmypid().'_',
        ]);

        $this->ensureTenantExists();
        $this->initializeTenancy();

        $this->actingAs(User::firstOrCreate(
            ['email' => config('app.default_user.email')],
            [
                'name' => config('app.default_user.name'),
                'password' => config('app.default_user.password'),
                'email_verified_at' => now(),
                'remember_token' => \Illuminate\Support\Str::random(10),
            ]
        ));

        $this->withoutVite();
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

    /**
     * Create physical tenant and database once across the entire test suite.
     */
    protected function ensureTenantExists(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('tenants')) {
            Artisan::call('migrate', [
                '--path' => 'database/migrations',
                '--force' => true,
            ]);
        }

        $tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::firstOrCreate([
            'id' => 'test_suite_tenant',
        ]));

        $appHost = parse_url(config('app.url', 'http://localhost'), PHP_URL_HOST) ?: 'localhost';

        if (static::$testTenant === null) {
            static::$testTenant = $tenant;
            DB::purge('tenant');
            static::$testTenant->database()->manager()->createDatabase(static::$testTenant);
            $dbFile = database_path(static::$testTenant->database()->getName());
            register_shutdown_function(static fn () => @unlink($dbFile));
            static::$testTenant->domains()->firstOrCreate(['domain' => 'localhost']);
            if ($appHost !== 'localhost') {
                static::$testTenant->domains()->firstOrCreate(['domain' => $appHost]);
            }
        } else {
            static::$testTenant = $tenant;
            static::$testTenant->domains()->firstOrCreate(['domain' => 'localhost']);
            if ($appHost !== 'localhost') {
                static::$testTenant->domains()->firstOrCreate(['domain' => $appHost]);
            }
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

        if (! static::$tenantMigrated) {
            Artisan::call('migrate:fresh', [
                '--database' => 'tenant',
                '--path' => $paths,
                '--realpath' => true,
                '--force' => true,
            ]);
            static::$tenantMigrated = true;
            try {
                DB::connection('tenant')->statement('PRAGMA journal_mode = MEMORY;');
                DB::connection('tenant')->statement('PRAGMA synchronous = OFF;');
            } catch (\Throwable) {
            }
        } else {
            $tables = DB::connection('tenant')->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' AND name != 'migrations'");
            DB::connection('tenant')->statement('PRAGMA foreign_keys = OFF;');
            DB::connection('tenant')->beginTransaction();
            foreach ($tables as $table) {
                try {
                    DB::connection('tenant')->statement("DELETE FROM \"{$table->name}\";");
                } catch (\Throwable) {
                }
            }
            try {
                DB::connection('tenant')->statement('DELETE FROM sqlite_sequence;');
            } catch (\Throwable) {
            }
            DB::connection('tenant')->commit();
            DB::connection('tenant')->statement('PRAGMA foreign_keys = ON;');
        }

        if ($this->shouldAutoActivatePlugins() && class_exists(\App\Core\Plugins\Services\PluginRegistry::class)) {
            $registry = app(\App\Core\Plugins\Services\PluginRegistry::class);
            $registry->discover();
            $registry->syncDatabase();

            $manager = app(\App\Core\Plugins\Services\PluginManager::class);
            foreach ($registry->all() as $manifest) {
                if ($manifest->id === 'sample-greeting') {
                    continue;
                }
                \App\Models\TenantPlugin::updateOrCreate([
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
}
