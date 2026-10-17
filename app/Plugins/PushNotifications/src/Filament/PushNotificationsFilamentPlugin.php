<?php

declare(strict_types=1);

namespace App\Plugins\PushNotifications\src\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;

final class PushNotificationsFilamentPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'push-notifications';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                \App\Plugins\PushNotifications\src\Filament\Resources\BulkNotifications\BulkNotificationResource::class,
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
