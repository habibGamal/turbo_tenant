<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Plugins\Enums\PluginStatus;
use App\Core\Plugins\Services\PluginDependencyResolver;
use App\Core\Plugins\Services\PluginRegistry;
use App\Models\Tenant;
use App\Models\TenantPlugin;
use Illuminate\Console\Command;
use Throwable;

final class TenantsPluginMigrateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenants:plugin:migrate
                            {--tenant= : The ID of a specific tenant to migrate}
                            {--plugin= : The ID of a specific plugin to migrate}
                            {--force : Force the operation to run when in production}';

    /**
     * Alternative aliases for the command.
     *
     * @var array<int, string>
     */
    protected $aliases = ['tenant:plugin:migrate'];

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run pending migrations for each activated plugin in each tenant database';

    public function handle(PluginRegistry $registry, PluginDependencyResolver $resolver): int
    {
        $targetTenantId = $this->option('tenant') ? (string) $this->option('tenant') : null;
        $targetPluginId = $this->option('plugin') ? (string) $this->option('plugin') : null;
        $force = (bool) $this->option('force');

        // Ensure plugins are discovered from disk
        $registry->all();

        if ($targetPluginId !== null && ! $registry->has($targetPluginId)) {
            $this->error("Plugin '{$targetPluginId}' is not registered or found on disk.");

            return self::FAILURE;
        }

        $tenants = $targetTenantId !== null
            ? Tenant::where('id', $targetTenantId)->get()
            : Tenant::all();

        if ($tenants->isEmpty()) {
            if ($targetTenantId !== null) {
                $this->error("Tenant '{$targetTenantId}' not found.");

                return self::FAILURE;
            }

            $this->warn('No tenants found.');

            return self::SUCCESS;
        }

        $this->info("Scanning {$tenants->count()} tenant(s) for active plugin migrations...");

        foreach ($tenants as $tenant) {
            $this->line('');
            $this->info("==> Processing Tenant: <comment>{$tenant->id}</comment>");

            // Query strictly active plugins in tenant_plugins table for this tenant
            $activePluginQuery = TenantPlugin::query()
                ->where('tenant_id', (string) $tenant->id)
                ->where('status', PluginStatus::ACTIVE);

            if ($targetPluginId !== null) {
                $activePluginQuery->where('plugin_id', $targetPluginId);
            }

            $activePluginIds = $activePluginQuery->pluck('plugin_id')->all();

            if (empty($activePluginIds)) {
                if ($targetPluginId !== null) {
                    $this->line("    Plugin '<comment>{$targetPluginId}</comment>' is not active for this tenant. Skipping.");
                } else {
                    $this->line('    No active plugins found for this tenant.');
                }

                continue;
            }

            $pluginsWithMigrations = [];

            foreach ($activePluginIds as $pluginId) {
                $manifest = $registry->get($pluginId);

                if ($manifest === null) {
                    $this->warn("    Plugin '{$pluginId}' is active in database but manifest was not found on disk.");

                    continue;
                }

                if ($manifest->tenantMigrationsPath !== null && is_dir($manifest->tenantMigrationsPath)) {
                    $pluginsWithMigrations[] = $manifest;
                }
            }

            if (empty($pluginsWithMigrations)) {
                $this->line('    No active plugins have migration directories.');

                continue;
            }

            // Dependencies first: a plugin's migrations may read columns its dependencies add.
            $pluginsWithMigrations = $resolver->sort(collect($pluginsWithMigrations)->keyBy('id'));

            foreach ($pluginsWithMigrations as $manifest) {
                $this->line("  -> Migrating plugin: <info>{$manifest->id}</info> ({$manifest->name})");

                try {
                    $exitCode = 0;

                    $tenant->run(function () use ($manifest, $force, &$exitCode) {
                        $exitCode = $this->call('migrate', [
                            '--path' => $manifest->tenantMigrationsPath,
                            '--realpath' => true,
                            '--force' => $force,
                        ]);
                    });

                    if ($exitCode !== 0) {
                        $this->error("Migration failed for plugin '{$manifest->id}' in tenant '{$tenant->id}' (exit code: {$exitCode}).");

                        return self::FAILURE;
                    }
                } catch (Throwable $e) {
                    $this->error("Failed to migrate plugin '{$manifest->id}' for tenant '{$tenant->id}': {$e->getMessage()}");

                    return self::FAILURE;
                }
            }
        }

        $this->newLine();
        $this->info("Successfully completed plugin migrations across {$tenants->count()} tenant(s).");

        return self::SUCCESS;
    }
}
