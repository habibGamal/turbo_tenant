<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Filament\Resources\WeightOptions\Pages;

use App\Plugins\CatalogProducts\src\Filament\Resources\WeightOptions\WeightOptionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListWeightOptions extends ListRecords
{
    protected static string $resource = WeightOptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
