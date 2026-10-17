<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Filament;

use App\Plugins\OrderManagement\src\Filament\Resources\Orders\OrderResource;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class OrderManagementFilamentPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'order-management';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                OrderResource::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
