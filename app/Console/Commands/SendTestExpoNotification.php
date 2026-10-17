<?php

declare(strict_types=1);

use App\Plugins\PushNotifications\src\Console\Commands\SendTestExpoNotification as PluginSendTestExpoNotification;

if (! class_exists('App\Console\Commands\SendTestExpoNotification', false)) {
    class_alias(PluginSendTestExpoNotification::class, 'App\Console\Commands\SendTestExpoNotification');
}
