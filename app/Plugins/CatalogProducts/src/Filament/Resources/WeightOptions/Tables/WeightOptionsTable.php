<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Filament\Resources\WeightOptions\Tables;

use App\Plugins\CatalogProducts\src\Filament\Resources\WeightOptions\WeightOptionResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class WeightOptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name', 'asc')
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
                    ->label('عدد القيم')
                    ->badge(),
                TextColumn::make('products_count')
                    ->counts('products')
                    ->label('عدد المنتجات')
                    ->badge()
                    ->color('info'),
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
                SelectFilter::make('unit')
                    ->label('الوحدة')
                    ->options([
                        'kg' => 'كيلوجرام (kg)',
                        'g' => 'جرام (g)',
                        'lb' => 'رطل (lb)',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('لا توجد خيارات وزن')
            ->emptyStateDescription('أضف خيارات وقوالب الأوزان للمنتجات التي تُباع بالوزن.')
            ->emptyStateIcon('heroicon-o-scale')
            ->emptyStateActions([
                CreateAction::make()
                    ->url(fn (): string => WeightOptionResource::getUrl('create'))
                    ->label('إضافة خيار وزن'),
            ]);
    }
}
