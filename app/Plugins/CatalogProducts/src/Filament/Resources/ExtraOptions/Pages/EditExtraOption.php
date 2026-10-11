<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Filament\Resources\ExtraOptions\Pages;

use App\Plugins\CatalogProducts\src\Filament\Resources\ExtraOptions\ExtraOptionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditExtraOption extends EditRecord
{
    protected static string $resource = ExtraOptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
