<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Filament\Widgets;

use App\Core\Contracts\SettingsRepository;
use App\Plugins\OrderManagement\src\Enums\OrderStatus;
use App\Plugins\OrderManagement\src\Filament\Resources\Orders\OrderResource;
use App\Plugins\OrderManagement\src\Models\Order;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Throwable;

final class LatestOrdersTable extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $currency = $this->getCurrency();

        return $table
            ->heading('أحدث الطلبات')
            ->query(Order::query()->latest()->limit(10))
            ->paginated(false)
            ->poll('30s')
            ->recordUrl(
                fn (Order $record): ?string => class_exists(OrderResource::class)
                    ? OrderResource::getUrl('view', ['record' => $record])
                    : null
            )
            ->columns([
                TextColumn::make('order_number')
                    ->label('رقم الطلب')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('customer')
                    ->label('العميل')
                    ->state(fn (Order $record): string => $record->getCustomerName()),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->color(fn (string|OrderStatus|null $state): string => match ($state instanceof OrderStatus ? $state->value : (string) $state) {
                        'pending' => 'gray',
                        'processing' => 'warning',
                        'out_for_delivery' => 'primary',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string|OrderStatus|null $state): string => match ($state instanceof OrderStatus ? $state->value : (string) $state) {
                        'pending' => 'قيد الانتظار',
                        'processing' => 'قيد التجهيز',
                        'out_for_delivery' => 'خارج للتوصيل',
                        'completed' => 'مكتمل',
                        'cancelled' => 'ملغي',
                        default => (string) $state,
                    }),
                TextColumn::make('total')
                    ->label('الإجمالي')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2).' '.$currency),
                TextColumn::make('created_at')
                    ->label('تاريخ الطلب')
                    ->since()
                    ->dateTimeTooltip(),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('عرض')
                    ->icon('heroicon-o-eye')
                    ->url(
                        fn (Order $record): ?string => class_exists(OrderResource::class)
                            ? OrderResource::getUrl('view', ['record' => $record])
                            : null
                    ),
            ]);
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
