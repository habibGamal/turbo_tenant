<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;

final class PosIntegrationFilamentPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'pos-integration';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                //
            ])
            ->pages([
                \App\Plugins\PosIntegration\src\Filament\Pages\ImportPOSProducts::class,
                \App\Plugins\PosIntegration\src\Filament\Pages\SyncPOSPrices::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
