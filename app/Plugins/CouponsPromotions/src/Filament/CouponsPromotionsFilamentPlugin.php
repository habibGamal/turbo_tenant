<?php

declare(strict_types=1);

namespace App\Plugins\CouponsPromotions\src\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;

final class CouponsPromotionsFilamentPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'coupons-promotions';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                \App\Plugins\CouponsPromotions\src\Filament\Resources\Coupons\CouponResource::class,
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
