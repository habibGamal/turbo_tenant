<?php

declare(strict_types=1);

namespace App\Plugins\PushNotifications;

use App\Core\Plugins\AbstractTenantPlugin;
use App\Models\Tenant;
use App\Plugins\PushNotifications\src\Listeners\SendOrderStatusNotificationListener;
use Illuminate\Support\Facades\Event;

final class PushNotificationsPlugin extends AbstractTenantPlugin
{
    public function bootForTenant(Tenant $tenant): void
    {
        // Subscribe to Order lifecycle events
        if (class_exists(\App\Plugins\OrderManagement\src\Events\OrderCreated::class)) {
            Event::listen(
                \App\Plugins\OrderManagement\src\Events\OrderCreated::class,
                SendOrderStatusNotificationListener::class
            );
        }

        if (class_exists(\App\Plugins\OrderManagement\src\Events\OrderStatusUpdated::class)) {
            Event::listen(
                \App\Plugins\OrderManagement\src\Events\OrderStatusUpdated::class,
                SendOrderStatusNotificationListener::class
            );
        }

        if (class_exists('App\Events\OrderCreated')) {
            Event::listen(
                'App\Events\OrderCreated',
                SendOrderStatusNotificationListener::class
            );
        }
    }

    public function onActivate(Tenant $tenant): void
    {
        // Post-activation setup
    }

    public function onDeactivate(Tenant $tenant): void
    {
        // Deactivation cleanup
    }

    public function onUninstall(Tenant $tenant): void
    {
        // Pre-purge cleanup
    }
}
