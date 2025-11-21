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
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use UnitEnum;

final class SyncPOSPrices extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationLabel = 'مزامنة الأسعار';

    protected static ?string $title = 'مزامنة أسعار المنتجات';

    protected string $view = 'filament.pages.sync-p-o-s-prices';

    protected static string|UnitEnum|null $navigationGroup = 'المنتجات';

    public function table(Table $table): Table
    {
        return $table
            ->heading('المنتجات ذات الأسعار المختلفة')
            ->description('المنتجات التي تختلف أسعارها عن الأسعار في نقطة البيع الرئيسية')
            ->records(function (): array {
                try {
                    $service = app(ProductPOSImporterService::class);

                    $productsWithChanges = $service->findProductsWithPriceChanges();

                    return $productsWithChanges->map(function ($product, $index) {
                        return [
                            'id' => $product['id'],
                            'name' => $product['name'],
                            'posRef' => $product['posRef'],
                            'product_id' => $product['product_id'],
                            'localPrice' => $product['localPrice'],
                            'localPriceAfterDiscount' => $product['localPriceAfterDiscount'],
                            'masterPrice' => $product['masterPrice'],
                            'masterPriceAfterDiscount' => $product['masterPriceAfterDiscount'],
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
                TextColumn::make('name')
                    ->label('اسم المنتج')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('localPrice')
                    ->label('السعر الحالي')
                    ->money('EGP')
                    ->color('danger'),

                TextColumn::make('masterPrice')
                    ->label('السعر الجديد')
                    ->money('EGP')
                    ->color('success'),

                TextColumn::make('localPriceAfterDiscount')
                    ->label('السعر بعد الخصم (حالي)')
                    ->money('EGP')
                    ->color('danger'),

                TextColumn::make('masterPriceAfterDiscount')
                    ->label('السعر بعد الخصم (جديد)')
                    ->money('EGP')
                    ->color('success'),
                TextColumn::make('posRef')
                    ->label('رقم المرجع')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
            ])
            ->selectable()
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('sync')
                        ->label('مزامنة المحدد')
                        ->icon('heroicon-o-arrow-path')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('تأكيد مزامنة الأسعار')
                        ->modalDescription('هل أنت متأكد من تحديث أسعار المنتجات المحددة؟')
                        ->modalSubmitActionLabel('نعم، حدّث الأسعار')
                        ->action(function (Collection $records): void {
                            $this->syncSelectedPrices($records);
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

                Action::make('syncAll')
                    ->label('مزامنة الكل')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('تأكيد مزامنة جميع الأسعار')
                    ->modalDescription('هل أنت متأكد من تحديث أسعار جميع المنتجات المختلفة؟')
                    ->modalSubmitActionLabel('نعم، حدّث الكل')
                    ->action(function (): void {
                        $this->syncAllPrices();
                    }),
            ])
            ->emptyStateHeading('لا توجد اختلافات في الأسعار')
            ->emptyStateDescription('جميع أسعار المنتجات متزامنة مع نقطة البيع الرئيسية')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated([10, 25, 50, 100]);
    }

    protected function syncSelectedPrices(Collection $records): void
    {
        try {
            $service = app(ProductPOSImporterService::class);
            $productIds = $records->pluck('id')->toArray();

            $result = $service->updateProductPrices($productIds);

            if ($result['updated'] > 0) {
                Notification::make()
                    ->title('تم التحديث بنجاح')
                    ->body("تم تحديث أسعار {$result['updated']} منتج بنجاح")
                    ->success()
                    ->send();
            }

            if ($result['failed'] > 0) {
                Notification::make()
                    ->title('تحذير')
                    ->body("فشل تحديث {$result['failed']} منتج")
                    ->warning()
                    ->send();

                foreach ($result['errors'] as $error) {
                    Log::warning($error);
                }
            }
        } catch (Exception $e) {
            Notification::make()
                ->title('خطأ في التحديث')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function syncAllPrices(): void
    {
        try {
            $service = app(ProductPOSImporterService::class);
            $productsWithChanges = $service->findProductsWithPriceChanges();
            $productIds = $productsWithChanges->pluck('id')->toArray();

            if (empty($productIds)) {
                Notification::make()
                    ->title('لا توجد أسعار للتحديث')
                    ->warning()
                    ->send();

                return;
            }

            $result = $service->updateProductPrices($productIds);

            if ($result['updated'] > 0) {
                Notification::make()
                    ->title('تم التحديث بنجاح')
                    ->body("تم تحديث أسعار {$result['updated']} منتج بنجاح")
                    ->success()
                    ->duration(5000)
                    ->send();
            }

            if ($result['failed'] > 0) {
                Notification::make()
                    ->title('تحذير')
                    ->body("فشل تحديث {$result['failed']} منتج")
                    ->warning()
                    ->send();

                foreach ($result['errors'] as $error) {
                    Log::warning($error);
                }
            }
        } catch (Exception $e) {
            Notification::make()
                ->title('خطأ في التحديث')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
