<?php

declare(strict_types=1);

namespace App\Plugins\PushNotifications\src\Listeners;

use App\Plugins\PushNotifications\src\Jobs\SendOrderStatusNotificationJob;

class SendOrderStatusNotificationListener
{
    public function handle(object $event): void
    {
        if (isset($event->order)) {
            SendOrderStatusNotificationJob::dispatch($event->order);
        }
    }
}
