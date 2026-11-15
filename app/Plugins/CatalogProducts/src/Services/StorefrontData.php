<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Services;

use App\Plugins\CatalogProducts\src\Http\Resources\CategoryResource;
use App\Plugins\CatalogProducts\src\Http\Resources\SectionResource;
use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\Section;

final class StorefrontData
{
    public const int PRODUCTS_PER_SECTION = 12;

    public function sections(): array
    {
        $sections = Section::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with(['products' => function ($query) {
                $query->where('is_active', true)
                    ->with(['category:id,name,name_ar'])
                    ->withCount('variants')
                    ->limit(self::PRODUCTS_PER_SECTION);
            }])
            ->get();

        return SectionResource::collection($sections)->resolve();
    }

    public function categories(): array
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'name_ar', 'description', 'image', 'sort_order']);

        return CategoryResource::collection($categories)->resolve();
    }
}
