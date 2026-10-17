<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration;

use App\Core\Plugins\AbstractTenantPlugin;
use App\Models\Tenant;
use App\Plugins\OrderManagement\src\Events\OrderCreated;
use App\Plugins\OrderManagement\src\Events\OrderStatusUpdated;
use App\Plugins\PosIntegration\src\Listeners\SendOrderToPosListener;
use Illuminate\Support\Facades\Event;

final class PosIntegrationPlugin extends AbstractTenantPlugin
{
    public function bootForTenant(Tenant $tenant): void
    {
        Event::listen(OrderCreated::class, [SendOrderToPosListener::class, 'handleOrderCreated']);
        Event::listen(OrderStatusUpdated::class, [SendOrderToPosListener::class, 'handleStatusUpdated']);
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
