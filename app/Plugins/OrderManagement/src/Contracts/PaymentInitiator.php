<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Contracts;

use App\Plugins\OrderManagement\src\Models\Order;

/**
 * Interface for initiating payments for orders.
 */
interface PaymentInitiator
{
    /**
     * Get the unique identifier for this gateway.
     */
    public function getGatewayId(): string;

    /**
     * Create a payment intention for an order.
     *
     * @param  Order  $order  The order to create payment for
     * @param  array  $billingData  Customer billing information
     * @param  string  $redirectionUrl  URL to redirect after successful payment
     * @param  string  $notificationUrl  Webhook URL for payment notifications
     */
    public function createPaymentIntention(
        Order $order,
        array $billingData,
        string $redirectionUrl,
        string $notificationUrl
    ): PaymentIntention;
}
