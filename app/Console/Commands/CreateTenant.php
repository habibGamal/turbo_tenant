<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\TenantService;
use Illuminate\Console\Command;

final class CreateTenant extends Command
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
    protected $description = 'Create a new tenant with storage directories, domain, manifest, and theme files';

    /**
     * Execute the console command.
     */
    public function handle(TenantService $tenantService): int
    {
        $tenantId = $this->option('id') ?: 'kofe';
        $domain = $this->option('domain') ?: 'kofe.localhost';

        $tenant = $tenantService->createTenant($tenantId);
        $tenantService->setupTenant($tenant, $domain);

        $this->info("Tenant '{$tenantId}' with domain '{$domain}' is ready.");

        return self::SUCCESS;
    }
}
