<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\TenantResource\Pages;

use App\Filament\Central\Resources\TenantResource;
use App\Jobs\SetupTenantJob;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

final class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Hash password before storing
        if (isset($data['admin_password'])) {
            $data['admin_password'] = bcrypt($data['admin_password']);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $tenant = $this->record;

        // Get the first domain from the relationship
        $domain = $tenant->domains()->first()?->domain;

        if ($domain) {
            SetupTenantJob::dispatch($tenant, $domain);
        }

        // Create admin user in tenant database
        if ($tenant->admin_name && $tenant->admin_email && $tenant->admin_password) {
            $this->createTenantAdmin($tenant);
        }
    }

    protected function createTenantAdmin($tenant): void
    {
        tenancy()->initialize($tenant);

        User::query()->updateOrCreate(
            ['email' => $tenant->admin_email],
            [
                'name' => $tenant->admin_name,
                'email' => $tenant->admin_email,
                'password' => $tenant->admin_password,
                'is_admin' => true,
                'email_verified_at' => now(),
            ]
        );

        tenancy()->end();
    }
}
