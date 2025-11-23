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
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->description)
                    ->limit(30),

                TextColumn::make('price')
                    ->money('USD')
                    ->sortable()
                    ->badge()
                    ->color('success'),

                TextColumn::make('original_price')
                    ->money('USD')
                    ->sortable()
                    ->toggleable()
                    ->color('gray'),

                TextColumn::make('discount_percentage')
                    ->suffix('%')
                    ->sortable()
                    ->toggleable()
                    ->badge()
                    ->color('warning'),

                TextColumn::make('badge')
                    ->badge()
                    ->color('primary')
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->boolean()
                    ->sortable()
                    ->label('Active'),

                IconColumn::make('is_featured')
                    ->boolean()
                    ->sortable()
                    ->label('Featured'),

                TextColumn::make('groups_count')
                    ->counts('groups')
                    ->label('Groups')
                    ->badge()
                    ->color('info'),

                TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Items')
                    ->badge()
                    ->color('info'),

                TextColumn::make('sort_order')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('valid_from')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('valid_until')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

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
                TernaryFilter::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only')
                    ->native(false),

                TernaryFilter::make('is_featured')
                    ->label('Featured')
                    ->boolean()
                    ->trueLabel('Featured only')
                    ->falseLabel('Not featured')
                    ->native(false),

                SelectFilter::make('has_discount')
                    ->label('Discount')
                    ->options([
                        'yes' => 'With Discount',
                        'no' => 'No Discount',
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
