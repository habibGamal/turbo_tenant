<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Filament\Checklist;

use App\Core\Plugins\Contracts\SetupChecklistItem;
use App\Plugins\CatalogProducts\src\Filament\Resources\Categories\CategoryResource;
use App\Plugins\CatalogProducts\src\Models\Category;
use Throwable;

final class CategorySetupChecklistItem implements SetupChecklistItem
{
    public function pluginId(): string
    {
        return 'catalog-products';
    }

    public function key(): string
    {
        return 'category';
    }

    public function label(): string
    {
        return 'تصنيف منتجات';
    }

    public function description(): string
    {
        return 'إضافة تصنيف منتجات واحد على الأقل لتنظيم قائمة المأكولات أو المنتجات';
    }

    public function isComplete(): bool
    {
        try {
            return Category::query()->exists();
        } catch (Throwable) {
            return false;
        }
    }

    public function url(): ?string
    {
        try {
            return CategoryResource::getUrl('index');
        } catch (Throwable) {
            return null;
        }
    }

    public function sortOrder(): int
    {
        return 40;
    }
}
