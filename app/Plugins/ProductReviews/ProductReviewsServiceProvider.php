<?php

declare(strict_types=1);

namespace App\Plugins\ProductReviews;

use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\ProductReviews\src\DTOs\ProductReviewsConfig;
use App\Plugins\ProductReviews\src\Models\Review;
use App\Plugins\StoreSettings\src\Services\SettingService;
use Illuminate\Support\ServiceProvider;

final class ProductReviewsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Composition Root: Bind typed Config DTO (Pattern 1)
        $this->app->singleton(ProductReviewsConfig::class, function ($app) {
            $settings = $app->make(SettingService::class);

            return new ProductReviewsConfig(
                enabled: (bool) $settings->get('product-reviews.enabled', true)
            );
        });

        // Bind domain services
    }

    public function boot(): void
    {
        // Dynamic relationship on Product
        if (class_exists(Product::class)) {
            Product::resolveRelationUsing('reviews', function ($product) {
                return $product->hasMany(Review::class);
            });
        }

        if (file_exists(__DIR__.'/routes/tenant.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/tenant.php');
        }
    }
}
