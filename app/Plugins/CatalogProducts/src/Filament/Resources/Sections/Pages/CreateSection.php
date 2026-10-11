<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Filament\Resources\Sections\Pages;

use App\Plugins\CatalogProducts\src\Filament\Resources\Sections\SectionResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateSection extends CreateRecord
{
    protected static string $resource = SectionResource::class;
}
