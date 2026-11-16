<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\Settings;

use App\Core\Contracts\SettingDefinition;
use App\Core\Contracts\SettingType;

enum PosSettingKey: string implements SettingDefinition
{
    case PRODUCTS_REPO_LINK = 'products_repo_link';
    case PRODUCTS_REPO_API_KEY = 'products_repo_api_key';

    public function defaultValue(): mixed
    {
        return match ($this) {
            self::PRODUCTS_REPO_LINK => null,
            self::PRODUCTS_REPO_API_KEY => null,
        };
    }

    public function type(): SettingType
    {
        return SettingType::Text;
    }
}
