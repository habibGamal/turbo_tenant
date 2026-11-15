<?php

declare(strict_types=1);

namespace App\Plugins\ComboPackages;

use Illuminate\Support\ServiceProvider;

final class ComboPackagesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(src\Bootstrap\StorefrontBootstrapContributor::class);
        $this->app->tag([src\Bootstrap\StorefrontBootstrapContributor::class], 'tenant.bootstrap.contributors');

        // Bind domain services
        $this->app->singleton(src\Catalog\ProductComboUpsellContributor::class);
        $this->app->tag([src\Catalog\ProductComboUpsellContributor::class], 'catalog.product.details');
        $this->app->singleton(src\Services\SellablePackageFilter::class);
        $this->app->singleton(src\Services\PackageSelectionValidator::class);
        $this->app->singleton(src\Services\PackageLinePricer::class);
        $this->app->singleton(src\Cart\PackageCartLineHandler::class);
        $this->app->tag([src\Cart\PackageCartLineHandler::class], 'cart-engine.line-types');
        $this->app->singleton(src\Guards\PackageProductDeletionGuard::class);
        $this->app->tag([src\Guards\PackageProductDeletionGuard::class], 'catalog-products.product-deletion-guards');
        $this->app->singleton(src\Catalog\PackageLineReferenceCatalog::class);
        $this->app->tag([src\Catalog\PackageLineReferenceCatalog::class], 'catalog.line-references');
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
        \App\Plugins\CatalogProducts\src\Models\Product::resolveRelationUsing('packageItems', function (\App\Plugins\CatalogProducts\src\Models\Product $product) {
            return $product->hasMany(src\Models\PackageItem::class, 'product_id');
        });
    }
}
