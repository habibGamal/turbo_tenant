<?php

declare(strict_types=1);

namespace App\Plugins\ComboPackages\src\Filament\Widgets;

use App\Plugins\ComboPackages\src\Models\Package;
use App\Plugins\OrderManagement\src\Enums\OrderStatus;
use App\Plugins\OrderManagement\src\Enums\PaymentStatus;
use App\Plugins\OrderManagement\src\Models\OrderItemComponent;
use Carbon\Carbon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;

final class PackageOptionPopularityTable extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        [$startDate, $endDate] = $this->resolveDateRange();

        $packageOptions = Package::withTrashed()->pluck('name', 'id')->toArray();
        $selectedPackageId = $this->tableFilters['package_id']['value'] ?? array_key_first($packageOptions);

        if ($selectedPackageId !== null) {
            $query = OrderItemComponent::query()
                ->join('order_items', 'order_items.id', '=', 'order_item_components.order_item_id')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('order_items.line_type', 'package')
                ->where('order_items.line_reference_id', (int) $selectedPackageId)
                ->where('orders.status', '!=', OrderStatus::CANCELLED->value)
                ->where(function ($q): void {
                    $q->whereNull('orders.payment_status')
                        ->orWhere('orders.payment_status', '!=', PaymentStatus::FAILED->value);
                })
                ->when($startDate, fn ($q) => $q->where('orders.created_at', '>=', $startDate))
                ->when($endDate, fn ($q) => $q->where('orders.created_at', '<=', $endDate))
                ->select([
                    'order_item_components.group_name',
                    'order_item_components.product_name',
                    'order_item_components.variant_name',
                    \Illuminate\Support\Facades\DB::raw('MIN(order_item_components.id) as id'),
                    \Illuminate\Support\Facades\DB::raw('COALESCE(SUM(order_item_components.quantity), COUNT(*)) as pick_count'),
                ])
                ->groupBy(
                    'order_item_components.group_name',
                    'order_item_components.product_name',
                    'order_item_components.variant_name'
                )
                ->orderBy('order_item_components.group_name')
                ->orderByDesc('pick_count');
        } else {
            $query = OrderItemComponent::query()->whereRaw('1 = 0');
        }

        return $table
            ->heading('شعبية خيارات الباقة')
            ->query($query)
            ->paginated(false)
            ->emptyStateHeading('لا توجد بيانات اختيارات للباقة المحددة في هذه الفترة')
            ->columns([
                TextColumn::make('group_name')
                    ->label('المجموعة')
                    ->state(fn (OrderItemComponent $record): string => (string) ($record->group_name ?? 'الافتراضية')),

                TextColumn::make('option')
                    ->label('الخيار')
                    ->state(function (OrderItemComponent $record): string {
                        $product = (string) $record->product_name;
                        $variant = $record->variant_name !== null && mb_trim((string) $record->variant_name) !== ''
                            ? mb_trim((string) $record->variant_name)
                            : null;

                        return $variant !== null ? "{$product} ({$variant})" : $product;
                    }),

                TextColumn::make('pick_count')
                    ->label('مرات الاختيار')
                    ->state(fn (OrderItemComponent $record): string => number_format((float) ($record->pick_count ?? 0))),
            ])
            ->filters([
                SelectFilter::make('package_id')
                    ->label('الباقة')
                    ->options($packageOptions)
                    ->query(fn ($query) => $query)
                    ->default(array_key_first($packageOptions)),

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
}
