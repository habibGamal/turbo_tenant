<?php

declare(strict_types=1);

namespace App\Core\Plugins\Contracts;

use Filament\Tables\Columns\Column;

interface UsersTableColumnContributor extends PluginContribution
{
    /**
     * @return array<Column>
     */
    public function columns(): array;
}
