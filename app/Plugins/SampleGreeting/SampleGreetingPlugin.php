<?php

declare(strict_types=1);

namespace App\Plugins\SampleGreeting;

use App\Core\Plugins\AbstractTenantPlugin;
use App\Models\Tenant;

final class SampleGreetingPlugin extends AbstractTenantPlugin
{
    public function bootForTenant(Tenant $tenant): void
    {
        config(['sample-greeting.active_tenant' => (string) $tenant->id]);
    }
}
