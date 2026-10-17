<?php

declare(strict_types=1);

namespace App\Plugins\GuestCheckout\src\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;

final class GuestCheckoutFilamentPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'guest-checkout';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                \App\Plugins\GuestCheckout\src\Filament\Resources\GuestUsers\GuestUserResource::class,
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
