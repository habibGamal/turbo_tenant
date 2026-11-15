<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Services;

use App\Plugins\OrderManagement\src\Enums\OrderStatus;

final class OrderStatusTransitions
{
    /**
     * Get allowed target statuses for a given source status.
     *
     * @return list<OrderStatus>
     */
    public function allowedTransitions(OrderStatus $from): array
    {
        return match ($from) {
            OrderStatus::PENDING => [
                OrderStatus::PREPARING,
                OrderStatus::CANCELLED,
            ],
            OrderStatus::PREPARING => [
                OrderStatus::OUT_FOR_DELIVERY,
                OrderStatus::CANCELLED,
            ],
            OrderStatus::OUT_FOR_DELIVERY => [
                OrderStatus::DELIVERED,
            ],
            OrderStatus::DELIVERED, OrderStatus::CANCELLED => [],
        };
    }

    /**
     * Determine if a transition between two statuses is allowed.
     */
    public function canTransition(OrderStatus $from, OrderStatus $to): bool
    {
        return in_array($to, $this->allowedTransitions($from), true);
    }
}
