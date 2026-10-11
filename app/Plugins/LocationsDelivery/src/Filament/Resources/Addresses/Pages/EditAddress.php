<?php

declare(strict_types=1);

namespace App\Plugins\LocationsDelivery\src\Filament\Resources\Addresses\Pages;

use App\Plugins\LocationsDelivery\src\Filament\Resources\Addresses\AddressResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditAddress extends EditRecord
{
    protected static string $resource = AddressResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
