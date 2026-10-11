<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;

final class CatalogProductsFilamentPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'catalog-products';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                \App\Plugins\CatalogProducts\src\Filament\Resources\Categories\CategoryResource::class,
                \App\Plugins\CatalogProducts\src\Filament\Resources\Products\ProductResource::class,
                \App\Plugins\CatalogProducts\src\Filament\Resources\WeightOptions\WeightOptionResource::class,
                \App\Plugins\CatalogProducts\src\Filament\Resources\ExtraOptions\ExtraOptionResource::class,
                \App\Plugins\CatalogProducts\src\Filament\Resources\Sections\SectionResource::class,
            ])
            ->pages([
                //
            ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
