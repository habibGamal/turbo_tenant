<?php

declare(strict_types=1);

namespace App\Plugins\CmsPages\src\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;

final class CmsPagesFilamentPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'cms-pages';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                \App\Plugins\CmsPages\src\Filament\Resources\Pages\PageResource::class,
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
