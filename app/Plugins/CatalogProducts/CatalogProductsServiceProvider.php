<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts;

use Illuminate\Support\ServiceProvider;

final class CatalogProductsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind plugin services
    }

    public function boot(): void
    {
        if (file_exists(__DIR__.'/routes/tenant.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/tenant.php');
        }
    }
}
