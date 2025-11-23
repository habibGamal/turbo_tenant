<?php

declare(strict_types=1);

namespace App\Enums;

enum SettingKey: string
{
    case SITE_NAME = 'site_name';
    case SITE_DESCRIPTION = 'site_description';
    case SITE_LOGO = 'site_logo';
    case SITE_FAVICON = 'site_favicon';
    case CONTACT_EMAIL = 'contact_email';
    case CONTACT_PHONE = 'contact_phone';
    case CURRENCY = 'currency';
    case TAX_RATE = 'tax_rate';
    case DELIVERY_FEE = 'delivery_fee';
    case MIN_ORDER_AMOUNT = 'min_order_amount';
    case MAINTENANCE_MODE = 'maintenance_mode';
    case PRODUCTS_REPO_LINK = 'products_repo_link';
    case GOOGLE_CLIENT_ID = 'google_client_id';
    case GOOGLE_CLIENT_SECRET = 'google_client_secret';
    case GOOGLE_REDIRECT_URL = 'google_redirect_url';

    public function label(): string
    {
        return match ($this) {
            self::SITE_NAME => 'Site Name',
            self::SITE_DESCRIPTION => 'Site Description',
            self::SITE_LOGO => 'Site Logo',
            self::SITE_FAVICON => 'Site Favicon',
            self::CONTACT_EMAIL => 'Contact Email',
            self::CONTACT_PHONE => 'Contact Phone',
            self::CURRENCY => 'Currency',
            self::TAX_RATE => 'Tax Rate (%)',
            self::DELIVERY_FEE => 'Delivery Fee',
            self::MIN_ORDER_AMOUNT => 'Minimum Order Amount',
            self::MAINTENANCE_MODE => 'Maintenance Mode',
            self::PRODUCTS_REPO_LINK => 'Products Repository URL',
            self::GOOGLE_CLIENT_ID => 'Google Client ID',
            self::GOOGLE_CLIENT_SECRET => 'Google Client Secret',
            self::GOOGLE_REDIRECT_URL => 'Google Redirect URL',
        };
    }

    public function defaultValue(): ?string
    {
        return match ($this) {
            self::SITE_NAME => 'Restaurant',
            self::SITE_DESCRIPTION => 'Your favorite restaurant',
            self::CURRENCY => 'EGP',
            self::TAX_RATE => '0',
            self::DELIVERY_FEE => '0',
            self::MIN_ORDER_AMOUNT => '0',
            self::MAINTENANCE_MODE => 'false',
            self::PRODUCTS_REPO_LINK => null,
            default => null,
        };
    }

    public function type(): string
    {
        return match ($this) {
            self::SITE_NAME, self::SITE_DESCRIPTION, self::SITE_LOGO, self::SITE_FAVICON,
            self::CONTACT_EMAIL, self::CONTACT_PHONE, self::CURRENCY => 'text',
            self::TAX_RATE, self::DELIVERY_FEE, self::MIN_ORDER_AMOUNT => 'numeric',
            self::MAINTENANCE_MODE => 'boolean',
            self::PRODUCTS_REPO_LINK, self::GOOGLE_CLIENT_ID,
            self::GOOGLE_CLIENT_SECRET, self::GOOGLE_REDIRECT_URL => 'text',
        };
    }
}
