<?php

declare(strict_types=1);

namespace App\Plugins\LocationsDelivery\src\Filament\Resources\Branches\Pages;

use App\Plugins\LocationsDelivery\src\Filament\Resources\Branches\BranchResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditBranch extends EditRecord
{
    protected static string $resource = BranchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
