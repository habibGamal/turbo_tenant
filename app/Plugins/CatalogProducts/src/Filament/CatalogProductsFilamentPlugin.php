<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;

final class CatalogProductsFilamentPlugin implements Plugin
{
    public static function make(): static
    {
        return app(self::class);
    }

    public function getId(): string
    {
        return 'catalog-products';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                Resources\Categories\CategoryResource::class,
                Resources\Products\ProductResource::class,
                Resources\WeightOptions\WeightOptionResource::class,
                Resources\ExtraOptions\ExtraOptionResource::class,
                Resources\Sections\SectionResource::class,
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
