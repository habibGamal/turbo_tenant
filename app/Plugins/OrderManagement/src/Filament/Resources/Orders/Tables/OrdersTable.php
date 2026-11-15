<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Filament\Resources\Orders\Tables;

use App\Core\Contracts\SettingsRepository;
use App\Plugins\OrderManagement\src\Enums\OrderPosStatus;
use App\Plugins\OrderManagement\src\Enums\OrderStatus;
use App\Plugins\OrderManagement\src\Enums\PaymentMethod;
use App\Plugins\OrderManagement\src\Enums\PaymentStatus;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\PosIntegration\src\Filament\Actions\OrderDeliveryActions;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->poll('30s')
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('لا توجد طلبات')
            ->emptyStateDescription('ستظهر هنا جميع الطلبات الواردة من المتجر ونقاط البيع.')
            ->emptyStateIcon('heroicon-o-shopping-bag')
            ->columns([
                TextColumn::make('order_number')
                    ->label('رقم الطلب')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('customer')
                    ->label('العميل')
                    ->state(fn ($record) => $record->getCustomerName())
                    ->description(fn ($record) => $record->getCustomerPhone() ?? '—')
                    ->searchable(query: function ($query, $search) {
                        return $query->where(function ($q) use ($search) {
                            $q->where('customer_name', 'like', "%{$search}%")
                                ->orWhere('customer_phone', 'like', "%{$search}%")
                                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"));
                        });
                    })
                    ->sortable(query: function ($query, $direction) {
                        return $query->orderBy('customer_name', $direction);
                    })
                    ->copyable()
                    ->copyMessage('تم نسخ الاسم'),

                TextColumn::make('type')
                    ->label('النوع')
                    ->badge()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->sortable(),

                TextColumn::make('payment_status')
                    ->label('حالة الدفع')
                    ->badge()
                    ->sortable(),

                TextColumn::make('pos_status')
                    ->label('مزامنة POS')
                    ->badge()
                    ->sortable(),

                TextColumn::make('posDelivery.failures_count')
                    ->label('محاولات إرسال فاشلة')
                    ->placeholder('غير متاح')
                    ->visible(fn (): bool => OrderDeliveryActions::enabled())
                    ->toggleable(),

                TextColumn::make('posDelivery.last_attempt_at')
                    ->label('آخر محاولة إرسال')
                    ->since()->dateTimeTooltip()->placeholder('غير متاح')
                    ->visible(fn (): bool => OrderDeliveryActions::enabled())
                    ->toggleable(),

                TextColumn::make('delivery_overdue')
                    ->label('متابعة الإرسال')
                    ->state(fn (Order $record): ?string => $record->posDelivery?->isOverdue() ? 'تأخرت المهمة' : null)
                    ->badge()->color('warning')
                    ->visible(fn (): bool => OrderDeliveryActions::enabled())
                    ->toggleable(),

                TextColumn::make('items_count')
                    ->counts('items')
                    ->label('العناصر')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('branch.name')
                    ->label('الفرع')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('total')
                    ->label('الإجمالي')
                    ->money(fn () => app(SettingsRepository::class)->get('currency', 'EGP'))
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('created_at')
                    ->label('تاريخ الطلب')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
            ])
            ->filters([
                Filter::make('pos_overdue')
                    ->label('مهام إرسال متأخرة')
                    ->visible(fn (): bool => OrderDeliveryActions::enabled())
                    ->query(function (Builder $query): Builder {
                        if (! OrderDeliveryActions::enabled()) {
                            return $query;
                        }

                        return $query->whereHas('posDelivery', fn (Builder $delivery) => $delivery->where(function (Builder $states): void {
                            $cutoff = now()->subMinutes(5);
                            $states->where(fn (Builder $q) => $q->where('status', OrderPosStatus::QUEUED)->where('queued_at', '<', $cutoff))
                                ->orWhere(fn (Builder $q) => $q->where('status', OrderPosStatus::SENDING)->where('last_attempt_at', '<', $cutoff))
                                ->orWhere(fn (Builder $q) => $q->where('status', OrderPosStatus::RETRY_WAIT)->where('next_retry_at', '<', $cutoff));
                        }));
                    }),
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from')->label('من تاريخ'),
                        DatePicker::make('created_until')->label('إلى تاريخ'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['created_from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['created_until'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),

                SelectFilter::make('status')
                    ->label('حالة الطلب')
                    ->options(OrderStatus::class)
                    ->multiple(),

                SelectFilter::make('payment_status')
                    ->label('حالة الدفع')
                    ->options(PaymentStatus::class)
                    ->multiple(),

                SelectFilter::make('payment_method')
                    ->label('طريقة الدفع')
                    ->options(PaymentMethod::class)
                    ->multiple(),

                SelectFilter::make('pos_status')
                    ->label('مزامنة POS')
                    ->options(class_exists(OrderPosStatus::class) ? OrderPosStatus::class : [
                        'failed' => 'فشل الإرسال (مراجعة مطلوبة)',
                        'sent' => 'تم الإرسال بنجاح',
                        'pending' => 'قيد الانتظار',
                    ]),

                SelectFilter::make('customer_type')
                    ->label('نوع العميل')
                    ->options([
                        'guest' => 'زائر',
                        'user' => 'مسجل',
                    ])
                    ->query(function ($query, $state) {
                        if (($state['value'] ?? null) === 'guest') {
                            return $query->whereNotNull('guest_user_id');
                        }
                        if (($state['value'] ?? null) === 'user') {
                            return $query->whereNotNull('user_id');
                        }

                        return $query;
                    }),

                SelectFilter::make('branch')
                    ->label('الفرع')
                    ->relationship('branch', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
