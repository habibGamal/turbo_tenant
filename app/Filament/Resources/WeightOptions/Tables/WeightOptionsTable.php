<?php

declare(strict_types=1);

namespace App\Filament\Resources\WeightOptions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class WeightOptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('unit')
                    ->label('الوحدة')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'kg' => 'كيلوجرام (kg)',
                        'g' => 'جرام (g)',
                        'lb' => 'رطل (lb)',
                        default => $state,
                    }),
                TextColumn::make('values_count')
                    ->counts('values')
                    ->label('عدد القيم'),
                TextColumn::make('products_count')
                    ->counts('products')
                    ->label('عدد المنتجات'),
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
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
