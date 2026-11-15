<?php

declare(strict_types=1);

namespace App\Core\Contracts;

enum SettingType
{
    case Text;
    case Number;
    case Boolean;
    case Json;
}
