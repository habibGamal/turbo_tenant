<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Pages\ManagePlugins;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

final class PluginStatsOverview extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    /**
     * @var int|array<string, int|null>|null
     */
    protected int|array|null $columns = [
        'sm' => 2,
        'lg' => 4,
    ];

    #[On('plugins-updated')]
    public function refreshStats(): void
    {
        $this->cachedStats = null;
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $counts = ManagePlugins::resolvePluginCounts();

        return [
            Stat::make('إجمالي الإضافات', $counts['total'])
                ->description('جميع الإضافات المسجلة في النظام')
                ->descriptionIcon(Heroicon::OutlinedPuzzlePiece)
                ->color('primary'),

            Stat::make('الإضافات المفعلة', $counts['active'])
                ->description('تعمل حالياً في متجرك')
                ->descriptionIcon(Heroicon::OutlinedCheckCircle)
                ->color('success'),

            Stat::make('متاحة للتثبيت', $counts['available'])
                ->description('جاهزة للتثبيت أو التفعيل')
                ->descriptionIcon(Heroicon::OutlinedArrowDownTray)
                ->color('info'),

            Stat::make('إضافات أساسية', $counts['core'])
                ->description('إضافات نظام محمية (Core)')
                ->descriptionIcon(Heroicon::OutlinedLockClosed)
                ->color('warning'),
        ];
    }
}
