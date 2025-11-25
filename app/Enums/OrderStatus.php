<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case PREPARING = 'preparing';
    case READY = 'ready';
    case OUT_FOR_DELIVERY = 'out_for_delivery';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'قيد الانتظار',
            self::CONFIRMED => 'مؤكد',
            self::PREPARING => 'قيد التحضير',
            self::READY => 'جاهز',
            self::OUT_FOR_DELIVERY => 'في الطريق',
            self::DELIVERED => 'تم التوصيل',
            self::CANCELLED => 'ملغي',
        };
    }
}
