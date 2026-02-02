<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\TenantResource\Pages;

use App\Filament\Central\Resources\TenantResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Hash password before storing if provided
        if (isset($data['admin_password']) && filled($data['admin_password'])) {
            $data['admin_password'] = bcrypt($data['admin_password']);
        } else {
            // Remove password from data if not provided to prevent overwriting
            unset($data['admin_password']);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $tenant = $this->record;

        // Update admin user in tenant database if credentials are provided
        if ($tenant->admin_name && $tenant->admin_email) {
            $this->updateTenantAdmin($tenant);
        }
    }

    protected function updateTenantAdmin($tenant): void
    {
        tenancy()->initialize($tenant);

        $data = [
            'name' => $tenant->admin_name,
            'email' => $tenant->admin_email,
            'is_admin' => true,
        ];

        // Only update password if a new one was provided
        if ($tenant->admin_password) {
            $data['password'] = $tenant->admin_password;
        }

        User::query()->updateOrCreate(
            ['email' => $tenant->admin_email],
            $data
        );

        tenancy()->end();
    }
}
