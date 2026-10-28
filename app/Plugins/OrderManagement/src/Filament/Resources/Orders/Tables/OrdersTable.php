<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Filament\Resources\Orders\Tables;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Plugins\OrderManagement\src\Enums\OrderPosStatus;
use App\Plugins\StoreSettings\src\Services\SettingService;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

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
                            $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"))
                                ->orWhereHas('guestUser', fn ($g) => $g->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"));
                        });
                    })
                    ->sortable(query: function ($query, $direction) {
                        if (class_exists(\App\Plugins\GuestCheckout\src\Models\GuestUser::class)) {
                            return $query->orderBy(
                                \App\Models\User::select('name')
                                    ->whereColumn('users.id', 'orders.user_id')
                                    ->union(
                                        \App\Plugins\GuestCheckout\src\Models\GuestUser::select('name')
                                            ->whereColumn('guest_users.id', 'orders.guest_user_id')
                                    ),
                                $direction
                            );
                        }

                        return $query->orderBy(
                            \App\Models\User::select('name')
                                ->whereColumn('users.id', 'orders.user_id'),
                            $direction
                        );
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
                    ->money(fn () => app(SettingService::class)->get('currency', 'EGP'))
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('created_at')
                    ->label('تاريخ الطلب')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
            ])
            ->filters([
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
