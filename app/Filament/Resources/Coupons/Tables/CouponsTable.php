<?php

declare(strict_types=1);

namespace App\Filament\Resources\Coupons\Tables;

use Filament\Support\Enums\FontWeight;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
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
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->copyable()
                    ->copyMessage('Code copied to clipboard'),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'percentage' => 'success',
                        'fixed' => 'info',
                    })
                    ->formatStateUsing(fn(string $state): string => ucfirst($state)),
                TextColumn::make('value')
                    ->sortable()
                    ->formatStateUsing(fn(string $state, $record): string =>
                        $record->type === 'percentage' ? "{$state}%" : "{$state} EGP"
                    ),
                IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('expiry_date')
                    ->dateTime()
                    ->sortable()
                    ->color(fn($state) => now()->gt($state) ? 'danger' : 'success'),
                TextColumn::make('usage_count')
                    ->label('Used')
                    ->sortable()
                    ->formatStateUsing(fn(string $state, $record): string =>
                        $record->max_usage ? "{$state} / {$record->max_usage}" : $state
                    ),
                TextColumn::make('total_consumed')
                    ->label('Total Discount')
                    ->sortable()
                    ->money('EGP')
                    ->toggleable(),
                TextColumn::make('created_at')
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
                SelectFilter::make('type')
                    ->options([
                        'percentage' => 'Percentage',
                        'fixed' => 'Fixed Amount',
                    ]),
                TernaryFilter::make('expired')
                    ->label('Status')
                    ->queries(
                        true: fn($query) => $query->where('expiry_date', '<', now()),
                        false: fn($query) => $query->where('expiry_date', '>=', now()),
                    )
                    ->trueLabel('Expired')
                    ->falseLabel('Active'),
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
            ->defaultSort('created_at', 'desc');
    }
}
