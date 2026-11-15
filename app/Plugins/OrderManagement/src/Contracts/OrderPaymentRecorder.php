<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Contracts;

use App\Plugins\OrderManagement\src\Models\Order;

interface OrderPaymentRecorder
{
    /**
     * Persists payment-related attributes on the order.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function record(Order $order, array $attributes): void;
}
