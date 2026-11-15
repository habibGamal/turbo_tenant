<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\Filament;

use App\Plugins\PosIntegration\src\Filament\RelationManagers\OrderDeliveryAttemptsRelationManager;
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
            // Panel registration runs before tenant activation makes this relation visible.
            ->livewireComponents([
                OrderDeliveryAttemptsRelationManager::class,
            ])
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
