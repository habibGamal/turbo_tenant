<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

abstract class TestCase extends BaseTestCase
{
    protected array $connectionsToTransact = ['sqlite'];

    protected static ?Tenant $testTenant = null;

    protected static bool $tenantMigrated = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->shouldInitializeTenancy()) {
            $this->useCentralConnection();

            return;
        }

        $testingDir = database_path('testing');
        if (! is_dir($testingDir)) {
            mkdir($testingDir, 0777, true);
        }

        // One tenant database file per test process, so parallel test runs don't wipe each other's tables.
        config([
            'tenancy.central_domains' => [],
            'tenancy.database.prefix' => 'testing/tenant_p'.getmypid().'_',
            'tenancy.database.suffix' => '.sqlite',
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
        $this->useCentralConnection();

        DB::disconnect('tenant');
        DB::purge('tenant');

        parent::tearDown();
    }

    /**
     * Reset default database connection and end any active tenancy context.
     */
    protected function useCentralConnection(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $central = config('tenancy.database.central_connection', 'sqlite');
        config(['database.default' => $central]);
        DB::setDefaultConnection($central);
    }

    protected function shouldInitializeTenancy(): bool
    {
        return ! $this instanceof CentralTestCase;
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

        $tenantDbName = 'testing/tenant_'.getmypid().'.sqlite';
        $dbFile = database_path($tenantDbName);

        $tenant = Tenant::withoutEvents(function () use ($tenantDbName): Tenant {
            $t = Tenant::firstOrCreate(['id' => 'test_suite_tenant']);
            $t->setInternal('db_name', $tenantDbName);

            return $t;
        });

        $appHost = parse_url(config('app.url', 'http://localhost'), PHP_URL_HOST) ?: 'localhost';

        if (static::$testTenant === null) {
            if (file_exists($dbFile)) {
                @unlink($dbFile);
            }
            static::$testTenant = $tenant;
            DB::purge('tenant');
            static::$testTenant->database()->manager()->createDatabase(static::$testTenant);
            register_shutdown_function(static function () use ($dbFile): void {
                if (file_exists($dbFile)) {
                    @unlink($dbFile);
                }
            });
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
            } catch (Throwable) {
            }
        } else {
            $tables = DB::connection('tenant')->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' AND name != 'migrations'");
            DB::connection('tenant')->statement('PRAGMA foreign_keys = OFF;');
            DB::connection('tenant')->beginTransaction();
            foreach ($tables as $table) {
                try {
                    DB::connection('tenant')->statement("DELETE FROM \"{$table->name}\";");
                } catch (Throwable) {
                }
            }
            try {
                DB::connection('tenant')->statement('DELETE FROM sqlite_sequence;');
            } catch (Throwable) {
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
