<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Tables;

use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
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
                    ->label('الصورة')
                    ->square(),
                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name_ar')
                    ->label('الاسم بالعربية')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('category.name')
                    ->label('الفئة')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('base_price')
                    ->label('السعر الأساسي')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('price_after_discount')
                    ->label('السعر بعد الخصم')
                    ->money('USD')
                    ->sortable(),
                IconColumn::make('sell_by_weight')
                    ->label('بيع بالوزن')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label('نشط')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('is_active')
                    ->label('نشط')
                    ->toggle(),
                Filter::make('sell_by_weight')
                    ->label('بيع بالوزن')
                    ->toggle(),
                SelectFilter::make('category')
                    ->label('الفئة')
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
                        ->label('تفعيل المحدد')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => true]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('deactivate')
                        ->label('إلغاء تفعيل المحدد')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => false]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('enable_sell_by_weight')
                        ->label('تفعيل البيع بالوزن')
                        ->icon('heroicon-o-scale')
                        ->color('info')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update(['sell_by_weight' => true]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('disable_sell_by_weight')
                        ->label('إلغاء البيع بالوزن')
                        ->icon('heroicon-o-scale')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update(['sell_by_weight' => false]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('change_category')
                        ->label('تغيير الفئة')
                        ->icon('heroicon-o-tag')
                        ->color('warning')
                        ->form([
                            Select::make('category_id')
                                ->label('الفئة الجديدة')
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
                        ->label('تطبيق خيار إضافي')
                        ->icon('heroicon-o-plus-circle')
                        ->color('info')
                        ->form([
                            Select::make('extra_option_id')
                                ->label('الخيار الإضافي')
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
                        ->label('تطبيق خيار الوزن')
                        ->icon('heroicon-o-calculator')
                        ->color('success')
                        ->form([
                            Select::make('weight_options_id')
                                ->label('خيار الوزن')
                                ->relationship('weightOption', 'name')
                                ->searchable()
                                ->preload()
                                ->required(),
                            Toggle::make('enable_sell_by_weight')
                                ->label('تفعيل "البيع بالوزن" أيضاً')
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
                    BulkAction::make('quickAddVariants')
                        ->label('إضافة متغيرات سريعة')
                        ->icon('heroicon-o-bolt')
                        ->color('info')
                        ->form([
                            TagsInput::make('variant_names')
                                ->label('أسماء المتغيرات')
                                ->placeholder('أدخل اسم المتغير واضغط Enter')
                                ->helperText('أضف أسماء المتغيرات بسرعة. اضغط Enter بعد كل اسم. سيتم إنشاء المتغيرات بإعدادات افتراضية.')
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $variantNames = $data['variant_names'];
                            $totalVariantsCreated = 0;

                            foreach ($records as $product) {
                                $maxSortOrder = $product->variants()->max('sort_order') ?? 0;

                                foreach ($variantNames as $index => $name) {
                                    $product->variants()->create([
                                        'name' => $name,
                                        'is_available' => true,
                                        'sort_order' => $maxSortOrder + $index + 1,
                                    ]);
                                    $totalVariantsCreated++;
                                }
                            }

                            Notification::make()
                                ->title('تم إنشاء المتغيرات بنجاح')
                                ->body("{$totalVariantsCreated} متغير(ات) تمت إضافتها إلى {$records->count()} منتج(ات).")
                                ->success()
                                ->send();
                        })
                        ->successNotificationTitle('تم إنشاء المتغيرات')
                        ->modalWidth('md')
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
