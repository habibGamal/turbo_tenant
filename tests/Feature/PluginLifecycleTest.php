<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Plugins\Contracts\TenantPluginInterface;
use App\Core\Plugins\Enums\PluginStatus;
use App\Core\Plugins\Services\PluginLifecycleService;
use App\Core\Plugins\Services\PluginManager;
use App\Core\Plugins\Services\PluginRegistry;
use App\Models\Tenant;
use App\Models\TenantPlugin;
use App\Plugins\SampleGreeting\SampleGreetingPlugin;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

final class PluginLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate', [
            '--path' => 'database/migrations',
            '--force' => true,
        ]);

        app(PluginRegistry::class)->discover()->syncDatabase();
    }

    public function test_newly_created_tenant_has_core_plugins_activated_automatically(): void
    {
        $tenant = Tenant::create(['id' => 'lifecycle_core_tenant_'.uniqid()]);

        try {
            $storeSettingsRecord = TenantPlugin::query()
                ->where('tenant_id', (string) $tenant->id)
                ->where('plugin_id', 'store-settings')
                ->first();

            $this->assertNotNull($storeSettingsRecord);
            $this->assertSame(PluginStatus::ACTIVE, $storeSettingsRecord->status);

            $mediaOptimizerRecord = TenantPlugin::query()
                ->where('tenant_id', (string) $tenant->id)
                ->where('plugin_id', 'media-optimizer')
                ->first();

            $this->assertNotNull($mediaOptimizerRecord);
            $this->assertSame(PluginStatus::ACTIVE, $mediaOptimizerRecord->status);

            $this->assertTrue(app(PluginManager::class)->isPluginActive('store-settings', $tenant));
            $this->assertTrue(app(PluginManager::class)->isPluginActive('media-optimizer', $tenant));
        } finally {
            $tenant->delete();
        }
    }

    public function test_uninstall_drops_plugin_tables_even_when_a_later_migration_batch_exists(): void
    {
        $tenant = self::$testTenant;
        $lifecycle = app(PluginLifecycleService::class);

        $lifecycle->activate($tenant, 'sample-greeting');

        $hasTable = $tenant->run(fn () => Schema::hasTable('sample_greetings'));
        $this->assertTrue($hasTable);

        // Simulate a later migration batch in the tenant's database
        $tenant->run(fn () => DB::table('migrations')->insert([
            'migration' => '2026_12_31_999999_subsequent_dummy_migration',
            'batch' => 999,
        ]));

        // Uninstall must use migrate:reset to drop tables even though batch 999 exists
        $lifecycle->uninstall($tenant, 'sample-greeting');

        $hasTableAfter = $tenant->run(fn () => Schema::hasTable('sample_greetings'));
        $this->assertFalse($hasTableAfter, 'sample_greetings table should be dropped after uninstall');

        $recordExists = TenantPlugin::query()
            ->where('tenant_id', (string) $tenant->id)
            ->where('plugin_id', 'sample-greeting')
            ->exists();
        $this->assertFalse($recordExists);

        // Clean up dummy migration entry
        $tenant->run(fn () => DB::table('migrations')
            ->where('migration', '2026_12_31_999999_subsequent_dummy_migration')
            ->delete()
        );
    }

    public function test_lifecycle_hooks_on_activate_on_deactivate_and_on_uninstall_are_called(): void
    {
        $tenant = self::$testTenant;
        $lifecycle = app(PluginLifecycleService::class);

        $mock = Mockery::mock(TenantPluginInterface::class);
        $mock->shouldReceive('onActivate')->once()->with(Mockery::type(Tenant::class));
        $mock->shouldReceive('onDeactivate')->once()->with(Mockery::type(Tenant::class));
        $mock->shouldReceive('onUninstall')->once()->with(Mockery::type(Tenant::class));

        app()->instance(SampleGreetingPlugin::class, $mock);

        $lifecycle->activate($tenant, 'sample-greeting');
        $lifecycle->deactivate($tenant, 'sample-greeting');
        $lifecycle->uninstall($tenant, 'sample-greeting');
    }

    public function test_reactivating_a_deactivated_plugin_preserves_settings_and_installed_at(): void
    {
        $tenant = self::$testTenant;
        $lifecycle = app(PluginLifecycleService::class);

        $initialSettings = ['greeting' => 'Welcome to custom restaurant'];

        $lifecycle->activate($tenant, 'sample-greeting', $initialSettings);

        $record = TenantPlugin::query()
            ->where('tenant_id', (string) $tenant->id)
            ->where('plugin_id', 'sample-greeting')
            ->first();

        $this->assertNotNull($record);
        $originalInstalledAt = $record->installed_at;
        $this->assertSame($initialSettings, $record->settings);

        // Deactivate plugin
        $lifecycle->deactivate($tenant, 'sample-greeting');

        $deactivatedRecord = TenantPlugin::query()
            ->where('tenant_id', (string) $tenant->id)
            ->where('plugin_id', 'sample-greeting')
            ->first();
        $this->assertSame(PluginStatus::DEACTIVATED, $deactivatedRecord->status);

        // Reactivate without passing new settings
        $lifecycle->activate($tenant, 'sample-greeting');

        $reactivatedRecord = TenantPlugin::query()
            ->where('tenant_id', (string) $tenant->id)
            ->where('plugin_id', 'sample-greeting')
            ->first();

        $this->assertSame(PluginStatus::ACTIVE, $reactivatedRecord->status);
        $this->assertSame($initialSettings, $reactivatedRecord->settings, 'Settings must be preserved on reactivation');
        $this->assertSame(
            $originalInstalledAt->toDateTimeString(),
            $reactivatedRecord->installed_at->toDateTimeString(),
            'installed_at must be preserved on reactivation'
        );

        $lifecycle->uninstall($tenant, 'sample-greeting');
    }

    public function test_activate_is_idempotent_for_already_active_plugin(): void
    {
        $tenant = self::$testTenant;
        $lifecycle = app(PluginLifecycleService::class);

        $lifecycle->activate($tenant, 'sample-greeting');
        // Calling activate again must not throw and must keep plugin active
        $lifecycle->activate($tenant, 'sample-greeting');

        $this->assertTrue(app(PluginManager::class)->isPluginActive('sample-greeting', $tenant));

        $lifecycle->uninstall($tenant, 'sample-greeting');
    }
}
