<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum OrderType: string implements HasColor, HasIcon, HasLabel
{
    case WEB_DELIVERY = 'web_delivery';
    case WEB_TAKEAWAY = 'web_takeaway';
    case POS = 'pos';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::WEB_DELIVERY => 'توصيل (ويب)',
            self::WEB_TAKEAWAY => 'استلام من الفرع',
            self::POS => 'نقطة بيع (POS)',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::WEB_DELIVERY => 'success',
            self::WEB_TAKEAWAY => 'info',
            self::POS => 'warning',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::WEB_DELIVERY => 'heroicon-o-truck',
            self::WEB_TAKEAWAY => 'heroicon-o-building-storefront',
            self::POS => 'heroicon-o-computer-desktop',
        };
    }

    public function label(): string
    {
        return $this->getLabel() ?? $this->value;
    }
}
