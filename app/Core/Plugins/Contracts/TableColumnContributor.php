<?php

declare(strict_types=1);

namespace App\Core\Plugins\Contracts;

use Filament\Tables\Columns\Column;

/**
 * Adds Filament table columns to another plugin's admin table without either plugin
 * depending on the other. The table owner collects contributors with
 * PluginContributions->active('admin.table.<owner-plugin-id>.<table>'),
 * e.g. 'admin.table.combo-packages.packages'.
 *
 * Columns must compute their state from the row generically ($record->getKey()),
 * because the contributor does not know the owner's model class.
 */
interface TableColumnContributor extends PluginContribution
{
    /**
     * @return array<Column>
     */
    public function columns(): array;
}
