<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CreateTenant extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-tenant {--id=} {--domain=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tenantId = $this->option('id') ?: 'clinic2';
        $domain = $this->option('domain') ?: 'clinic2.localhost';

        // If tenant DB already exists, avoid triggering the TenantCreated pipeline
        $databaseName = 'tenant'.$tenantId;
        $dbExists = \DB::selectOne('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?', [$databaseName]);

        if ($dbExists) {
            $tenant = \App\Models\Tenant::withoutEvents(function () use ($tenantId) {
                return \App\Models\Tenant::query()->updateOrCreate(['id' => $tenantId], []);
            });
        } else {
            $tenant = \App\Models\Tenant::create(['id' => $tenantId]);
        }

        $tenant->domains()->firstOrCreate(['domain' => $domain]);

        $this->info("Tenant '{$tenantId}' with domain '{$domain}' is ready.");
    }
}
