<?php

declare(strict_types=1);

namespace App\Plugins\CmsPages\src\Filament\Resources\Pages\Tables;

use App\Plugins\CmsPages\src\Filament\Resources\Pages\PageResource;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class PagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('العنوان (إنجليزي)')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title_ar')
                    ->label('العنوان (عربي)')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('رابط مختصر')
                    ->searchable()
                    ->copyable(),
                ToggleColumn::make('is_active')
                    ->label('نشط')
                    ->sortable(),
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
                TernaryFilter::make('is_active')
                    ->label('الحالة')
                    ->boolean()
                    ->trueLabel('نشط فقط')
                    ->falseLabel('غير نشط فقط')
                    ->native(false),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('view_live')
                    ->label('عرض الصفحة')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn ($record) => url("/page/{$record->slug}"))
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('لا توجد صفحات محتوى')
            ->emptyStateDescription('أنشئ صفحات مثل الشروط والأحكام، سياسة الخصوصية، أو من نحن لمتجرك.')
            ->emptyStateIcon('heroicon-o-document-text')
            ->emptyStateActions([
                Action::make('create')
                    ->label('إنشاء صفحة جديدة')
                    ->url(fn () => PageResource::getUrl('create'))
                    ->icon('heroicon-m-plus')
                    ->button(),
            ]);
    }
}
