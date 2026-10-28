<?php

declare(strict_types=1);

namespace App\Plugins\PaymentProcessing\src\Services;

use App\Enums\PaymentStatus;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\PaymentProcessing\src\Contracts\PaymentCallbackServiceInterface;

final readonly class PaymentCallbackService implements PaymentCallbackServiceInterface
{
    /**
     * Read the order payment status from the database and map to SPA status.
     * NEVER modifies payment_status, status, or pos_status.
     *
     * @return string 'success'|'failed'|'pending'
     */
    public function handleCallback(int $orderId): string
    {
        $order = Order::findOrFail($orderId);

        $dbStatus = $order->payment_status instanceof PaymentStatus
            ? $order->payment_status->value
            : (string) ($order->payment_status ?? '');

        return match ($dbStatus) {
            PaymentStatus::COMPLETED->value => 'success',
            PaymentStatus::FAILED->value => 'failed',
            default => 'pending',
        };
    }
}
