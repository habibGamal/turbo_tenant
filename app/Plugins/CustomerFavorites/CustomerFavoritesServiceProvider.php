<?php

declare(strict_types=1);

namespace App\Plugins\CustomerFavorites;

use App\Plugins\CustomerFavorites\src\DTOs\CustomerFavoritesConfig;
use App\Plugins\StoreSettings\src\Services\SettingService;
use Illuminate\Support\ServiceProvider;

final class CustomerFavoritesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Composition Root: Bind typed Config DTO (Pattern 1)
        $this->app->singleton(CustomerFavoritesConfig::class, function ($app) {
            $settings = $app->make(SettingService::class);

            return new CustomerFavoritesConfig(
                enabled: (bool) $settings->get('customer-favorites.enabled', true)
            );
        });

        // Bind domain services
    }

    public function boot(): void
    {
        if (file_exists(__DIR__.'/routes/tenant.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/tenant.php');
        }
    }
}
