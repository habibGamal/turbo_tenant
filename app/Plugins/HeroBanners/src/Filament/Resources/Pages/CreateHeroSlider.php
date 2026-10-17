<?php

declare(strict_types=1);

namespace App\Plugins\HeroBanners\src\Filament\Resources\Pages;

use App\Plugins\HeroBanners\src\Filament\Resources\HeroSliderResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateHeroSlider extends CreateRecord
{
    protected static string $resource = HeroSliderResource::class;
}
