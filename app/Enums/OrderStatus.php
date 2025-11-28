<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case PREPARING = 'processing';
    case OUT_FOR_DELIVERY = 'out_for_delivery';
    case DELIVERED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'قيد الانتظار',
            self::PREPARING => 'قيد التحضير',
            self::OUT_FOR_DELIVERY => 'في الطريق',
            self::DELIVERED => 'تم التوصيل',
            self::CANCELLED => 'ملغي',
        };
    }
}
