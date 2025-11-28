<?php

declare(strict_types=1);

namespace App\Enums;

enum SettingKey: string
{
    case SVG_LOGO = 'svg_logo';
    case SITE_NAME = 'site_name';
    case SITE_DESCRIPTION = 'site_description';
    case SITE_LOGO = 'site_logo';
    case SITE_FAVICON = 'site_favicon';
    case CONTACT_EMAIL = 'contact_email';
    case CONTACT_PHONE = 'contact_phone';
    case CONTACT_ADDRESS = 'contact_address';
    case SOCIAL_FACEBOOK = 'social_facebook';
    case SOCIAL_INSTAGRAM = 'social_instagram';
    case SOCIAL_TWITTER = 'social_twitter';
    case CURRENCY = 'currency';
    case TAX_RATE = 'tax_rate';
    case COD_FEE = 'cod_fee';
    case MIN_ORDER_AMOUNT = 'min_order_amount';
    case MAINTENANCE_MODE = 'maintenance_mode';
    case PRODUCTS_REPO_LINK = 'products_repo_link';
    case GOOGLE_CLIENT_ID = 'google_client_id';
    case GOOGLE_CLIENT_SECRET = 'google_client_secret';
    case GOOGLE_REDIRECT_URL = 'google_redirect_url';
    case PAYMOB_BASE_URL = 'paymob_base_url';
    case PAYMOB_SECRET_KEY = 'paymob_secret_key';
    case PAYMOB_PUBLIC_KEY = 'paymob_public_key';
    case PAYMOB_INTEGRATION_IDS = 'paymob_integration_ids';
    case PAYMOB_HMAC_SECRET = 'paymob_hmac_secret';
    case PAYMOB_CURRENCY = 'paymob_currency';
    case PAYMOB_MODE = 'paymob_mode';
    case IMAGE_PLACEHOLDER = 'image_placeholder';
    case PRODUCT_SHOW_CARDS = 'product_show_cards';
    case WORK_TIMES = 'work_times';
    case ACCEPT_ORDERS_AFTER_WORK_TIMES = 'accept_orders_after_work_times';
    case FACEBOOK_APP_ID = 'facebook_app_id';

    // Mail Settings
    case MAIL_MAILER = 'mail_mailer';
    case MAIL_HOST = 'mail_host';
    case MAIL_PORT = 'mail_port';
    case MAIL_USERNAME = 'mail_username';
    case MAIL_PASSWORD = 'mail_password';
    case MAIL_ENCRYPTION = 'mail_encryption';
    case MAIL_FROM_ADDRESS = 'mail_from_address';
    case MAIL_FROM_NAME = 'mail_from_name';

    public function label(): string
    {
        return match ($this) {
            self::SITE_NAME => 'Site Name',
            self::SITE_DESCRIPTION => 'Site Description',
            self::SITE_LOGO => 'Site Logo',
            self::SITE_FAVICON => 'Site Favicon',
            self::CONTACT_EMAIL => 'Contact Email',
            self::CONTACT_PHONE => 'Contact Phone',
            self::CONTACT_ADDRESS => 'Contact Address',
            self::SOCIAL_FACEBOOK => 'Facebook URL',
            self::SOCIAL_INSTAGRAM => 'Instagram URL',
            self::SOCIAL_TWITTER => 'Twitter URL',
            self::CURRENCY => 'Currency',
            self::TAX_RATE => 'Tax Rate (%)',
            self::COD_FEE => 'COD Fee',
            self::MIN_ORDER_AMOUNT => 'Minimum Order Amount',
            self::MAINTENANCE_MODE => 'Maintenance Mode',
            self::PRODUCTS_REPO_LINK => 'Products Repository URL',
            self::GOOGLE_CLIENT_ID => 'Google Client ID',
            self::GOOGLE_CLIENT_SECRET => 'Google Client Secret',
            self::GOOGLE_REDIRECT_URL => 'Google Redirect URL',
            self::PAYMOB_BASE_URL => 'Paymob Base URL',
            self::PAYMOB_SECRET_KEY => 'Paymob Secret Key',
            self::PAYMOB_PUBLIC_KEY => 'Paymob Public Key',
            self::PAYMOB_INTEGRATION_IDS => 'Paymob Integration IDs',
            self::PAYMOB_HMAC_SECRET => 'Paymob HMAC Secret',
            self::PAYMOB_CURRENCY => 'Paymob Currency',
            self::PAYMOB_MODE => 'Paymob Mode',
            self::IMAGE_PLACEHOLDER => 'Image Placeholder',
            self::SVG_LOGO => 'SVG Logo',
            self::PRODUCT_SHOW_CARDS => 'Product Show Cards',
            self::WORK_TIMES => 'Work Times',
            self::ACCEPT_ORDERS_AFTER_WORK_TIMES => 'Accept Orders After Work Times',
            self::FACEBOOK_APP_ID => 'Facebook App ID',
            self::MAIL_MAILER => 'Mail Mailer',
            self::MAIL_HOST => 'Mail Host',
            self::MAIL_PORT => 'Mail Port',
            self::MAIL_USERNAME => 'Mail Username',
            self::MAIL_PASSWORD => 'Mail Password',
            self::MAIL_ENCRYPTION => 'Mail Encryption',
            self::MAIL_FROM_ADDRESS => 'Mail From Address',
            self::MAIL_FROM_NAME => 'Mail From Name',
        };
    }

    public function defaultValue(): ?string
    {
        return match ($this) {
            self::SITE_NAME => 'Restaurant',
            self::SITE_DESCRIPTION => 'Your favorite restaurant',
            self::CONTACT_ADDRESS => '123 Restaurant St, City, State 12345',
            self::SOCIAL_FACEBOOK => 'https://facebook.com',
            self::SOCIAL_INSTAGRAM => 'https://instagram.com',
            self::SOCIAL_TWITTER => 'https://twitter.com',
            self::CURRENCY => 'EGP',
            self::TAX_RATE => '0',
            self::COD_FEE => '0',
            self::MIN_ORDER_AMOUNT => '0',
            self::MAINTENANCE_MODE => 'false',
            self::PRODUCTS_REPO_LINK => null,
            self::PAYMOB_BASE_URL => 'https://accept.paymob.com',
            self::PAYMOB_CURRENCY => 'EGP',
            self::PAYMOB_MODE => 'test',
            self::IMAGE_PLACEHOLDER => '/images/placeholder-food.svg',
            self::SVG_LOGO => '/images/placeholder-food.svg',
            self::PRODUCT_SHOW_CARDS => '[]',
            self::WORK_TIMES => '[]',
            self::ACCEPT_ORDERS_AFTER_WORK_TIMES => 'true',
            self::FACEBOOK_APP_ID => null,
            self::MAIL_MAILER => 'smtp',
            self::MAIL_HOST => 'smtp.mailtrap.io',
            self::MAIL_PORT => '2525',
            self::MAIL_ENCRYPTION => 'tls',
            default => null,
        };
    }

    public function type(): string
    {
        return match ($this) {
            self::SITE_NAME, self::SITE_DESCRIPTION, self::SITE_LOGO, self::SITE_FAVICON,
            self::CONTACT_EMAIL, self::CONTACT_PHONE, self::CONTACT_ADDRESS,
            self::SOCIAL_FACEBOOK, self::SOCIAL_INSTAGRAM, self::SOCIAL_TWITTER,
            self::FACEBOOK_APP_ID => 'text',
            self::CURRENCY => 'text',
            self::TAX_RATE, self::COD_FEE, self::MIN_ORDER_AMOUNT => 'numeric',
            self::MAINTENANCE_MODE => 'boolean',
            self::PRODUCTS_REPO_LINK, self::GOOGLE_CLIENT_ID,
            self::GOOGLE_CLIENT_SECRET, self::GOOGLE_REDIRECT_URL,
            self::PAYMOB_BASE_URL, self::PAYMOB_SECRET_KEY, self::PAYMOB_PUBLIC_KEY,
            self::PAYMOB_INTEGRATION_IDS, self::PAYMOB_HMAC_SECRET,
            self::PAYMOB_CURRENCY, self::PAYMOB_MODE, self::IMAGE_PLACEHOLDER,
            self::MAIL_MAILER, self::MAIL_HOST, self::MAIL_USERNAME, self::MAIL_PASSWORD,
            self::MAIL_ENCRYPTION, self::MAIL_FROM_ADDRESS, self::MAIL_FROM_NAME => 'text',
            self::PRODUCT_SHOW_CARDS => 'json',
            self::WORK_TIMES => 'json',
            self::ACCEPT_ORDERS_AFTER_WORK_TIMES => 'boolean',
            self::MAIL_PORT => 'numeric',
        };
    }
}
