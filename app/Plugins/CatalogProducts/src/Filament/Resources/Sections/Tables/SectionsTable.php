<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Filament\Resources\Sections\Tables;

use App\Plugins\CatalogProducts\src\Filament\Resources\Sections\SectionResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class SectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order', 'asc')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('title')
                    ->label('العنوان')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('title_ar')
                    ->label('العنوان بالعربية')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('location')
                    ->label('الموقع')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'home' => 'الرئيسية',
                        'menu' => 'القائمة',
                        'featured' => 'مميز',
                        default => (string) $state,
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'home' => 'primary',
                        'menu' => 'info',
                        'featured' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('products_count')
                    ->counts('products')
                    ->label('عدد المنتجات')
                    ->badge()
                    ->color('info'),

                TextColumn::make('sort_order')
                    ->label('ترتيب العرض')
                    ->numeric()
                    ->sortable(),

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
                SelectFilter::make('location')
                    ->label('الموقع')
                    ->options([
                        'home' => 'الرئيسية',
                        'menu' => 'القائمة',
                        'featured' => 'مميز',
                    ]),

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
            ->emptyStateHeading('لا توجد أقسام حتى الآن')
            ->emptyStateDescription('أنشئ أقساماً في الصفحة الرئيسية أو القائمة لعرض مجموعات المنتجات.')
            ->emptyStateIcon('heroicon-o-squares-2x2')
            ->emptyStateActions([
                CreateAction::make()
                    ->url(fn (): string => SectionResource::getUrl('create'))
                    ->label('إضافة قسم'),
            ]);
    }
}
