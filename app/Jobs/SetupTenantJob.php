<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\TenantService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class SetupTenantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public string $domain
    ) {}

    public function handle(TenantService $tenantService): void
    {
        $tenantService->setupTenant($this->tenant, $this->domain);
    }
}
