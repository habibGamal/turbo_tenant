<?php

declare(strict_types=1);

namespace App\Plugins\ComboPackages\src\Filament\Widgets;

use App\Core\Contracts\SettingsRepository;
use App\Plugins\ComboPackages\src\Reporting\ComboSalesReport;
use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Throwable;

final class ComboStatsOverviewWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        [$startDate, $endDate] = $this->resolveDateRange();

        $report = app(ComboSalesReport::class);
        $stats = $report->getStats($startDate, $endDate);

        $currency = $this->getCurrency();
        $combosSold = $stats['combos_sold'];
        $comboRevenue = $stats['combo_revenue'];
        $avgPrice = $stats['average_price'];
        $sharePercent = $stats['combo_share_percentage'];

        return [
            Stat::make('الباقات المباعة', number_format($combosSold))
                ->description('إجمالي كمية الباقات المطلوبة')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('primary'),

            Stat::make('إيرادات الباقات', number_format($comboRevenue, 2).' '.$currency)
                ->description('إجمالي مبيعات الباقات في الفترة')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('متوسط سعر الباقة', number_format($avgPrice, 2).' '.$currency)
                ->description('متوسط قيمة الباقة المباعة')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('info'),

            Stat::make('حصة الباقات من الإيرادات', number_format($sharePercent, 1).'%')
                ->description('من إجمالي إيرادات المتجر في الفترة')
                ->descriptionIcon('heroicon-m-chart-pie')
                ->color('warning'),
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveDateRange(): array
    {
        $start = $this->pageFilters['startDate'] ?? $this->pageFilters['start_date'] ?? null;
        $end = $this->pageFilters['endDate'] ?? $this->pageFilters['end_date'] ?? null;

        $startDate = is_string($start) && mb_trim($start) !== ''
            ? Carbon::parse($start)->startOfDay()
            : now()->subDays(30)->startOfDay();

        $endDate = is_string($end) && mb_trim($end) !== ''
            ? Carbon::parse($end)->endOfDay()
            : now()->endOfDay();

        return [$startDate, $endDate];
    }

    private function getCurrency(): string
    {
        if (app()->bound(SettingsRepository::class)) {
            try {
                return (string) (app(SettingsRepository::class)->get('currency', 'EGP') ?? 'EGP');
            } catch (Throwable) {
                return 'EGP';
            }
        }

        return 'EGP';
    }
}
