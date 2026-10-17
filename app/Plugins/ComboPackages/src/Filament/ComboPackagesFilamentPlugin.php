<?php

declare(strict_types=1);

namespace App\Plugins\ComboPackages\src\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;

final class ComboPackagesFilamentPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'combo-packages';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                \App\Plugins\ComboPackages\src\Filament\Resources\Packages\PackageResource::class,
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
