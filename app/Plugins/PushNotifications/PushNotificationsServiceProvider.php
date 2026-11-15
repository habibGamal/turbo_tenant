<?php

declare(strict_types=1);

namespace App\Plugins\PushNotifications;

use App\Plugins\PushNotifications\src\Console\Commands\SendScheduledBulkNotifications;
use App\Plugins\PushNotifications\src\Console\Commands\SendTestExpoNotification;
use Illuminate\Support\ServiceProvider;

final class PushNotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
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
            \App\Models\User::resolveRelationUsing('pushNotifications', function (\App\Models\User $user) {
                return $user->hasMany(src\Models\UserNotification::class);
            });
            \App\Models\User::resolveRelationUsing('unreadPushNotifications', function (\App\Models\User $user) {
                return $user->hasMany(src\Models\UserNotification::class)->where('read', false);
            });
        }

        if (file_exists(__DIR__.'/routes/tenant.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/tenant.php');
        }
    }
}
