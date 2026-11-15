<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\Filament\Widgets;

use App\Core\Contracts\SettingsRepository;
use App\Plugins\OrderManagement\src\Enums\OrderPosStatus;
use App\Plugins\OrderManagement\src\Filament\Resources\Orders\OrderResource;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\PosIntegration\src\Filament\Actions\OrderDeliveryActions;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Throwable;

final class PosFailedOrdersTable extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        if (! class_exists(Order::class) || ! OrderDeliveryActions::authorized()) {
            return false;
        }

        $failedStatus = class_exists(OrderPosStatus::class) ? OrderPosStatus::FAILED->value : 'failed';

        return Order::query()->where('pos_status', $failedStatus)->exists();
    }

    public function table(Table $table): Table
    {
        $currency = $this->getCurrency();
        $failedStatus = class_exists(OrderPosStatus::class) ? OrderPosStatus::FAILED->value : 'failed';

        return $table
            ->heading('طلبات فشل إرسالها لنقطة البيع')
            ->query(Order::query()->where('pos_status', $failedStatus)->latest())
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
                TextColumn::make('pos_failure_reason')
                    ->label('سبب الفشل')
                    ->placeholder('غير محدد')
                    ->limit(40),
                TextColumn::make('total')
                    ->label('الإجمالي')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2).' '.$currency),
                TextColumn::make('created_at')
                    ->label('تاريخ الطلب')
                    ->since()
                    ->dateTimeTooltip(),
            ])
            ->recordActions([
                OrderDeliveryActions::send(retry: true),
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
