<?php

declare(strict_types=1);

namespace App\Plugins\ProductReviews\src\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;

final class ProductReviewsFilamentPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'product-reviews';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                \App\Plugins\ProductReviews\src\Filament\Resources\ReviewResource::class,
            ])
            ->pages([
                // Register Filament Pages here
            ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
