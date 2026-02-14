<?php

declare(strict_types=1);

namespace App\Filament\Resources\GuestUsers\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class GuestUsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->label('الهاتف')
                    ->formatStateUsing(fn ($record) => $record->full_phone)
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('email')
                    ->label('البريد الإلكتروني')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->placeholder('—'),
                TextColumn::make('city')
                    ->label('المدينة')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('area.name')
                    ->label('المنطقة')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('orders_count')
                    ->label('عدد الطلبات')
                    ->counts('orders')
                    ->badge()
                    ->color('success')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('تاريخ التسجيل')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Filter::make('has_email')
                    ->label('لديه بريد إلكتروني')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('email')),
                Filter::make('has_orders')
                    ->label('لديه طلبات')
                    ->query(fn (Builder $query): Builder => $query->has('orders')),
                SelectFilter::make('area')
                    ->label('المنطقة')
                    ->relationship('area', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
