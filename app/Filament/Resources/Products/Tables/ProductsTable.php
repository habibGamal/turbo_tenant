<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Tables;

use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

final class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->square(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('base_price')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('price_after_discount')
                    ->money('USD')
                    ->sortable(),
                IconColumn::make('sell_by_weight')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('is_active')
                    ->toggle(),
                Filter::make('sell_by_weight')
                    ->toggle(),
                SelectFilter::make('category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => true]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => false]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('enable_sell_by_weight')
                        ->label('Enable Sell by Weight')
                        ->icon('heroicon-o-scale')
                        ->color('info')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update(['sell_by_weight' => true]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('disable_sell_by_weight')
                        ->label('Disable Sell by Weight')
                        ->icon('heroicon-o-scale')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update(['sell_by_weight' => false]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('change_category')
                        ->label('Change Category')
                        ->icon('heroicon-o-tag')
                        ->color('warning')
                        ->form([
                            Select::make('category_id')
                                ->label('New Category')
                                ->relationship('category', 'name')
                                ->searchable()
                                ->preload()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $records->each->update(['category_id' => $data['category_id']]);
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('apply_extra_option')
                        ->label('Apply Extra Option')
                        ->icon('heroicon-o-plus-circle')
                        ->color('info')
                        ->form([
                            Select::make('extra_option_id')
                                ->label('Extra Option')
                                ->relationship('extraOption', 'name')
                                ->searchable()
                                ->preload()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $records->each->update(['extra_option_id' => $data['extra_option_id']]);
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('apply_weight_option')
                        ->label('Apply Weight Option')
                        ->icon('heroicon-o-calculator')
                        ->color('success')
                        ->form([
                            Select::make('weight_options_id')
                                ->label('Weight Option')
                                ->relationship('weightOption', 'name')
                                ->searchable()
                                ->preload()
                                ->required(),
                            Toggle::make('enable_sell_by_weight')
                                ->label('Also enable "Sell by Weight"')
                                ->default(true),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $updateData = ['weight_options_id' => $data['weight_options_id']];
                            if ($data['enable_sell_by_weight']) {
                                $updateData['sell_by_weight'] = true;
                            }
                            $records->each->update($updateData);
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
