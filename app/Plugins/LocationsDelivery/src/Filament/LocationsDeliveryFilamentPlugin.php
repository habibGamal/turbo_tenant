<?php

declare(strict_types=1);

namespace App\Plugins\LocationsDelivery\src\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;

final class LocationsDeliveryFilamentPlugin implements Plugin
{
    public static function make(): static
    {
        return app(self::class);
    }

    public function getId(): string
    {
        return 'locations-delivery';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                Resources\Branches\BranchResource::class,
                Resources\Governorates\GovernorateResource::class,
                Resources\Areas\AreaResource::class,
                Resources\Addresses\AddressResource::class,
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
