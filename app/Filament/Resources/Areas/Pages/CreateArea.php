<?php

declare(strict_types=1);

namespace App\Filament\Resources\Areas\Pages;

use App\Filament\Resources\Areas\AreaResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateArea extends CreateRecord
{
    protected static string $resource = AreaResource::class;
}
