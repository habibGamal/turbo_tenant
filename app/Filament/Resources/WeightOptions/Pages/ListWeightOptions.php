<?php

declare(strict_types=1);

namespace App\Filament\Resources\WeightOptions\Pages;

use App\Filament\Resources\WeightOptions\WeightOptionResource;
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
