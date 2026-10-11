<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Filament\Resources\WeightOptions\Pages;

use App\Plugins\CatalogProducts\src\Filament\Resources\WeightOptions\WeightOptionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditWeightOption extends EditRecord
{
    protected static string $resource = WeightOptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
