<?php

declare(strict_types=1);

namespace App\Plugins\ComboPackages\src\Filament\Widgets;

use App\Core\Contracts\SettingsRepository;
use App\Plugins\ComboPackages\src\Models\Package;
use App\Plugins\OrderManagement\src\Enums\OrderStatus;
use App\Plugins\OrderManagement\src\Enums\PaymentStatus;
use App\Plugins\OrderManagement\src\Models\OrderItem;
use Carbon\Carbon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;
use Throwable;

final class TopPackagesTable extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $currency = $this->getCurrency();
        [$startDate, $endDate] = $this->resolveDateRange();

        $query = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.line_type', 'package')
            ->where('orders.status', '!=', OrderStatus::CANCELLED->value)
            ->where(function ($q): void {
                $q->whereNull('orders.payment_status')
                    ->orWhere('orders.payment_status', '!=', PaymentStatus::FAILED->value);
            })
            ->when($startDate, fn ($q) => $q->where('orders.created_at', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->where('orders.created_at', '<=', $endDate))
            ->select([
                'order_items.line_reference_id as id',
                'order_items.line_reference_id',
                \Illuminate\Support\Facades\DB::raw('MAX(order_items.product_name) as snapshotted_name'),
                \Illuminate\Support\Facades\DB::raw('MAX(order_items.product_name_ar) as snapshotted_name_ar'),
                \Illuminate\Support\Facades\DB::raw('COALESCE(SUM(order_items.quantity), 0) as units_sold'),
                \Illuminate\Support\Facades\DB::raw('COALESCE(SUM(order_items.total), 0) as total_revenue'),
            ])
            ->groupBy('order_items.line_reference_id')
            ->orderByDesc('total_revenue');

        return $table
            ->heading('أداء مبيعات الباقات')
            ->query($query)
            ->paginated(false)
            ->emptyStateHeading('لا توجد مبيعات باقات في هذه الفترة')
            ->columns([
                TextColumn::make('package_name')
                    ->label('الباقة')
                    ->state(function (OrderItem $record): string {
                        $id = $record->line_reference_id ?? $record->id;
                        $pkg = Package::withTrashed()->find($id);

                        return (string) ($pkg?->name ?? $record->snapshotted_name ?? "باقة #{$id}");
                    })
                    ->searchable(false),

                TextColumn::make('units_sold')
                    ->label('الوحدات المباعة')
                    ->state(fn (OrderItem $record): string => number_format((float) ($record->units_sold ?? 0))),

                TextColumn::make('total_revenue')
                    ->label('الإيرادات')
                    ->state(fn (OrderItem $record): string => number_format((float) ($record->total_revenue ?? 0.0), 2).' '.$currency),

                TextColumn::make('average_price')
                    ->label('متوسط السعر')
                    ->state(function (OrderItem $record) use ($currency): string {
                        $units = (float) ($record->units_sold ?? 0);
                        $revenue = (float) ($record->total_revenue ?? 0.0);
                        $avg = $units > 0 ? ($revenue / $units) : 0.0;

                        return number_format($avg, 2).' '.$currency;
                    }),
            ])
            ->filters([
                SelectFilter::make('range')
                    ->label('الفترة الزمنية')
                    ->options([
                        '7' => 'آخر 7 أيام',
                        '30' => 'آخر 30 يوم',
                        '90' => 'آخر 90 يوم',
                        '365' => 'آخر سنة',
                        'all' => 'كل الأوقات',
                    ])
                    ->query(fn ($query) => $query)
                    ->default('30'),
            ]);
    }

    /**
     * @return array{0: Carbon|null, 1: Carbon|null}
     */
    private function resolveDateRange(): array
    {
        $start = $this->pageFilters['startDate'] ?? $this->pageFilters['start_date'] ?? null;
        $end = $this->pageFilters['endDate'] ?? $this->pageFilters['end_date'] ?? null;

        if (is_string($start) && mb_trim($start) !== '') {
            $startDate = Carbon::parse($start)->startOfDay();
            $endDate = is_string($end) && mb_trim($end) !== '' ? Carbon::parse($end)->endOfDay() : now()->endOfDay();

            return [$startDate, $endDate];
        }

        $filterRange = $this->tableFilters['range']['value'] ?? '30';

        return match ($filterRange) {
            '7' => [now()->subDays(7)->startOfDay(), now()->endOfDay()],
            '90' => [now()->subDays(90)->startOfDay(), now()->endOfDay()],
            '365' => [now()->subDays(365)->startOfDay(), now()->endOfDay()],
            'all' => [null, null],
            default => [now()->subDays(30)->startOfDay(), now()->endOfDay()],
        };
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
