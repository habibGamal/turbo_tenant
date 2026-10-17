<?php

declare(strict_types=1);

namespace App\Plugins\HeroBanners\src\Filament\Resources\Pages;

use App\Plugins\HeroBanners\src\Filament\Resources\HeroSliderResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditHeroSlider extends EditRecord
{
    protected static string $resource = HeroSliderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
