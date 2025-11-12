<?php

declare(strict_types=1);

namespace App\Filament\Resources\WeightOptions\Pages;

use App\Filament\Resources\WeightOptions\WeightOptionResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateWeightOption extends CreateRecord
{
    protected static string $resource = WeightOptionResource::class;
}
