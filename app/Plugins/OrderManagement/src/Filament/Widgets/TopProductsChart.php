<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Filament\Widgets;

use App\Plugins\OrderManagement\src\Enums\OrderStatus;
use App\Plugins\OrderManagement\src\Models\OrderItem;
use Filament\Widgets\ChartWidget;

final class TopProductsChart extends ChartWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'المنتجات الأكثر مبيعاً (آخر 7 أيام)';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return class_exists(OrderItem::class);
    }

    protected function getData(): array
    {
        $since = now()->subDays(6)->startOfDay();

        $items = OrderItem::query()
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.created_at', '>=', $since)
            ->where('orders.status', '!=', OrderStatus::CANCELLED->value)
            ->selectRaw("COALESCE(NULLIF(order_items.product_name_ar, ''), order_items.product_name) as name, SUM(order_items.quantity) as total_qty")
            ->groupBy('name')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'الكمية المباعة',
                    'data' => $items->pluck('total_qty')->map(fn ($q): float => (float) $q)->values()->toArray(),
                    'backgroundColor' => [
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(139, 92, 246, 0.8)',
                    ],
                ],
            ],
            'labels' => $items->pluck('name')->map(fn ($name): string => (string) ($name ?? 'غير محدد'))->values()->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
