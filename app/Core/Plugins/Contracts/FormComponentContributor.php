<?php

declare(strict_types=1);

namespace App\Core\Plugins\Contracts;

use Filament\Schemas\Components\Component;

/**
 * Adds Filament form components to another plugin's admin form without either plugin
 * depending on the other. The form owner collects contributors with
 * PluginContributions->active('admin.form.<owner-plugin-id>.<form>'),
 * e.g. 'admin.form.combo-packages.package'.
 *
 * Contributed components must load and save their own state from the record generically
 * (e.g. afterStateHydrated + saveRelationshipsUsing keyed by $record->getKey()), because the
 * contributor does not know the form owner's model class.
 */
interface FormComponentContributor extends PluginContribution
{
    /**
     * @return array<Component|\Filament\Forms\Components\Field>
     */
    public function components(): array;
}
