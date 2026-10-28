<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasColor, HasIcon, HasLabel
{
    case CARD = 'card';
    case WALLET = 'wallet';
    case COD = 'cod'; // Cash on Delivery
    case KIOSK = 'kiosk';
    case BANK_TRANSFER = 'bank_transfer';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::CARD => 'بطاقة ائتمانية / بنكية',
            self::WALLET => 'محفظة إلكترونية',
            self::COD => 'الدفع عند الاستلام',
            self::KIOSK => 'مدفوعات كشك / فوري',
            self::BANK_TRANSFER => 'تحويل بنكي',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::CARD => 'primary',
            self::WALLET => 'success',
            self::COD => 'warning',
            self::KIOSK => 'info',
            self::BANK_TRANSFER => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::CARD => 'heroicon-o-credit-card',
            self::WALLET => 'heroicon-o-device-phone-mobile',
            self::COD => 'heroicon-o-banknotes',
            self::KIOSK => 'heroicon-o-building-storefront',
            self::BANK_TRANSFER => 'heroicon-o-building-library',
        };
    }

    public function label(): string
    {
        return $this->getLabel() ?? $this->value;
    }

    public function requiresOnlinePayment(): bool
    {
        return match ($this) {
            self::CARD, self::WALLET, self::KIOSK, self::BANK_TRANSFER => true,
            self::COD => false,
        };
    }

    public function posMapping(): string
    {
        return match ($this) {
            self::CARD => 'card',
            self::COD => 'cash',
            self::WALLET => 'POS-WALLET',
            self::KIOSK => 'POS-KIOSK',
            self::BANK_TRANSFER => 'POS-BANK-TRANSFER',
        };
    }
}
