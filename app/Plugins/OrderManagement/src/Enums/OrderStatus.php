<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasColor, HasIcon, HasLabel
{
    case PENDING = 'pending';
    case PREPARING = 'processing';
    case OUT_FOR_DELIVERY = 'out_for_delivery';
    case DELIVERED = 'completed';
    case CANCELLED = 'cancelled';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PENDING => 'قيد الانتظار',
            self::PREPARING => 'قيد التحضير',
            self::OUT_FOR_DELIVERY => 'خارج للتوصيل',
            self::DELIVERED => 'مكتمل (تم التوصيل)',
            self::CANCELLED => 'ملغي',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::PREPARING => 'warning',
            self::OUT_FOR_DELIVERY => 'info',
            self::DELIVERED => 'success',
            self::CANCELLED => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::PENDING => 'heroicon-o-clock',
            self::PREPARING => 'heroicon-o-arrow-path',
            self::OUT_FOR_DELIVERY => 'heroicon-o-truck',
            self::DELIVERED => 'heroicon-o-check-circle',
            self::CANCELLED => 'heroicon-o-x-circle',
        };
    }

    public function label(): string
    {
        return $this->getLabel() ?? $this->value;
    }
}
