<?php

declare(strict_types=1);

namespace App\Plugins\GuestCheckout;

use App\Core\Plugins\AbstractTenantPlugin;
use App\Models\Tenant;
use App\Plugins\GuestCheckout\src\Models\GuestUser;
use App\Plugins\GuestCheckout\src\Pipelines\Pipes\GuestCheckoutPermissionPipe;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Pipelines\OrderPipelineRegistry;

final class GuestCheckoutPlugin extends AbstractTenantPlugin
{
    public function bootForTenant(Tenant $tenant): void
    {
        Order::resolveRelationUsing('guestUser', function (Order $order) {
            return $order->belongsTo(GuestUser::class, 'guest_user_id');
        });

        if (app()->bound(OrderPipelineRegistry::class)) {
            app(OrderPipelineRegistry::class)->registerValidationPipe(GuestCheckoutPermissionPipe::class, 325);
        }
    }

    public function onActivate(Tenant $tenant): void
    {
    }

    public function onDeactivate(Tenant $tenant): void
    {
    }

    public function onUninstall(Tenant $tenant): void
    {
    }
}
