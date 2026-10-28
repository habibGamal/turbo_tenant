<?php

declare(strict_types=1);

use App\Plugins\HeroBanners\src\Filament\Resources\HeroSliderResource as PluginHeroSliderResource;

if (! class_exists('App\Filament\Resources\HeroSliderResource', false)) {
    class_alias(PluginHeroSliderResource::class, 'App\Filament\Resources\HeroSliderResource');
}
