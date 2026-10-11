<?php

declare(strict_types=1);

namespace App\Core\Plugins\Enums;

enum PluginStatus: string
{
    case ENTITLED = 'entitled';
    case ACTIVE = 'active';
    case DEACTIVATED = 'deactivated';

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }
}
