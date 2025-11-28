<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\TenantResource\Pages;

use App\Filament\Central\Resources\TenantResource;
use App\Jobs\SetupTenantJob;
use Filament\Resources\Pages\CreateRecord;

final class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    protected function afterCreate(): void
    {
        $tenant = $this->record;

        // Get the first domain from the relationship
        $domain = $tenant->domains()->first()?->domain;

        if ($domain) {
            SetupTenantJob::dispatch($tenant, $domain);
        }
    }
}
