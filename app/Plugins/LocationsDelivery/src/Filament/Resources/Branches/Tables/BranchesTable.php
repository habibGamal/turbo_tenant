<?php

declare(strict_types=1);

namespace App\Plugins\LocationsDelivery\src\Filament\Resources\Branches\Tables;

use App\Plugins\LocationsDelivery\src\Filament\Resources\Branches\BranchResource;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class BranchesTable
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
                TextColumn::make('link')
                    ->label('الرابط')
                    ->url(fn ($record) => $record->link, shouldOpenInNewTab: true)
                    ->openUrlInNewTab()
                    ->limit(50)
                    ->searchable(),
                ToggleColumn::make('is_active')
                    ->label('نشط')
                    ->sortable(),
                TextColumn::make('areas_count')
                    ->counts('areas')
                    ->label('المناطق المغطاة')
                    ->badge()
                    ->sortable(),
                TextColumn::make('orders_count')
                    ->counts('orders')
                    ->label('إجمالي الطلبات')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label('تاريخ التحديث')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('الحالة')
                    ->placeholder('الكل')
                    ->trueLabel('نشط فقط')
                    ->falseLabel('غير نشط فقط'),
            ])
            ->emptyStateHeading('لا توجد فروع')
            ->emptyStateDescription('لم تضف أي فروع بعد — أضف الفرع الأول')
            ->emptyStateActions([
                Action::make('create')
                    ->label('أضف أول فرع')
                    ->url(fn (): string => BranchResource::getUrl('create'))
                    ->icon('heroicon-m-plus')
                    ->button(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
