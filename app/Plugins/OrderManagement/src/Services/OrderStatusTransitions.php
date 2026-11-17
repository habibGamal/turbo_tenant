<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Services;

use App\Plugins\OrderManagement\src\Enums\OrderStatus;
use App\Plugins\OrderManagement\src\Enums\OrderType;

final class OrderStatusTransitions
{
    /**
     * Get allowed target statuses for a given source status.
     *
     * Takeaway orders are not delivered, so the POS completes them straight
     * from processing; the delivery path stays allowed for them too.
     *
     * @return list<OrderStatus>
     */
    public function allowedTransitions(OrderStatus $from, ?OrderType $type = null): array
    {
        if ($from === OrderStatus::PREPARING && $type === OrderType::WEB_TAKEAWAY) {
            return [
                OrderStatus::OUT_FOR_DELIVERY,
                OrderStatus::DELIVERED,
                OrderStatus::CANCELLED,
            ];
        }

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
    public function canTransition(OrderStatus $from, OrderStatus $to, ?OrderType $type = null): bool
    {
        return in_array($to, $this->allowedTransitions($from, $type), true);
    }
}
