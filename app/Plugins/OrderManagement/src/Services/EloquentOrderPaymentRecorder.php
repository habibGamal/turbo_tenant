<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Services;

use App\Plugins\OrderManagement\src\Contracts\OrderPaymentRecorder;
use App\Plugins\OrderManagement\src\Models\Order;

final class EloquentOrderPaymentRecorder implements OrderPaymentRecorder
{
    /**
     * Persists payment-related attributes on the order.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function record(Order $order, array $attributes): void
    {
        $order->update($attributes);
    }
}
