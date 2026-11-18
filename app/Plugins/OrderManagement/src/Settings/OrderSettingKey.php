<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Settings;

use App\Core\Contracts\SettingDefinition;
use App\Core\Contracts\SettingType;

enum OrderSettingKey: string implements SettingDefinition
{
    case TAX_RATE = 'tax_rate';
    case MIN_ORDER_AMOUNT = 'min_order_amount';
    case FREE_SHIPPING_THRESHOLD = 'free_shipping_threshold';
    case SIMPLIFIED_ADDRESS_ENABLED = 'simplified_address_enabled';
    case ALLOW_MANUAL_ORDER_STATUS_UPDATE = 'allow_manual_order_status_update';

    public function defaultValue(): mixed
    {
        return match ($this) {
            self::TAX_RATE => 0.0,
            self::MIN_ORDER_AMOUNT => 0.0,
            self::FREE_SHIPPING_THRESHOLD => 0.0,
            self::SIMPLIFIED_ADDRESS_ENABLED => false,
            self::ALLOW_MANUAL_ORDER_STATUS_UPDATE => false,
        };
    }

    public function type(): SettingType
    {
        return match ($this) {
            self::TAX_RATE, self::MIN_ORDER_AMOUNT, self::FREE_SHIPPING_THRESHOLD => SettingType::Number,
            self::SIMPLIFIED_ADDRESS_ENABLED, self::ALLOW_MANUAL_ORDER_STATUS_UPDATE => SettingType::Boolean,
        };
    }
}
