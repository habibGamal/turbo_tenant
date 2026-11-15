<?php

declare(strict_types=1);

namespace App\Plugins\ComboPackages\src\Filament\Resources\Packages\Tables;

use App\Core\Plugins\Contracts\TableColumnContributor;
use App\Core\Plugins\Services\PluginContributions;
use App\Plugins\ComboPackages\src\Filament\Resources\Packages\PackageResource;
use App\Plugins\ComboPackages\src\Models\Package;
use App\Plugins\ComboPackages\src\Services\PackageAvailabilityService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class PackagesTable
{
    public static function configure(Table $table): Table
    {
        /** @var PluginContributions $pluginContributions */
        $pluginContributions = app(PluginContributions::class);
        $contributedColumns = [];
        foreach ($pluginContributions->active('admin.table.combo-packages.packages') as $contributor) {
            if ($contributor instanceof TableColumnContributor) {
                foreach ($contributor->columns() as $column) {
                    $contributedColumns[] = $column;
                }
            }
        }

        $columns = [
            ImageColumn::make('image')
                ->label('الصورة')
                ->square()
                ->defaultImageUrl(url('/images/placeholder.png')),

            TextColumn::make('name')
                ->label('الاسم')
                ->searchable()
                ->sortable()
                ->description(fn ($record) => $record->description)
                ->limit(30),

            TextColumn::make('name_ar')
                ->label('الاسم (عربي)')
                ->searchable()
                ->sortable()
                ->toggleable(),

            TextColumn::make('price')
                ->label('السعر')
                ->money('EGP')
                ->sortable()
                ->badge()
                ->color('success'),

            TextColumn::make('original_price')
                ->label('السعر الأصلي')
                ->money('EGP')
                ->sortable()
                ->toggleable()
                ->color('gray'),

            TextColumn::make('discount_percentage')
                ->label('نسبة الخصم')
                ->suffix('%')
                ->sortable()
                ->toggleable()
                ->badge()
                ->color('warning'),

            TextColumn::make('health_status')
                ->label('الحالة')
                ->badge()
                ->state(fn (Package $record): string => app(PackageAvailabilityService::class)->determineHealthStatus($record))
                ->color(fn (string $state): string => match ($state) {
                    'متاحة' => 'success',
                    'مجدولة' => 'info',
                    'غير مكتملة' => 'warning',
                    'خارج أوقات التوفر' => 'warning',
                    'نفدت اليوم' => 'danger',
                    'منتهية' => 'danger',
                    'غير نشطة' => 'gray',
                    default => 'gray',
                }),

            TextColumn::make('validity_status')
                ->label('حالة الصلاحية')
                ->badge()
                ->toggleable()
                ->state(function ($record): string {
                    if ($record->valid_until && now()->gt($record->valid_until)) {
                        return 'منتهية';
                    }
                    if ($record->valid_from && now()->lt($record->valid_from)) {
                        return 'مجدولة';
                    }

                    return 'سارية';
                })
                ->color(fn (string $state): string => match ($state) {
                    'سارية' => 'success',
                    'مجدولة' => 'info',
                    'منتهية' => 'danger',
                    default => 'gray',
                }),

            TextColumn::make('badge')
                ->label('الشارة')
                ->badge()
                ->color('primary')
                ->toggleable(),

            ToggleColumn::make('is_active')
                ->label('نشط')
                ->sortable(),

            ToggleColumn::make('is_featured')
                ->label('مميز')
                ->sortable(),

            TextColumn::make('groups_count')
                ->counts('groups')
                ->label('عدد المجموعات')
                ->badge()
                ->color('info'),

            TextColumn::make('items_count')
                ->counts('items')
                ->label('عدد العناصر')
                ->badge()
                ->color('info'),

            TextColumn::make('sort_order')
                ->label('ترتيب العرض')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('valid_from')
                ->label('صالح من')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('valid_until')
                ->label('صالح حتى')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

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
        ];

        return $table
            ->modifyQueryUsing(function ($query): void {
                $query->with([
                    'availabilities',
                    'branches',
                    'groups.items.product.variants',
                    'groups.items.product',
                    'groups.items.variant',
                ]);
            })
            ->reorderable('sort_order')
            ->defaultSort('sort_order', 'asc')
            ->columns(array_merge($columns, $contributedColumns))
            ->filters([
                SelectFilter::make('health_status')
                    ->label('الحالة')
                    ->options([
                        'inactive' => 'غير نشطة',
                        'expired' => 'منتهية',
                        'scheduled' => 'مجدولة',
                        'unfulfillable' => 'غير مكتملة',
                        'sold_out' => 'نفدت اليوم',
                        'outside_hours' => 'خارج أوقات التوفر',
                        'available' => 'متاحة',
                    ])
                    ->query(function ($query, array $state) {
                        $value = $state['value'] ?? null;
                        if (! $value) {
                            return $query;
                        }

                        $expectedLabel = match ($value) {
                            'inactive' => 'غير نشطة',
                            'expired' => 'منتهية',
                            'scheduled' => 'مجدولة',
                            'unfulfillable' => 'غير مكتملة',
                            'sold_out' => 'نفدت اليوم',
                            'outside_hours' => 'خارج أوقات التوفر',
                            'available' => 'متاحة',
                            default => null,
                        };

                        if ($expectedLabel === null) {
                            return $query;
                        }

                        $service = app(PackageAvailabilityService::class);
                        $packages = Package::with([
                            'availabilities',
                            'groups.items.product.variants',
                            'groups.items.product',
                            'groups.items.variant',
                        ])->get();

                        $matchingIds = $packages
                            ->filter(fn (Package $pkg): bool => $service->determineHealthStatus($pkg) === $expectedLabel)
                            ->pluck('id')
                            ->all();

                        return $query->whereIn('id', $matchingIds);
                    }),

                TernaryFilter::make('is_active')
                    ->label('نشط')
                    ->boolean()
                    ->trueLabel('نشط فقط')
                    ->falseLabel('غير نشط فقط')
                    ->native(false),

                TernaryFilter::make('is_featured')
                    ->label('مميز')
                    ->boolean()
                    ->trueLabel('مميز فقط')
                    ->falseLabel('غير مميز')
                    ->native(false),

                Filter::make('valid')
                    ->label('صالح حالياً')
                    ->query(fn ($query) => $query
                        ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', now()))
                        ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', now()))
                    ),

                Filter::make('expired')
                    ->label('منتهي الصلاحية')
                    ->query(fn ($query) => $query->whereNotNull('valid_until')->where('valid_until', '<', now())),

                SelectFilter::make('has_discount')
                    ->label('الخصم')
                    ->options([
                        'yes' => 'بخصم',
                        'no' => 'بدون خصم',
                    ])
                    ->query(function ($query, $state) {
                        if ($state['value'] === 'yes') {
                            return $query->whereNotNull('discount_percentage')->where('discount_percentage', '>', 0);
                        }
                        if ($state['value'] === 'no') {
                            return $query->whereNull('discount_percentage')->orWhere('discount_percentage', '=', 0);
                        }
                    }),
            ])
            ->recordActions([
                Action::make('duplicate')
                    ->label('نسخ الباقة')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(function (Package $record) {
                        $copy = DB::transaction(function () use ($record) {
                            $newPackage = $record->replicate(['groups_count', 'items_count']);
                            unset($newPackage->groups_count, $newPackage->items_count);
                            $newPackage->name = $record->name ? "{$record->name} (نسخة)" : null;
                            if ($record->name_ar) {
                                $newPackage->name_ar = "{$record->name_ar} (نسخة)";
                            }
                            $newPackage->is_active = false;
                            $newPackage->save();

                            $record->loadMissing('groups.items');

                            foreach ($record->groups as $group) {
                                $newGroup = $group->replicate();
                                $newGroup->package_id = $newPackage->id;
                                $newGroup->save();

                                foreach ($group->items as $item) {
                                    $newItem = $item->replicate();
                                    $newItem->package_group_id = $newGroup->id;
                                    $newItem->save();
                                }
                            }

                            return $newPackage;
                        });

                        Notification::make()
                            ->title('تم نسخ الباقة بنجاح')
                            ->success()
                            ->send();

                        return redirect(PackageResource::getUrl('edit', ['record' => $copy]));
                    }),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('activate')
                        ->label('تفعيل')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => true]))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('deactivate')
                        ->label('إيقاف')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => false]))
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('لا توجد باقات توفير')
            ->emptyStateDescription('أنشئ أول باقة وجبات أو عروض مجمعة لجذب عملائك وزيادة قيمة الطلب.')
            ->emptyStateIcon('heroicon-o-archive-box')
            ->emptyStateActions([
                Action::make('create')
                    ->label('إنشاء باقة جديدة')
                    ->url(fn () => PackageResource::getUrl('create'))
                    ->icon('heroicon-m-plus')
                    ->button(),
            ]);
    }
}
