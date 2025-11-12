<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sections\Pages;

use App\Filament\Resources\Sections\SectionResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateSection extends CreateRecord
{
    protected static string $resource = SectionResource::class;
}
