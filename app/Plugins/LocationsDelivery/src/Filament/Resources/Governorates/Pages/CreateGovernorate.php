<?php

declare(strict_types=1);

namespace App\Plugins\LocationsDelivery\src\Filament\Resources\Governorates\Pages;

use App\Plugins\LocationsDelivery\src\Filament\Resources\Governorates\GovernorateResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateGovernorate extends CreateRecord
{
    protected static string $resource = GovernorateResource::class;
}
