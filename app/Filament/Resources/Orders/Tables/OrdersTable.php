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
                    ->label('رقم الطلب')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('user.name')
                    ->label('العميل')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('النوع')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'web_delivery' => 'success',
                        'web_takeaway' => 'info',
                        'pos' => 'warning',
                    })
                    ->sortable(),
                TextColumn::make('status')
                    ->label('الحالة')
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
                    ->label('الفرع')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('total')
                    ->label('الإجمالي')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('تاريخ الطلب')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'pending' => 'قيد الانتظار',
                        'processing' => 'قيد التجهيز',
                        'out_for_delivery' => 'خارج للتوصيل',
                        'completed' => 'مكتمل',
                        'cancelled' => 'ملغى',
                    ])
                    ->multiple(),
                SelectFilter::make('type')
                    ->label('النوع')
                    ->options([
                        'web_delivery' => 'توصيل (ويب)',
                        'web_takeaway' => 'استلام (ويب)',
                        'pos' => 'POS',
                    ])
                    ->multiple(),
                SelectFilter::make('branch')
                    ->label('الفرع')
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
