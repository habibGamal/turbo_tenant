<?php

declare(strict_types=1);

namespace App\Plugins\ProductReviews;

use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\ProductReviews\src\Contributors\ProductRatingContributor;
use App\Plugins\ProductReviews\src\Models\Review;
use Illuminate\Support\ServiceProvider;

final class ProductReviewsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProductRatingContributor::class);
        $this->app->tag([ProductRatingContributor::class], 'catalog.product.details');
    }

    public function boot(): void
    {
        $this->registerRelations();

        if (file_exists(__DIR__.'/routes/tenant.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/tenant.php');
        }
    }

    private function registerRelations(): void
    {
        Product::resolveRelationUsing('reviews', function (Product $product) {
            return $product->hasMany(Review::class);
        });
    }
}
