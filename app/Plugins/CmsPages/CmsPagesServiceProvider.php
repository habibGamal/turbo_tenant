<?php

declare(strict_types=1);

namespace App\Plugins\CmsPages;

use App\Plugins\CmsPages\src\DTOs\CmsPagesConfig;
use App\Plugins\StoreSettings\src\Services\SettingService;
use Illuminate\Support\ServiceProvider;

final class CmsPagesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Composition Root: Bind typed Config DTO (Pattern 1)
        $this->app->singleton(CmsPagesConfig::class, function ($app) {
            $settings = $app->make(SettingService::class);

            return new CmsPagesConfig(
                enabled: (bool) $settings->get('cms-pages.enabled', true)
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
