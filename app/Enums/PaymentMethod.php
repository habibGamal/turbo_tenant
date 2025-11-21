<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case CARD = 'card';
    case WALLET = 'wallet';
    case COD = 'cod'; // Cash on Delivery
    case KIOSK = 'kiosk';
    case BANK_TRANSFER = 'bank_transfer';

    public function label(): string
    {
        return match ($this) {
            self::CARD => 'Credit/Debit Card',
            self::WALLET => 'Mobile Wallet',
            self::COD => 'Cash on Delivery',
            self::KIOSK => 'Kiosk Payment',
            self::BANK_TRANSFER => 'Bank Transfer',
        };
    }

    public function requiresOnlinePayment(): bool
    {
        return match ($this) {
            self::CARD, self::WALLET, self::KIOSK, self::BANK_TRANSFER => true,
            self::COD => false,
        };
    }
}
