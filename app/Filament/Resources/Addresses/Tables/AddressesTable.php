<?php

declare(strict_types=1);

namespace App\Filament\Resources\Addresses\Tables;

use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class AddressesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('المستخدم')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('area.name')
                    ->label('المنطقة')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('area.governorate.name')
                    ->label('المحافظة')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('phone_number')
                    ->label('رقم الهاتف')
                    ->searchable(),

                TextColumn::make('street')
                    ->label('الشارع')
                    ->searchable()
                    ->limit(30),

                TextColumn::make('building')
                    ->label('المبنى')
                    ->searchable(),

                TextColumn::make('floor')
                    ->label('الدور')
                    ->searchable(),

                TextColumn::make('apartment')
                    ->label('الشقة')
                    ->searchable(),

                IconColumn::make('is_default')
                    ->label('افتراضي')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('user_id')
                    ->label('المستخدم')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('area_id')
                    ->label('المنطقة')
                    ->relationship('area', 'name')
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('is_default')
                    ->label('افتراضي')
                    ->placeholder('الكل')
                    ->trueLabel('افتراضي فقط')
                    ->falseLabel('غير افتراضي فقط'),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
