<?php

declare(strict_types=1);

namespace App\Plugins\StoreSettings\src\Contracts;

use App\Core\Contracts\SettingDefinition;
use App\Core\Plugins\Contracts\PluginContribution;
use Filament\Schemas\Components\Tabs\Tab;

interface SettingsSection extends PluginContribution
{
    public function tab(): Tab;

    /**
     * @return list<SettingDefinition>
     */
    public function keys(): array;

    public function sort(): int;
}
