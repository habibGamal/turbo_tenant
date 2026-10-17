<?php

declare(strict_types=1);

use App\Plugins\PushNotifications\src\Console\Commands\SendScheduledBulkNotifications as PluginSendScheduledBulkNotifications;

if (! class_exists('App\Console\Commands\SendScheduledBulkNotifications', false)) {
    class_alias(PluginSendScheduledBulkNotifications::class, 'App\Console\Commands\SendScheduledBulkNotifications');
}
