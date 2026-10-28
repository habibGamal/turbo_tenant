<?php

declare(strict_types=1);

namespace App\Plugins\ComboPackages\src\Filament\Resources\Packages\Tables;

use App\Plugins\ComboPackages\src\Filament\Resources\Packages\PackageResource;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class PackagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order', 'asc')
            ->columns([
                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->description)
                    ->limit(30),

                TextColumn::make('name_ar')
                    ->label('الاسم (عربي)')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('price')
                    ->label('السعر')
                    ->money('EGP')
                    ->sortable()
                    ->badge()
                    ->color('success'),

                TextColumn::make('original_price')
                    ->label('السعر الأصلي')
                    ->money('EGP')
                    ->sortable()
                    ->toggleable()
                    ->color('gray'),

                TextColumn::make('discount_percentage')
                    ->label('نسبة الخصم')
                    ->suffix('%')
                    ->sortable()
                    ->toggleable()
                    ->badge()
                    ->color('warning'),

                TextColumn::make('validity_status')
                    ->label('حالة الصلاحية')
                    ->badge()
                    ->state(function ($record): string {
                        if ($record->valid_until && now()->gt($record->valid_until)) {
                            return 'منتهية';
                        }
                        if ($record->valid_from && now()->lt($record->valid_from)) {
                            return 'مجدولة';
                        }

                        return 'سارية';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'سارية' => 'success',
                        'مجدولة' => 'info',
                        'منتهية' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('badge')
                    ->label('الشارة')
                    ->badge()
                    ->color('primary')
                    ->toggleable(),

                ToggleColumn::make('is_active')
                    ->label('نشط')
                    ->sortable(),

                ToggleColumn::make('is_featured')
                    ->label('مميز')
                    ->sortable(),

                TextColumn::make('groups_count')
                    ->counts('groups')
                    ->label('عدد المجموعات')
                    ->badge()
                    ->color('info'),

                TextColumn::make('items_count')
                    ->counts('items')
                    ->label('عدد العناصر')
                    ->badge()
                    ->color('info'),

                TextColumn::make('sort_order')
                    ->label('ترتيب العرض')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('valid_from')
                    ->label('صالح من')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('valid_until')
                    ->label('صالح حتى')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('تاريخ التحديث')
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

                TernaryFilter::make('is_featured')
                    ->label('مميز')
                    ->boolean()
                    ->trueLabel('مميز فقط')
                    ->falseLabel('غير مميز')
                    ->native(false),

                Filter::make('valid')
                    ->label('صالح حالياً')
                    ->query(fn ($query) => $query
                        ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', now()))
                        ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', now()))
                    ),

                Filter::make('expired')
                    ->label('منتهي الصلاحية')
                    ->query(fn ($query) => $query->whereNotNull('valid_until')->where('valid_until', '<', now())),

                SelectFilter::make('has_discount')
                    ->label('الخصم')
                    ->options([
                        'yes' => 'بخصم',
                        'no' => 'بدون خصم',
                    ])
                    ->query(function ($query, $state) {
                        if ($state['value'] === 'yes') {
                            return $query->whereNotNull('discount_percentage')->where('discount_percentage', '>', 0);
                        }
                        if ($state['value'] === 'no') {
                            return $query->whereNull('discount_percentage')->orWhere('discount_percentage', '=', 0);
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('لا توجد باقات توفير')
            ->emptyStateDescription('أنشئ أول باقة وجبات أو عروض مجمعة لجذب عملائك وزيادة قيمة الطلب.')
            ->emptyStateIcon('heroicon-o-archive-box')
            ->emptyStateActions([
                Action::make('create')
                    ->label('إنشاء باقة جديدة')
                    ->url(fn () => PackageResource::getUrl('create'))
                    ->icon('heroicon-m-plus')
                    ->button(),
            ]);
    }
}
