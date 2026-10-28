<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Core\Plugins\Services\PluginManager;
use App\Core\Plugins\Services\PluginRegistry;
use Filament\Widgets\Widget;
use Throwable;

final class StoreSetupChecklist extends Widget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.store-setup-checklist';

    public static function canView(): bool
    {
        $items = self::getApplicableItems();

        if (empty($items)) {
            return false;
        }

        foreach ($items as $item) {
            if (! $item['is_completed']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array{id: string, title: string, description: string, is_completed: bool, url: ?string}>
     */
    public static function getApplicableItems(): array
    {
        $items = [];

        // 1. Store logo set
        if (self::isPluginActive('store-settings') && class_exists(\App\Plugins\StoreSettings\src\Services\SettingService::class)) {
            $logo = null;
            try {
                $logo = app(\App\Plugins\StoreSettings\src\Services\SettingService::class)->get('site_logo');
            } catch (Throwable) {
                $logo = null;
            }

            $url = class_exists(\App\Plugins\StoreSettings\src\Filament\Pages\Settings::class)
                ? \App\Plugins\StoreSettings\src\Filament\Pages\Settings::getUrl()
                : null;

            $items[] = [
                'id' => 'site_logo',
                'title' => 'شعار المتجر',
                'description' => 'قم بإعداد شعار المتجر للظهور في واجهة المتجر والفواتير',
                'is_completed' => ! empty($logo),
                'url' => $url,
            ];
        }

        // 2. At least one active branch
        if (self::isPluginActive('locations-delivery') && class_exists(\App\Plugins\LocationsDelivery\src\Models\Branch::class)) {
            $hasActiveBranch = false;
            try {
                $hasActiveBranch = \App\Plugins\LocationsDelivery\src\Models\Branch::query()->where('is_active', true)->exists();
            } catch (Throwable) {
                $hasActiveBranch = false;
            }

            $url = class_exists(\App\Plugins\LocationsDelivery\src\Filament\Resources\Branches\BranchResource::class)
                ? \App\Plugins\LocationsDelivery\src\Filament\Resources\Branches\BranchResource::getUrl('index')
                : null;

            $items[] = [
                'id' => 'active_branch',
                'title' => 'فرع نشط',
                'description' => 'إضافة فرع نشط واحد على الأقل لاستقبال وتجهيز الطلبات',
                'is_completed' => $hasActiveBranch,
                'url' => $url,
            ];
        }

        // 3. At least one area
        if (self::isPluginActive('locations-delivery') && class_exists(\App\Plugins\LocationsDelivery\src\Models\Area::class)) {
            $hasArea = false;
            try {
                $hasArea = \App\Plugins\LocationsDelivery\src\Models\Area::query()->exists();
            } catch (Throwable) {
                $hasArea = false;
            }

            $url = class_exists(\App\Plugins\LocationsDelivery\src\Filament\Resources\Areas\AreaResource::class)
                ? \App\Plugins\LocationsDelivery\src\Filament\Resources\Areas\AreaResource::getUrl('index')
                : null;

            $items[] = [
                'id' => 'area',
                'title' => 'منطقة توصيل',
                'description' => 'إضافة وتحديد منطقة توصيل واحدة على الأقل وتكلفتها',
                'is_completed' => $hasArea,
                'url' => $url,
            ];
        }

        // 4. At least one category
        if (self::isPluginActive('catalog-products') && class_exists(\App\Plugins\CatalogProducts\src\Models\Category::class)) {
            $hasCategory = false;
            try {
                $hasCategory = \App\Plugins\CatalogProducts\src\Models\Category::query()->exists();
            } catch (Throwable) {
                $hasCategory = false;
            }

            $url = class_exists(\App\Plugins\CatalogProducts\src\Filament\Resources\Categories\CategoryResource::class)
                ? \App\Plugins\CatalogProducts\src\Filament\Resources\Categories\CategoryResource::getUrl('index')
                : null;

            $items[] = [
                'id' => 'category',
                'title' => 'تصنيف منتجات',
                'description' => 'إضافة تصنيف منتجات واحد على الأقل لتنظيم قائمة المأكولات أو المنتجات',
                'is_completed' => $hasCategory,
                'url' => $url,
            ];
        }

        // 5. At least one product
        if (self::isPluginActive('catalog-products') && class_exists(\App\Plugins\CatalogProducts\src\Models\Product::class)) {
            $hasProduct = false;
            try {
                $hasProduct = \App\Plugins\CatalogProducts\src\Models\Product::query()->exists();
            } catch (Throwable) {
                $hasProduct = false;
            }

            $url = class_exists(\App\Plugins\CatalogProducts\src\Filament\Resources\Products\ProductResource::class)
                ? \App\Plugins\CatalogProducts\src\Filament\Resources\Products\ProductResource::getUrl('index')
                : null;

            $items[] = [
                'id' => 'product',
                'title' => 'إضافة منتج',
                'description' => 'إضافة منتج واحد على الأقل في قائمة منتجات المتجر',
                'is_completed' => $hasProduct,
                'url' => $url,
            ];
        }

        // 6. At least one order received
        if (self::isPluginActive('order-management') && class_exists(\App\Plugins\OrderManagement\src\Models\Order::class)) {
            $hasOrder = false;
            try {
                $hasOrder = \App\Plugins\OrderManagement\src\Models\Order::query()->exists();
            } catch (Throwable) {
                $hasOrder = false;
            }

            $url = class_exists(\App\Plugins\OrderManagement\src\Filament\Resources\Orders\OrderResource::class)
                ? \App\Plugins\OrderManagement\src\Filament\Resources\Orders\OrderResource::getUrl('index')
                : null;

            $items[] = [
                'id' => 'order',
                'title' => 'أول طلب',
                'description' => 'استقبال أول طلب تجريبي أو حقيقي من عميل',
                'is_completed' => $hasOrder,
                'url' => $url,
            ];
        }

        return $items;
    }

    protected function getViewData(): array
    {
        $items = self::getApplicableItems();
        $total = count($items);
        $completed = count(array_filter($items, fn (array $i): bool => $i['is_completed']));
        $percentage = $total > 0 ? (int) round(($completed / $total) * 100) : 100;

        return [
            'items' => $items,
            'total' => $total,
            'completed' => $completed,
            'percentage' => $percentage,
        ];
    }

    private static function isPluginActive(string $pluginId): bool
    {
        $tenant = function_exists('tenant') ? tenant() : null;

        if ($tenant && class_exists(PluginManager::class)) {
            try {
                return app(PluginManager::class)->isPluginActive($pluginId, $tenant);
            } catch (Throwable) {
                return false;
            }
        }

        if (class_exists(PluginRegistry::class)) {
            try {
                return app(PluginRegistry::class)->has($pluginId);
            } catch (Throwable) {
                return false;
            }
        }

        return true;
    }
}
