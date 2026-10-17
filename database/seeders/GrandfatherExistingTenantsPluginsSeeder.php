<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Plugins\Enums\PluginStatus;
use App\Core\Plugins\Services\PluginRegistry;
use App\Models\Plugin;
use App\Models\Tenant;
use App\Models\TenantPlugin;
use Illuminate\Database\Seeder;

final class GrandfatherExistingTenantsPluginsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Grandfathers all existing tenants by discovering all plugins,
     * syncing the central plugin catalog, and granting active status
     * for all plugins across all existing stores so zero regression occurs.
     */
    public function run(): void
    {
        /** @var PluginRegistry $registry */
        $registry = app(PluginRegistry::class);
        $registry->discover()->syncDatabase();

        $plugins = Plugin::all();
        $tenants = Tenant::all();

        $now = now();

        foreach ($tenants as $tenant) {
            foreach ($plugins as $plugin) {
                TenantPlugin::firstOrCreate(
                    [
                        'tenant_id' => (string) $tenant->id,
                        'plugin_id' => (string) $plugin->id,
                    ],
                    [
                        'status' => PluginStatus::ACTIVE,
                        'settings' => [],
                        'installed_at' => $now,
                        'activated_at' => $now,
                    ]
                );
            }
        }
    }
}
