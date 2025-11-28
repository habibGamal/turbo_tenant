<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\TenantResource\Pages;

use App\Filament\Central\Resources\TenantResource;
use Filament\Resources\Pages\ListRecords;

final class ListTenants extends ListRecords
{
    protected static string $resource = TenantResource::class;
}
