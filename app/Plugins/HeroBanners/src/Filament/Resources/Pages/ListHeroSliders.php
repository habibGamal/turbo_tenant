<?php

declare(strict_types=1);

namespace App\Plugins\HeroBanners\src\Filament\Resources\Pages;

use App\Plugins\HeroBanners\src\Filament\Resources\HeroSliderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListHeroSliders extends ListRecords
{
    protected static string $resource = HeroSliderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
