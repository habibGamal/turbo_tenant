<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Contracts;

use App\Core\Plugins\Contracts\PluginContribution;
use Filament\Schemas\Components\Tabs\Tab;

interface ProductFormExtender extends PluginContribution
{
    /**
     * @return array<Tab>
     */
    public function tabs(): array;
}
