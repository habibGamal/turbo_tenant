<?php

declare(strict_types=1);

namespace App\Filament\Resources\HeroSliderResource\Pages;

use App\Filament\Resources\HeroSliderResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateHeroSlider extends CreateRecord
{
    protected static string $resource = HeroSliderResource::class;
}
