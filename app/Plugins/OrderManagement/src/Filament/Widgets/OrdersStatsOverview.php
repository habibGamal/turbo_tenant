<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\StoreSettings\src\Services\SettingService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Throwable;

final class OrdersStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $days = collect(range(6, 0))->map(fn (int $daysAgo): string => now()->subDays($daysAgo)->format('Y-m-d'));
        $todayStr = now()->format('Y-m-d');
        $yesterdayStr = now()->subDay()->format('Y-m-d');

        $start = now()->subDays(6)->startOfDay();
        $end = now()->endOfDay();

        $records = Order::query()
            ->where('created_at', '>=', $start)
            ->where('created_at', '<=', $end)
            ->selectRaw("
                DATE(created_at) as order_date,
                SUM(CASE WHEN status != 'cancelled' THEN 1 ELSE 0 END) as valid_orders,
                SUM(CASE WHEN status != 'cancelled' THEN total ELSE 0 END) as total_revenue,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders
            ")
            ->groupBy('order_date')
            ->get()
            ->keyBy('order_date');

        $ordersChart = [];
        $revenueChart = [];
        $aovChart = [];
        $pendingChart = [];

        foreach ($days as $dateStr) {
            $row = $records->get($dateStr);
            $validOrders = (int) ($row?->valid_orders ?? 0);
            $revenue = (float) ($row?->total_revenue ?? 0.0);
            $aov = $validOrders > 0 ? round($revenue / $validOrders, 2) : 0.0;
            $pending = (int) ($row?->pending_orders ?? 0);

            $ordersChart[] = $validOrders;
            $revenueChart[] = $revenue;
            $aovChart[] = $aov;
            $pendingChart[] = $pending;
        }

        $todayRow = $records->get($todayStr);
        $yesterdayRow = $records->get($yesterdayStr);

        $todayOrders = (int) ($todayRow?->valid_orders ?? 0);
        $yesterdayOrders = (int) ($yesterdayRow?->valid_orders ?? 0);

        $todayRevenue = (float) ($todayRow?->total_revenue ?? 0.0);
        $yesterdayRevenue = (float) ($yesterdayRow?->total_revenue ?? 0.0);

        $todayAov = $todayOrders > 0 ? round($todayRevenue / $todayOrders, 2) : 0.0;
        $yesterdayAov = $yesterdayOrders > 0 ? round($yesterdayRevenue / $yesterdayOrders, 2) : 0.0;

        $totalPending = Order::query()->where('status', OrderStatus::PENDING->value)->count();
        $todayPending = (int) ($todayRow?->pending_orders ?? 0);
        $yesterdayPending = (int) ($yesterdayRow?->pending_orders ?? 0);

        $ordersComp = $this->calculateComparison($todayOrders, $yesterdayOrders);
        $revenueComp = $this->calculateComparison($todayRevenue, $yesterdayRevenue);
        $aovComp = $this->calculateComparison($todayAov, $yesterdayAov);

        $currency = $this->getCurrency();

        return [
            Stat::make('طلبات اليوم', (string) $todayOrders)
                ->description($ordersComp['description'])
                ->descriptionIcon($ordersComp['icon'])
                ->color($ordersComp['color'])
                ->chart($ordersChart),

            Stat::make('إيرادات اليوم', number_format($todayRevenue, 2).' '.$currency)
                ->description($revenueComp['description'])
                ->descriptionIcon($revenueComp['icon'])
                ->color($revenueComp['color'])
                ->chart($revenueChart),

            Stat::make('متوسط قيمة الطلب', number_format($todayAov, 2).' '.$currency)
                ->description($aovComp['description'])
                ->descriptionIcon($aovComp['icon'])
                ->color($aovComp['color'])
                ->chart($aovChart),

            Stat::make('طلبات قيد الانتظار', (string) $totalPending)
                ->description($totalPending > 0 ? 'بانتظار التأكيد' : 'لا توجد طلبات معلقة')
                ->descriptionIcon('heroicon-m-clock')
                ->color($totalPending > 0 ? 'warning' : 'gray')
                ->chart($pendingChart),
        ];
    }

    /**
     * @return array{description: string, icon: string, color: string}
     */
    private function calculateComparison(float|int $today, float|int $yesterday): array
    {
        if (abs((float) $yesterday) < PHP_FLOAT_EPSILON || (float) $yesterday <= 0.0) {
            if ($today > 0) {
                return [
                    'description' => '+100% عن أمس',
                    'icon' => 'heroicon-m-arrow-trending-up',
                    'color' => 'success',
                ];
            }

            return [
                'description' => '0% عن أمس',
                'icon' => 'heroicon-m-minus',
                'color' => 'gray',
            ];
        }

        $diff = (($today - $yesterday) / $yesterday) * 100;
        $rounded = (int) round(abs($diff));

        if ($diff > 0) {
            return [
                'description' => "+{$rounded}% عن أمس",
                'icon' => 'heroicon-m-arrow-trending-up',
                'color' => 'success',
            ];
        }

        if ($diff < 0) {
            return [
                'description' => "-{$rounded}% عن أمس",
                'icon' => 'heroicon-m-arrow-trending-down',
                'color' => 'danger',
            ];
        }

        return [
            'description' => '0% عن أمس',
            'icon' => 'heroicon-m-minus',
            'color' => 'gray',
        ];
    }

    private function getCurrency(): string
    {
        if (class_exists(SettingService::class)) {
            try {
                return (string) (app(SettingService::class)->get('currency', 'EGP') ?? 'EGP');
            } catch (Throwable) {
                return 'EGP';
            }
        }

        return 'EGP';
    }
}
