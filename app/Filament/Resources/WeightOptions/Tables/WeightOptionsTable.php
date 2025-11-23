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
                    ->searchable()
                    ->sortable(),
                TextColumn::make('unit')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'kg' => 'Kilogram (kg)',
                        'g' => 'Gram (g)',
                        'lb' => 'Pound (lb)',
                        default => $state,
                    }),
                TextColumn::make('values_count')
                    ->counts('values')
                    ->label('Values'),
                TextColumn::make('products_count')
                    ->counts('products')
                    ->label('Products'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
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
