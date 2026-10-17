<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Events;

use App\Enums\OrderStatus;
use App\Plugins\OrderManagement\src\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class OrderStatusUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Order $order,
        public ?OrderStatus $previousStatus = null
    ) {}
}
