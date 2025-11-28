<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\OrderPosStatus;
use App\Jobs\SendOrderToPosJob;
use App\Jobs\SendOrderUpdateMailJob;
use App\Models\Order;

class OrderObserver
{
    public function created(Order $order): void
    {
        SendOrderUpdateMailJob::dispatch($order);
    }

    public function updated(Order $order): void
    {
        if ($order->isDirty('pos_status') && $order->pos_status === OrderPosStatus::READY) {
            SendOrderToPosJob::dispatch($order);
        }

        if ($order->isDirty('status')) {
            SendOrderUpdateMailJob::dispatch($order);
        }
    }
}
