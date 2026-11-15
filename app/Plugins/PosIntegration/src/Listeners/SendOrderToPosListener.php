<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\Listeners;

use App\Plugins\OrderManagement\src\Enums\OrderPosStatus;
use App\Plugins\OrderManagement\src\Events\OrderCreated;
use App\Plugins\OrderManagement\src\Events\OrderFinalized;
use App\Plugins\OrderManagement\src\Events\OrderPaymentCompleted;
use App\Plugins\OrderManagement\src\Events\OrderStatusUpdated;
use App\Plugins\PosIntegration\src\Services\OrderDeliveryDispatcher;
use Illuminate\Validation\ValidationException;

final class SendOrderToPosListener
{
    public function handleOrderCreated(OrderCreated $event): void
    {
        $order = $event->order;

        if ($order->pos_status === OrderPosStatus::READY) {
            $this->enqueue($order);
        }
    }

    public function handleStatusUpdated(OrderStatusUpdated $event): void
    {
        $order = $event->order;

        if ($order->pos_status === OrderPosStatus::READY) {
            $this->enqueue($order);
        }
    }

    public function handleOrderPaymentCompleted(OrderPaymentCompleted $event): void
    {
        $this->enqueue($event->order);
    }

    public function handleOrderFinalized(OrderFinalized $event): void
    {
        $this->enqueue($event->order);
    }

    private function enqueue(\App\Plugins\OrderManagement\src\Models\Order $order): void
    {
        try {
            app(OrderDeliveryDispatcher::class)->enqueue($order);
        } catch (ValidationException) {
            // Duplicate events and ineligible orders must not break checkout/payment handling.
        }
    }
}
