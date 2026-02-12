<?php

declare(strict_types=1);

namespace App\Filament\Resources\Areas\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class AreasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('name')
                    ->label('الاسم (إنجليزي)')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name_ar')
                    ->label('الاسم (عربي)')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('governorate.name')
                    ->label('المحافظة')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('branch.name')
                    ->label('الفرع')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('shipping_cost')
                    ->label('تكلفة الشحن')
                    ->money('EGP')
                    ->sortable(),

                TextColumn::make('addresses_count')
                    ->label('عدد العناوين')
                    ->counts('addresses')
                    ->sortable(),

                TextColumn::make('sort_order')
                    ->label('ترتيب العرض')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('نشط')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('governorate_id')
                    ->label('المحافظة')
                    ->relationship('governorate', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('branch_id')
                    ->label('الفرع')
                    ->relationship('branch', 'name')
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('is_active')
                    ->label('نشط')
                    ->placeholder('الكل')
                    ->trueLabel('نشط فقط')
                    ->falseLabel('غير نشط فقط'),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order', 'asc');
    }
}
