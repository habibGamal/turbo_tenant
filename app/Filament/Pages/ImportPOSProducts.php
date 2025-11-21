<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Services\ProductPOSImporterService;
use BackedEnum;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use UnitEnum;

final class ImportPOSProducts extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static ?string $navigationLabel = 'استيراد منتجات POS';

    protected static ?string $title = 'استيراد منتجات من نقطة البيع';

    protected string $view = 'filament.pages.import-p-o-s-products';

    protected static string|UnitEnum|null $navigationGroup = 'المنتجات';

    public function table(Table $table): Table
    {
        return $table
            ->heading('المنتجات المتاحة للاستيراد')
            ->description('اختر المنتجات التي تريد استيرادها من نقطة البيع الرئيسية')
            ->records(function (array $filters): array {
                try {
                    $service = app(ProductPOSImporterService::class);
                    $newProducts = $service->findNewProducts();

                    // Apply category filter
                    if (filled($filters['categoryName']['value'] ?? null)) {
                        $newProducts = $newProducts->where('categoryName', $filters['categoryName']['value']);
                    }

                    // Apply type filter
                    if (filled($filters['type']['value'] ?? null)) {
                        $newProducts = $newProducts->where('type', $filters['type']['value']);
                    }

                    return $newProducts->map(function ($product, $index) {
                        return [
                            'id' => $product['id'],
                            'posRef' => $product['posRef'],
                            'name' => $product['name'],
                            'type' => $product['type'],
                            'categoryId' => $product['categoryId'],
                            'categoryName' => $product['categoryName'],
                            'index' => $index,
                        ];
                    })->values()->toArray();
                } catch (Exception $e) {
                    Notification::make()
                        ->title('خطأ في الاتصال')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return [];
                }
            })
            ->columns([
                TextColumn::make('categoryName')
                    ->label('الفئة')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                TextColumn::make('name')
                    ->label('اسم المنتج')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('posRef')
                    ->label('رقم المرجع')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('تم نسخ رقم المرجع')
                    ->toggleable(),

                TextColumn::make('type')
                    ->label('نوع المنتج')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'manufactured', 'manifactured' => 'success',
                        'consumable' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'manufactured', 'manifactured' => 'مُصنّع',
                        'consumable' => 'مستهلك',
                        default => $state,
                    })
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('categoryName')
                    ->label('الفئة')
                    ->options(function (): array {
                        try {
                            $service = app(ProductPOSImporterService::class);
                            $newProducts = $service->findNewProducts();

                            return $newProducts
                                ->pluck('categoryName', 'categoryName')
                                ->unique()
                                ->toArray();
                        } catch (Exception $e) {
                            return [];
                        }
                    })
                    ->searchable()
                    ->preload(),

                SelectFilter::make('type')
                    ->label('نوع المنتج')
                    ->options([
                        'manufactured' => 'مُصنّع',
                        'manifactured' => 'مُصنّع',
                        'consumable' => 'مستهلك',
                    ])
                    ->searchable(),
            ])
            ->selectable()
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('import')
                        ->label('استيراد المحدد')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('تأكيد استيراد المنتجات')
                        ->modalDescription('هل أنت متأكد من استيراد المنتجات المحددة؟')
                        ->modalSubmitActionLabel('نعم، استورد')
                        ->action(function (Collection $records): void {
                            $this->importSelectedProducts($records);
                        }),
                ]),

                Action::make('refresh')
                    ->label('تحديث القائمة')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->action(function (): void {
                        Notification::make()
                            ->title('تم تحديث القائمة')
                            ->success()
                            ->send();
                    }),

                Action::make('importAll')
                    ->label('استيراد الكل')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('تأكيد استيراد جميع المنتجات')
                    ->modalDescription('هل أنت متأكد من استيراد جميع المنتجات الجديدة؟ قد يستغرق هذا بعض الوقت.')
                    ->modalSubmitActionLabel('نعم، استورد الكل')
                    ->action(function (): void {
                        $this->importAllProducts();
                    }),
            ])
            ->emptyStateHeading('لا توجد منتجات جديدة')
            ->emptyStateDescription('جميع المنتجات من نقطة البيع موجودة بالفعل في النظام')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated([10, 25, 50, 100]);
    }

    protected function importSelectedProducts(Collection $records): void
    {
        try {
            $service = app(ProductPOSImporterService::class);
            $productIds = $records->pluck('id')->toArray();

            $result = $service->importProducts($productIds);

            if ($result['imported'] > 0) {
                Notification::make()
                    ->title('تم الاستيراد بنجاح')
                    ->body("تم استيراد {$result['imported']} منتج بنجاح")
                    ->success()
                    ->send();
            }

            if ($result['failed'] > 0) {
                Notification::make()
                    ->title('تحذير')
                    ->body("فشل استيراد {$result['failed']} منتج")
                    ->warning()
                    ->send();

                foreach ($result['errors'] as $error) {
                    Log::warning($error);
                }
            }
        } catch (Exception $e) {
            Notification::make()
                ->title('خطأ في الاستيراد')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function importAllProducts(): void
    {
        try {
            $service = app(ProductPOSImporterService::class);
            $newProducts = $service->findNewProducts();
            $productIds = $newProducts->pluck('id')->toArray();

            if (empty($productIds)) {
                Notification::make()
                    ->title('لا توجد منتجات للاستيراد')
                    ->warning()
                    ->send();

                return;
            }

            $result = $service->importProducts($productIds);

            if ($result['imported'] > 0) {
                Notification::make()
                    ->title('تم الاستيراد بنجاح')
                    ->body("تم استيراد {$result['imported']} منتج بنجاح")
                    ->success()
                    ->duration(5000)
                    ->send();
            }

            if ($result['failed'] > 0) {
                Notification::make()
                    ->title('تحذير')
                    ->body("فشل استيراد {$result['failed']} منتج")
                    ->warning()
                    ->send();

                foreach ($result['errors'] as $error) {
                    Log::warning($error);
                }
            }
        } catch (Exception $e) {
            Notification::make()
                ->title('خطأ في الاستيراد')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
