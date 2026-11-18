<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Contracts;

use App\Core\Plugins\Contracts\PluginContribution;
use App\Plugins\OrderManagement\src\Models\Order;

/**
 * Extends the order payment status JSON response payload.
 * Tag: 'order.payment-status.response'
 */
interface OrderPaymentStatusResponseContributor extends PluginContribution
{
    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    public function extend(Order $order, array $response): array;
}
