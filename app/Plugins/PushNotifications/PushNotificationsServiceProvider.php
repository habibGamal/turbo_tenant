<?php

declare(strict_types=1);

namespace App\Plugins\PushNotifications;

use App\Plugins\PushNotifications\src\Console\Commands\SendScheduledBulkNotifications;
use App\Plugins\PushNotifications\src\Console\Commands\SendTestExpoNotification;
use App\Plugins\PushNotifications\src\DTOs\PushNotificationsConfig;
use App\Plugins\StoreSettings\src\Services\SettingService;
use Illuminate\Support\ServiceProvider;

final class PushNotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Composition Root: Bind typed Config DTO (Pattern 1)
        $this->app->singleton(PushNotificationsConfig::class, function ($app) {
            $settings = $app->make(SettingService::class);

            return new PushNotificationsConfig(
                enabled: (bool) $settings->get('push-notifications.enabled', true)
            );
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                SendScheduledBulkNotifications::class,
                SendTestExpoNotification::class,
            ]);
        }
    }

    public function boot(): void
    {
        if (class_exists(\App\Models\User::class)) {
            \App\Models\User::resolveRelationUsing('notifications', function (\App\Models\User $user) {
                return $user->hasMany(\App\Plugins\PushNotifications\src\Models\UserNotification::class);
            });
            \App\Models\User::resolveRelationUsing('unreadNotifications', function (\App\Models\User $user) {
                return $user->hasMany(\App\Plugins\PushNotifications\src\Models\UserNotification::class)->where('read', false);
            });
        }

        if (file_exists(__DIR__.'/routes/tenant.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/tenant.php');
        }
    }
}
