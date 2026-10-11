<?php

declare(strict_types=1);

namespace App\Core\Plugins\Enums;

enum PluginCategory: string
{
    case FOUNDATION = 'foundation';
    case COMMERCE = 'commerce';
    case MARKETING = 'marketing';
    case COMMUNICATION = 'communication';
    case STOREFRONT = 'storefront';
    case ADDON = 'addon';
}
