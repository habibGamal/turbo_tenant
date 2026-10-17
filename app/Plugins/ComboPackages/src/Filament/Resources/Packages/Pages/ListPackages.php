<?php

declare(strict_types=1);

namespace App\Plugins\ComboPackages\src\Filament\Resources\Packages\Pages;

use App\Plugins\ComboPackages\src\Filament\Resources\Packages\PackageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPackages extends ListRecords
{
    protected static string $resource = PackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
