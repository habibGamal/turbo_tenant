<?php

declare(strict_types=1);

namespace App\Plugins\ComboPackages\src\Filament\Resources\Packages\Pages;

use App\Plugins\ComboPackages\src\Filament\Resources\Packages\PackageResource;
use App\Plugins\ComboPackages\src\Filament\Widgets\ComboStatsOverviewWidget;
use App\Plugins\ComboPackages\src\Filament\Widgets\PackageOptionPopularityTable;
use App\Plugins\ComboPackages\src\Filament\Widgets\TopPackagesTable;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListPackages extends ListRecords
{
    protected static string $resource = PackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ComboStatsOverviewWidget::class,
            TopPackagesTable::class,
            PackageOptionPopularityTable::class,
        ];
    }
}
