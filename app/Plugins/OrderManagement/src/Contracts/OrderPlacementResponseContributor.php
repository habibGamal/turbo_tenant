<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Contracts;

use App\Core\Contracts\Customer;
use App\Core\Plugins\Contracts\PluginContribution;
use App\Plugins\OrderManagement\src\Models\Order;

/**
 * Extends the order placement JSON response payload.
 * Tag: 'order.placement.response'
 */
interface OrderPlacementResponseContributor extends PluginContribution
{
    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    public function extend(Order $order, Customer $customer, array $response): array;
}
