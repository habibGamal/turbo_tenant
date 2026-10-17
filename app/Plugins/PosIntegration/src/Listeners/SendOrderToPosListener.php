<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\Listeners;

use App\Plugins\OrderManagement\src\Events\OrderCreated;
use App\Plugins\OrderManagement\src\Events\OrderStatusUpdated;
use App\Plugins\PosIntegration\src\Enums\OrderPosStatus;
use App\Plugins\PosIntegration\src\Jobs\SendOrderToPosJob;

final class SendOrderToPosListener
{
    public function handleOrderCreated(OrderCreated $event): void
    {
        $order = $event->order;

        if ($order->pos_status === OrderPosStatus::READY) {
            SendOrderToPosJob::dispatch($order);
        }
    }

    public function handleStatusUpdated(OrderStatusUpdated $event): void
    {
        $order = $event->order;

        if ($order->pos_status === OrderPosStatus::READY) {
            SendOrderToPosJob::dispatch($order);
        }
    }
}
