<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts;

use Illuminate\Support\ServiceProvider;

final class CatalogProductsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(src\Bootstrap\StorefrontBootstrapContributor::class);
        $this->app->tag([src\Bootstrap\StorefrontBootstrapContributor::class], 'tenant.bootstrap.contributors');

        $this->app->bind(
            src\Contracts\ProductImporter::class,
            src\Services\ProductImporterService::class
        );
        $this->app->bind(
            src\Contracts\ProductPriceUpdater::class,
            src\Services\ProductPriceUpdaterService::class
        );

        $this->app->singleton(src\Filament\Checklist\CategorySetupChecklistItem::class);
        $this->app->singleton(src\Filament\Checklist\ProductSetupChecklistItem::class);
        $this->app->tag([
            src\Filament\Checklist\CategorySetupChecklistItem::class,
            src\Filament\Checklist\ProductSetupChecklistItem::class,
        ], 'admin.setup-checklist');
    }

    public function boot(): void
    {
        if (file_exists(__DIR__.'/routes/tenant.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/tenant.php');
        }
    }
}
