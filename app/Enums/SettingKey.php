<?php

declare(strict_types=1);

namespace App\Enums;

use App\Core\Contracts\SettingDefinition;
use App\Core\Contracts\SettingType;

enum SettingKey: string implements SettingDefinition
{
    case SVG_LOGO = 'svg_logo';
    case SITE_NAME = 'site_name';
    case SITE_NAME_AR = 'site_name_ar';
    case SITE_DESCRIPTION = 'site_description';
    case SITE_LOGO = 'site_logo';
    case SITE_FAVICON = 'site_favicon';
    case CONTACT_EMAIL = 'contact_email';
    case CONTACT_PHONE = 'contact_phone';
    case WHATSAPP_NUMBER = 'whatsapp_number';
    case CONTACT_ADDRESS = 'contact_address';
    case SOCIAL_FACEBOOK = 'social_facebook';
    case SOCIAL_INSTAGRAM = 'social_instagram';
    case SOCIAL_TWITTER = 'social_twitter';
    case SOCIAL_LINKS = 'social_links';
    case CURRENCY = 'currency';
    case MAINTENANCE_MODE = 'maintenance_mode';
    case GOOGLE_CLIENT_ID = 'google_client_id';
    case GOOGLE_CLIENT_SECRET = 'google_client_secret';
    case GOOGLE_REDIRECT_URL = 'google_redirect_url';
    case IMAGE_PLACEHOLDER = 'image_placeholder';
    case PRODUCT_SHOW_CARDS = 'product_show_cards';
    case WORK_TIMES = 'work_times';
    case ACCEPT_ORDERS_AFTER_WORK_TIMES = 'accept_orders_after_work_times';
    case THEME = 'theme';

    // Mail Settings
    case MAIL_MAILER = 'mail_mailer';
    case MAIL_HOST = 'mail_host';
    case MAIL_PORT = 'mail_port';
    case MAIL_USERNAME = 'mail_username';
    case MAIL_PASSWORD = 'mail_password';
    case MAIL_ENCRYPTION = 'mail_encryption';
    case MAIL_FROM_ADDRESS = 'mail_from_address';
    case MAIL_FROM_NAME = 'mail_from_name';

    public function defaultValue(): mixed
    {
        return match ($this) {
            self::SITE_NAME => 'Restaurant',
            self::SITE_DESCRIPTION => 'Your favorite restaurant',
            self::CONTACT_ADDRESS => '123 Restaurant St, City, State 12345',
            self::SOCIAL_FACEBOOK => 'https://facebook.com',
            self::SOCIAL_INSTAGRAM => 'https://instagram.com',
            self::SOCIAL_TWITTER => 'https://twitter.com',
            self::SOCIAL_LINKS => [],
            self::CURRENCY => 'EGP',
            self::MAINTENANCE_MODE => false,
            self::IMAGE_PLACEHOLDER => '/images/placeholder-food.svg',
            self::SVG_LOGO => '/images/placeholder-food.svg',
            self::PRODUCT_SHOW_CARDS => [],
            self::WORK_TIMES => [],
            self::THEME => [],
            self::ACCEPT_ORDERS_AFTER_WORK_TIMES => true,
            self::MAIL_MAILER => 'smtp',
            self::MAIL_HOST => 'smtp.mailtrap.io',
            self::MAIL_PORT => 2525,
            self::MAIL_ENCRYPTION => 'tls',
            default => null,
        };
    }

    public function type(): SettingType
    {
        return match ($this) {
            self::MAINTENANCE_MODE, self::ACCEPT_ORDERS_AFTER_WORK_TIMES => SettingType::Boolean,
            self::SOCIAL_LINKS, self::PRODUCT_SHOW_CARDS, self::WORK_TIMES, self::THEME => SettingType::Json,
            self::MAIL_PORT => SettingType::Number,
            default => SettingType::Text,
        };
    }
}
