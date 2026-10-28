<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Filament\Resources\ExtraOptions\Tables;

use App\Plugins\CatalogProducts\src\Filament\Resources\ExtraOptions\ExtraOptionResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class ExtraOptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('اسم المجموعة')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name_ar')
                    ->label('الاسم بالعربية')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('selection_rules')
                    ->label('قواعد الاختيار')
                    ->state(fn ($record) => match (true) {
                        $record->min_selections > 0 && $record->max_selections => "إجباري ({$record->min_selections} إلى {$record->max_selections})",
                        $record->min_selections > 0 => "إجباري (حد أدنى {$record->min_selections})",
                        $record->max_selections > 0 => "اختياري (أقصى حد {$record->max_selections})",
                        default => 'اختياري مفتوح',
                    })
                    ->badge()
                    ->color(fn ($record) => $record->min_selections > 0 ? 'warning' : 'gray'),

                IconColumn::make('allow_multiple')
                    ->label('اختيار متعدد')
                    ->boolean(),

                TextColumn::make('items_count')
                    ->counts('items')
                    ->label('عناصر الإضافة')
                    ->badge(),

                TextColumn::make('products_count')
                    ->counts('products')
                    ->label('المنتجات المرتبطة')
                    ->badge()
                    ->color('info'),

                ToggleColumn::make('is_active')
                    ->label('نشط')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_mandatory')
                    ->label('نوع الإلزام')
                    ->queries(
                        true: fn ($query) => $query->where('min_selections', '>', 0),
                        false: fn ($query) => $query->where(fn ($q) => $q->where('min_selections', 0)->orWhereNull('min_selections')),
                    )
                    ->trueLabel('إجباري فقط')
                    ->falseLabel('اختياري فقط'),

                TernaryFilter::make('is_active')
                    ->label('الحالة'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('لا توجد خيارات إضافية')
            ->emptyStateDescription('أضف مجموعات إضافات مثل الإضافات والصلصات لربطها بالمنتجات.')
            ->emptyStateIcon('heroicon-o-plus-circle')
            ->emptyStateActions([
                CreateAction::make()
                    ->url(fn (): string => ExtraOptionResource::getUrl('create'))
                    ->label('إضافة خيار إضافي'),
            ]);
    }
}
