<?php

declare(strict_types=1);

namespace App\Plugins\LocationsDelivery\src\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;

final class LocationsDeliveryFilamentPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'locations-delivery';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                \App\Plugins\LocationsDelivery\src\Filament\Resources\Branches\BranchResource::class,
                \App\Plugins\LocationsDelivery\src\Filament\Resources\Governorates\GovernorateResource::class,
                \App\Plugins\LocationsDelivery\src\Filament\Resources\Areas\AreaResource::class,
                \App\Plugins\LocationsDelivery\src\Filament\Resources\Addresses\AddressResource::class,
            ])

            ->pages([
                //
            ]);
    }


    public function boot(Panel $panel): void
    {
        //
    }
}
