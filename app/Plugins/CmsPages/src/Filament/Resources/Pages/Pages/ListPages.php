<?php

declare(strict_types=1);

namespace App\Plugins\CmsPages\src\Filament\Resources\Pages\Pages;

use App\Plugins\CmsPages\src\Filament\Resources\Pages\PageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPages extends ListRecords
{
    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
