<?php

declare(strict_types=1);

namespace App\Plugins\CmsPages\src\Filament\Resources\Pages\Pages;

use App\Plugins\CmsPages\src\Filament\Resources\Pages\PageResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePage extends CreateRecord
{
    protected static string $resource = PageResource::class;
}
