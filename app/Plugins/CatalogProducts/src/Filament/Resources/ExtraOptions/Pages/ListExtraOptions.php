<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Filament\Resources\ExtraOptions\Pages;

use App\Plugins\CatalogProducts\src\Filament\Resources\ExtraOptions\ExtraOptionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListExtraOptions extends ListRecords
{
    protected static string $resource = ExtraOptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
