<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('user.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'web_delivery' => 'success',
                        'web_takeaway' => 'info',
                        'pos' => 'warning',
                    })
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string|\App\Enums\OrderStatus $state): string => match ($state instanceof \App\Enums\OrderStatus ? $state->value : $state) {
                        'pending' => 'gray',
                        'processing' => 'warning',
                        'out_for_delivery' => 'primary',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                    })
                    ->sortable(),
                TextColumn::make('branch.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('total')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Order Date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                        'out_for_delivery' => 'Out for Delivery',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ])
                    ->multiple(),
                SelectFilter::make('type')
                    ->options([
                        'web_delivery' => 'Web Delivery',
                        'web_takeaway' => 'Web Takeaway',
                        'pos' => 'POS',
                    ])
                    ->multiple(),
                SelectFilter::make('branch')
                    ->relationship('branch', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
