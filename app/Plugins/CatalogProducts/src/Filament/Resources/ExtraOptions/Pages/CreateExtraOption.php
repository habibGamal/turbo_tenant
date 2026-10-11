<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Filament\Resources\ExtraOptions\Pages;

use App\Plugins\CatalogProducts\src\Filament\Resources\ExtraOptions\ExtraOptionResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateExtraOption extends CreateRecord
{
    protected static string $resource = ExtraOptionResource::class;
}
