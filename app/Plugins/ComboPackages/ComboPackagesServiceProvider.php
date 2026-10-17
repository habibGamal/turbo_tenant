<?php

declare(strict_types=1);

namespace App\Plugins\ComboPackages;

use App\Plugins\ComboPackages\src\DTOs\ComboPackagesConfig;
use App\Plugins\StoreSettings\src\Services\SettingService;
use Illuminate\Support\ServiceProvider;

final class ComboPackagesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Composition Root: Bind typed Config DTO (Pattern 1)
        $this->app->singleton(ComboPackagesConfig::class, function ($app) {
            $settings = $app->make(SettingService::class);

            return new ComboPackagesConfig(
                enabled: (bool) $settings->get('combo-packages.enabled', true)
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
