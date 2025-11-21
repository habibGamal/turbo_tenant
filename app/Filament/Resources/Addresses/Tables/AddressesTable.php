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
                    ->label('User')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('area.name')
                    ->label('Area')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('area.governorate.name')
                    ->label('Governorate')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('phone_number')
                    ->label('Phone')
                    ->searchable(),

                TextColumn::make('street')
                    ->label('Street')
                    ->searchable()
                    ->limit(30),

                TextColumn::make('building')
                    ->label('Building')
                    ->searchable(),

                TextColumn::make('floor')
                    ->label('Floor')
                    ->searchable(),

                TextColumn::make('apartment')
                    ->label('Apartment')
                    ->searchable(),

                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('user_id')
                    ->label('User')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('area_id')
                    ->label('Area')
                    ->relationship('area', 'name')
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('is_default')
                    ->label('Default')
                    ->placeholder('All')
                    ->trueLabel('Default only')
                    ->falseLabel('Non-default only'),
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
