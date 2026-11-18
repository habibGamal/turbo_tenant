<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Core\Plugins\Enums\PluginStatus;
use App\Core\Plugins\Services\PluginManager;
use App\Filament\Pages\ManagePlugins;
use App\Filament\Widgets\PluginStatsOverview;
use App\Models\TenantPlugin;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

final class ManagePluginsTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'is_admin' => true,
        ]);
        $this->actingAs($this->admin);

        $tenant = tenant();
        $this->assertNotNull($tenant);
        app(PluginManager::class)->resetTenant($tenant);
    }

    public function test_manage_plugins_page_and_stats_widget_render_successfully(): void
    {
        $this->assertFalse(PluginStatsOverview::isDiscovered());

        Livewire::test(ManagePlugins::class)
            ->assertOk()
            ->assertSee('الكل')
            ->assertSee('المفعلة')
            ->assertSee('متاحة للتثبيت')
            ->assertSee('الأساسية (Core)')
            ->assertSee('Store Settings & Configuration');

        Livewire::test(PluginStatsOverview::class)
            ->assertOk()
            ->assertSee('إجمالي الإضافات')
            ->assertSee('الإضافات المفعلة')
            ->assertSee('متاحة للتثبيت')
            ->assertSee('إضافات أساسية');
    }

    public function test_table_lists_plugins_and_supports_tabs_search_category_filter_and_sorting(): void
    {
        $tenant = tenant();
        $this->assertNotNull($tenant);

        TenantPlugin::query()
            ->where('tenant_id', (string) $tenant->id)
            ->where('plugin_id', 'sample-greeting')
            ->delete();
        app(PluginManager::class)->resetTenant($tenant);

        $component = Livewire::test(ManagePlugins::class)
            ->assertOk()
            ->assertCanSeeTableRecords(['store-settings', 'sample-greeting']);

        $component
            ->set('activeTab', 'core')
            ->assertCanSeeTableRecords(['store-settings'])
            ->assertCanNotSeeTableRecords(['sample-greeting']);

        $component
            ->set('activeTab', 'not_installed')
            ->assertCanSeeTableRecords(['sample-greeting'])
            ->assertCanNotSeeTableRecords(['store-settings']);

        $component
            ->set('activeTab', 'all')
            ->searchTable('cms-pages')
            ->assertCanSeeTableRecords(['cms-pages'])
            ->assertCanNotSeeTableRecords(['store-settings']);

        $component
            ->searchTable('')
            ->filterTable('category', 'marketing')
            ->assertCanSeeTableRecords(['coupons-promotions'])
            ->assertCanNotSeeTableRecords(['store-settings']);
    }

    public function test_install_action_activates_plugin_without_dependencies_immediately(): void
    {
        $tenant = tenant();
        $this->assertNotNull($tenant);

        TenantPlugin::query()
            ->where('tenant_id', (string) $tenant->id)
            ->where('plugin_id', 'sample-greeting')
            ->delete();
        app(PluginManager::class)->resetTenant($tenant);

        Livewire::test(ManagePlugins::class)
            ->callAction(TestAction::make('install')->table('sample-greeting'))
            ->assertNotified()
            ->assertDispatched('plugins-updated');

        $this->assertTrue(app(PluginManager::class)->isPluginActive('sample-greeting', $tenant));
        $this->assertTrue($tenant->run(fn (): bool => Schema::hasTable('sample_greetings')));
    }

    public function test_install_action_resolves_and_activates_missing_dependencies_in_topological_order(): void
    {
        $tenant = tenant();
        $this->assertNotNull($tenant);

        TenantPlugin::query()
            ->where('tenant_id', (string) $tenant->id)
            ->whereIn('plugin_id', ['customer-favorites', 'catalog-products'])
            ->delete();
        app(PluginManager::class)->resetTenant($tenant);

        Livewire::test(ManagePlugins::class)
            ->mountAction(TestAction::make('install')->table('customer-favorites'))
            ->assertMountedActionModalSee('متطلبات التثبيت الإضافية')
            ->callMountedAction()
            ->assertNotified()
            ->assertDispatched('plugins-updated');

        $this->assertDatabaseHas('tenant_plugins', [
            'tenant_id' => (string) $tenant->id,
            'plugin_id' => 'catalog-products',
            'status' => PluginStatus::ACTIVE->value,
        ], 'sqlite');

        $this->assertDatabaseHas('tenant_plugins', [
            'tenant_id' => (string) $tenant->id,
            'plugin_id' => 'customer-favorites',
            'status' => PluginStatus::ACTIVE->value,
        ], 'sqlite');
    }

    public function test_deactivate_and_uninstall_actions_respect_core_and_active_dependents(): void
    {
        $tenant = tenant();
        $this->assertNotNull($tenant);

        $component = Livewire::test(ManagePlugins::class);

        $component
            ->assertActionHidden(TestAction::make('deactivate')->table('store-settings'))
            ->assertActionHidden(TestAction::make('uninstall')->table('store-settings'))
            ->assertActionVisible(TestAction::make('core_locked')->table('store-settings'));

        // Active dependent (customer-favorites) must block deactivating or uninstalling catalog-products
        $component
            ->callAction(TestAction::make('deactivate')->table('catalog-products'))
            ->assertNotified('لا يمكن تعطيل الإضافة حالياً');

        $this->assertTrue(app(PluginManager::class)->isPluginActive('catalog-products', $tenant));

        $component
            ->callAction(TestAction::make('uninstall')->table('catalog-products'))
            ->assertNotified('لا يمكن إلغاء التثبيت حالياً');

        $this->assertTrue(app(PluginManager::class)->isPluginActive('catalog-products', $tenant));

        // Standalone plugin lifecycle: install -> deactivate -> uninstall
        $component->callAction(TestAction::make('install')->table('sample-greeting'));
        $this->assertTrue(app(PluginManager::class)->isPluginActive('sample-greeting', $tenant));

        $component
            ->callAction(TestAction::make('deactivate')->table('sample-greeting'))
            ->assertNotified()
            ->assertDispatched('plugins-updated');

        $this->assertDatabaseHas('tenant_plugins', [
            'tenant_id' => (string) $tenant->id,
            'plugin_id' => 'sample-greeting',
            'status' => PluginStatus::DEACTIVATED->value,
        ], 'sqlite');

        $component
            ->callAction(TestAction::make('uninstall')->table('sample-greeting'))
            ->assertNotified()
            ->assertDispatched('plugins-updated');

        $this->assertDatabaseMissing('tenant_plugins', [
            'tenant_id' => (string) $tenant->id,
            'plugin_id' => 'sample-greeting',
        ], 'sqlite');
        $this->assertFalse($tenant->run(fn (): bool => Schema::hasTable('sample_greetings')));
    }
}
