<?php

declare(strict_types=1);

namespace App\Plugins\MetaMarketing\src\Settings;

use App\Core\Contracts\SettingDefinition;
use App\Core\Contracts\SettingType;

enum MetaSettingKey: string implements SettingDefinition
{
    // Kept as 'facebook_app_id' so existing tenants keep their configured Pixel ID.
    case PIXEL_ID = 'facebook_app_id';
    case DEBUG_MODE = 'meta_debug_mode';
    case CAPI_ENABLED = 'meta_capi_enabled';
    // Stored encrypted (Crypt::encryptString). Read it through MetaMarketingConfig only.
    case CAPI_ACCESS_TOKEN = 'meta_capi_access_token';
    case CAPI_TEST_EVENT_CODE = 'meta_capi_test_event_code';
    case CATALOG_ENABLED = 'meta_catalog_enabled';
    case CATALOG_FEED_TOKEN = 'meta_catalog_feed_token';
    case CATALOG_BRAND = 'meta_catalog_brand';

    public function defaultValue(): mixed
    {
        return match ($this) {
            self::DEBUG_MODE, self::CAPI_ENABLED, self::CATALOG_ENABLED => false,
            default => null,
        };
    }

    public function type(): SettingType
    {
        return match ($this) {
            self::DEBUG_MODE, self::CAPI_ENABLED, self::CATALOG_ENABLED => SettingType::Boolean,
            default => SettingType::Text,
        };
    }
}
