<?php

declare(strict_types=1);

namespace App\Plugins\CouponsPromotions\src\Filament\Resources\Coupons\Tables;

use App\Plugins\CouponsPromotions\src\Filament\Resources\Coupons\CouponResource;
use App\Plugins\CouponsPromotions\src\Models\Coupon;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class CouponsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('الرمز')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->copyable()
                    ->copyMessage('تم نسخ الرمز'),
                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('type')
                    ->label('النوع')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'percentage' => 'success',
                        'fixed' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'percentage' => 'نسبة مئوية',
                        'fixed' => 'مبلغ ثابت',
                        default => $state,
                    }),
                TextColumn::make('value')
                    ->label('القيمة')
                    ->sortable()
                    ->formatStateUsing(fn (string $state, $record): string => $record->type === 'percentage' ? "{$state}%" : "{$state} EGP"
                    ),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->state(function (Coupon $record): string {
                        if (! $record->is_active) {
                            return 'معطل';
                        }
                        if ($record->expiry_date && now()->gt($record->expiry_date)) {
                            return 'منتهي';
                        }
                        if ($record->max_usage && $record->usage_count >= $record->max_usage) {
                            return 'استنفذ';
                        }

                        return 'نشط';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'نشط' => 'success',
                        'منتهي' => 'danger',
                        'استنفذ' => 'warning',
                        'معطل' => 'gray',
                        default => 'gray',
                    }),
                IconColumn::make('free_shipping')
                    ->label('شحن مجاني')
                    ->state(fn (Coupon $record): bool => (bool) ($record->conditions['shipping']['free_shipping'] ?? false))
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('min_order_total')
                    ->label('الحد الأدنى للطلب')
                    ->state(fn (Coupon $record): ?string => isset($record->conditions['min_order_total']) && $record->conditions['min_order_total'] !== null ? "{$record->conditions['min_order_total']} EGP" : '—')
                    ->toggleable(),
                ToggleColumn::make('is_active')
                    ->label('نشط')
                    ->sortable(),
                TextColumn::make('expiry_date')
                    ->label('تاريخ الانتهاء')
                    ->dateTime()
                    ->sortable()
                    ->color(fn ($state) => now()->gt($state) ? 'danger' : 'success'),
                TextColumn::make('usage_count')
                    ->label('عدد مرات الاستخدام')
                    ->sortable()
                    ->formatStateUsing(fn (string $state, $record): string => $record->max_usage ? "{$state} / {$record->max_usage}" : $state
                    ),
                TextColumn::make('total_consumed')
                    ->label('إجمالي الخصم')
                    ->sortable()
                    ->money('EGP')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('نشط')
                    ->boolean()
                    ->trueLabel('نشط فقط')
                    ->falseLabel('غير نشط فقط')
                    ->native(false),
                SelectFilter::make('type')
                    ->label('النوع')
                    ->options([
                        'percentage' => 'نسبة مئوية',
                        'fixed' => 'مبلغ ثابت',
                    ]),
                Filter::make('exhausted')
                    ->label('كوبونات مستنفذة')
                    ->query(fn ($query) => $query->whereNotNull('max_usage')->whereColumn('usage_count', '>=', 'max_usage')),
                Filter::make('free_shipping')
                    ->label('كوبونات شحن مجاني')
                    ->query(fn ($query) => $query->where('conditions->shipping->free_shipping', true)),
                TernaryFilter::make('expired')
                    ->label('حالة الصلاحية')
                    ->queries(
                        true: fn ($query) => $query->where('expiry_date', '<', now()),
                        false: fn ($query) => $query->where('expiry_date', '>=', now()),
                    )
                    ->trueLabel('منتهي')
                    ->falseLabel('صالح'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('لا توجد كوبونات خصم')
            ->emptyStateDescription('أنشئ أول كوبون ترويجي لمكافأة عملائك وزيادة المبيعات.')
            ->emptyStateIcon('heroicon-o-ticket')
            ->emptyStateActions([
                Action::make('create')
                    ->label('إنشاء كوبون جديد')
                    ->url(fn () => CouponResource::getUrl('create'))
                    ->icon('heroicon-m-plus')
                    ->button(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
