<?php

declare(strict_types=1);

namespace App\Core\Contracts;

use BackedEnum;

interface SettingDefinition extends BackedEnum
{
    public function defaultValue(): mixed;

    public function type(): SettingType;
}
