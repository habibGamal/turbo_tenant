<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\Filament;

use App\Plugins\PosIntegration\src\Filament\Widgets\PosFailedOrdersTable;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class PosIntegrationFilamentPlugin implements Plugin
{
    public static function make(): static
    {
        return app(self::class);
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
                Pages\ImportPOSProducts::class,
                Pages\SyncPOSPrices::class,
            ])
            ->widgets([
                PosFailedOrdersTable::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
