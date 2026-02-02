<?php

declare(strict_types=1);

namespace App\Filament\Resources\Packages\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class PackagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->description)
                    ->limit(30),

                TextColumn::make('price')
                    ->label('السعر')
                    ->money('USD')
                    ->sortable()
                    ->badge()
                    ->color('success'),

                TextColumn::make('original_price')
                    ->label('السعر الأصلي')
                    ->money('USD')
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

                TextColumn::make('badge')
                    ->label('الشارة')
                    ->badge()
                    ->color('primary')
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->boolean()
                    ->sortable()
                    ->label('نشط'),

                IconColumn::make('is_featured')
                    ->boolean()
                    ->sortable()
                    ->label('مميز'),

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
            ->defaultSort('sort_order', 'asc');
    }
}
