<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Events;

use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Pipelines\Context\OrderValidationContext;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Order $order,
        public ?OrderValidationContext $validationContext = null
    ) {}
}
