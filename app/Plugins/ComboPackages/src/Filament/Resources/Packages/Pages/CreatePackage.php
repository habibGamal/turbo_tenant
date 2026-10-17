<?php

declare(strict_types=1);

namespace App\Plugins\ComboPackages\src\Filament\Resources\Packages\Pages;

use App\Plugins\ComboPackages\src\Filament\Resources\Packages\PackageResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePackage extends CreateRecord
{
    protected static string $resource = PackageResource::class;
}
