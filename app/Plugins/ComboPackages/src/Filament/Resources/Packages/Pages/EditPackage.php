<?php

declare(strict_types=1);

namespace App\Plugins\ComboPackages\src\Filament\Resources\Packages\Pages;

use App\Plugins\ComboPackages\src\Filament\Resources\Packages\PackageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPackage extends EditRecord
{
    protected static string $resource = PackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
