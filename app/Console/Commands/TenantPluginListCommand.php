<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Plugins\Services\PluginRegistry;
use App\Models\Tenant;
use App\Models\TenantPlugin;
use Illuminate\Console\Command;

final class TenantPluginListCommand extends Command
{
    protected $signature = 'tenant:plugin:list {tenant : The ID of the tenant}';

    protected $description = 'List all plugins and their status for a specific tenant';

    public function handle(PluginRegistry $registry): int
    {
        $tenantId = (string) $this->argument('tenant');
        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            $this->error("Tenant '{$tenantId}' not found.");

            return self::FAILURE;
        }

        $allManifests = $registry->all();
        $tenantRecords = TenantPlugin::where('tenant_id', $tenantId)->get()->keyBy('plugin_id');

        $rows = $allManifests->map(function ($manifest) use ($tenantRecords) {
            $record = $tenantRecords->get($manifest->id);
            $status = $record?->status->value ?? 'not_entitled';

            return [
                $manifest->id,
                $manifest->name,
                $manifest->version,
                strtoupper($status),
                $record?->activated_at?->toDateTimeString() ?? '-',
            ];
        })->values()->all();

        $this->table(
            ['Plugin ID', 'Name', 'Version', 'Tenant Status', 'Activated At'],
            $rows
        );

        return self::SUCCESS;
    }
}
