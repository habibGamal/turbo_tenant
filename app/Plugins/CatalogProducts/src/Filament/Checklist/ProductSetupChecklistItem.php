<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Filament\Checklist;

use App\Core\Plugins\Contracts\SetupChecklistItem;
use App\Plugins\CatalogProducts\src\Filament\Resources\Products\ProductResource;
use App\Plugins\CatalogProducts\src\Models\Product;
use Throwable;

final class ProductSetupChecklistItem implements SetupChecklistItem
{
    public function pluginId(): string
    {
        return 'catalog-products';
    }

    public function key(): string
    {
        return 'product';
    }

    public function label(): string
    {
        return 'إضافة منتج';
    }

    public function description(): string
    {
        return 'إضافة منتج واحد على الأقل في قائمة منتجات المتجر';
    }

    public function isComplete(): bool
    {
        try {
            return Product::query()->exists();
        } catch (Throwable) {
            return false;
        }
    }

    public function url(): ?string
    {
        try {
            return ProductResource::getUrl('index');
        } catch (Throwable) {
            return null;
        }
    }

    public function sortOrder(): int
    {
        return 50;
    }
}
