<?php

declare(strict_types=1);

namespace App\Plugins\StoreSettings\src\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;

final class StoreSettingsFilamentPlugin implements Plugin
{
    public static function make(): static
    {
        return app(self::class);
    }

    public function getId(): string
    {
        return 'store-settings';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                //
            ])
            ->pages([
                Pages\Settings::class,
                Pages\ManifestSettings::class,
            ]);

    }

    public function boot(Panel $panel): void
    {
        //
    }
}
