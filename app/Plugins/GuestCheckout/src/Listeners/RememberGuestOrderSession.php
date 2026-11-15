<?php

declare(strict_types=1);

namespace App\Plugins\GuestCheckout\src\Listeners;

use App\Plugins\OrderManagement\src\Events\OrderCreated;

final class RememberGuestOrderSession
{
    public function handle(OrderCreated $event): void
    {
        $order = $event->order;

        if ($order->guest_user_id !== null) {
            if (request()->hasSession()) {
                request()->session()->push('guest_order_ids', $order->id);
            } elseif (app()->bound('session') && session()->isStarted()) {
                session()->push('guest_order_ids', $order->id);
            }
        }
    }
}
