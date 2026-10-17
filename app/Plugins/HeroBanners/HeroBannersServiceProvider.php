<?php

declare(strict_types=1);

namespace App\Plugins\HeroBanners;

use App\Plugins\HeroBanners\src\DTOs\HeroBannersConfig;
use App\Plugins\HeroBanners\src\Services\HeroBannerService;
use App\Plugins\StoreSettings\src\Services\SettingService;
use Illuminate\Support\ServiceProvider;

final class HeroBannersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Composition Root: Bind typed Config DTO (Pattern 1)
        $this->app->singleton(HeroBannersConfig::class, function ($app) {
            $settings = $app->make(SettingService::class);

            return new HeroBannersConfig(
                enabled: (bool) $settings->get('hero-banners.enabled', true)
            );
        });

        // Bind domain services
        $this->app->singleton(HeroBannerService::class, function ($app) {
            return new HeroBannerService(
                $app->make(HeroBannersConfig::class)
            );
        });
    }

    public function boot(): void
    {
        if (file_exists(__DIR__.'/routes/tenant.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/tenant.php');
        }
    }
}
