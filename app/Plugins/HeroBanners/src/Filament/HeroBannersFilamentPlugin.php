<?php

declare(strict_types=1);

namespace App\Plugins\HeroBanners\src\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;

final class HeroBannersFilamentPlugin implements Plugin
{
    public static function make(): static
    {
        return app(self::class);
    }

    public function getId(): string
    {
        return 'hero-banners';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                Resources\HeroSliderResource::class,
            ])
            ->pages([
                // Register Filament Pages here
            ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
