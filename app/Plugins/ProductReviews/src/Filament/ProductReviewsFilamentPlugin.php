<?php

declare(strict_types=1);

namespace App\Plugins\ProductReviews\src\Filament;

use App\Plugins\ProductReviews\src\Filament\Widgets\PendingReviewsStat;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class ProductReviewsFilamentPlugin implements Plugin
{
    public static function make(): static
    {
        return app(self::class);
    }

    public function getId(): string
    {
        return 'product-reviews';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                Resources\ReviewResource::class,
            ])
            ->pages([
                // Register Filament Pages here
            ])
            ->widgets([
                PendingReviewsStat::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
