<?php

declare(strict_types=1);

use App\Plugins\PosIntegration\src\Filament\Components\PosItemSelect as PluginPosItemSelect;

if (! class_exists('App\Filament\Forms\Components\PosItemSelect', false)) {
    class_alias(PluginPosItemSelect::class, 'App\Filament\Forms\Components\PosItemSelect');
}
