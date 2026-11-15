<?php

declare(strict_types=1);

namespace App\Plugins\PaymentProcessing\src\Settings;

use App\Core\Contracts\SettingDefinition;
use App\Core\Contracts\SettingType;
use App\Plugins\PaymentProcessing\src\Services\PaymobService;

enum PaymentSettingKey: string implements SettingDefinition
{
    case COD_FEE = 'cod_fee';
    case ONLINE_PAYMENTS_ENABLED = 'online_payments_enabled';
    case ACTIVE_PAYMENT_GATEWAY = 'active_payment_gateway';
    case PAYMOB_BASE_URL = 'paymob_base_url';
    case PAYMOB_SECRET_KEY = 'paymob_secret_key';
    case PAYMOB_PUBLIC_KEY = 'paymob_public_key';
    case PAYMOB_INTEGRATION_IDS = 'paymob_integration_ids';
    case PAYMOB_HMAC_SECRET = 'paymob_hmac_secret';
    case PAYMOB_CURRENCY = 'paymob_currency';
    case PAYMOB_MODE = 'paymob_mode';
    case KASHIER_MERCHANT_ID = 'kashier_merchant_id';
    case KASHIER_API_KEY = 'kashier_api_key';
    case KASHIER_SECRET_KEY = 'kashier_secret_key';
    case KASHIER_MODE = 'kashier_mode';
    case KASHIER_CURRENCY = 'kashier_currency';
    case KASHIER_ALLOWED_METHODS = 'kashier_allowed_methods';

    public function defaultValue(): mixed
    {
        return match ($this) {
            self::COD_FEE => 0.0,
            self::ONLINE_PAYMENTS_ENABLED => true,
            self::ACTIVE_PAYMENT_GATEWAY => PaymobService::ID,
            self::PAYMOB_BASE_URL => 'https://accept.paymob.com',
            self::PAYMOB_CURRENCY => 'EGP',
            self::PAYMOB_MODE => 'test',
            self::KASHIER_MODE => 'test',
            self::KASHIER_CURRENCY => 'EGP',
            self::KASHIER_ALLOWED_METHODS => 'card',
            default => null,
        };
    }

    public function type(): SettingType
    {
        return match ($this) {
            self::COD_FEE => SettingType::Number,
            self::ONLINE_PAYMENTS_ENABLED => SettingType::Boolean,
            default => SettingType::Text,
        };
    }
}
