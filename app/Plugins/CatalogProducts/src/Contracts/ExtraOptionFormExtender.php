<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Contracts;

use App\Core\Plugins\Contracts\PluginContribution;
use Filament\Schemas\Components\Component;

interface ExtraOptionFormExtender extends PluginContribution
{
    /**
     * @return array<Component>
     */
    public function itemComponents(): array;
}
