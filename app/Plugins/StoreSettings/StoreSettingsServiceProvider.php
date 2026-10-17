<?php

declare(strict_types=1);

namespace App\Plugins\StoreSettings;

use App\Plugins\StoreSettings\src\Services\SettingService;
use Illuminate\Support\ServiceProvider;

final class StoreSettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingService::class, fn () => new SettingService());
        $this->app->alias(SettingService::class, 'setting.service');

        $this->app->singleton(\App\Plugins\StoreSettings\src\DTOs\StoreConfig::class, function ($app) {
            $settings = $app->make(SettingService::class);

            return new \App\Plugins\StoreSettings\src\DTOs\StoreConfig(
                siteName: (string) ($settings->get(\App\Enums\SettingKey::SITE_NAME) ?? 'Restaurant'),
                siteNameAr: $settings->get(\App\Enums\SettingKey::SITE_NAME_AR),
                currency: (string) ($settings->get(\App\Enums\SettingKey::CURRENCY) ?? 'EGP'),
                taxRate: (float) ($settings->get(\App\Enums\SettingKey::TAX_RATE) ?? 0.0),
                codFee: (float) ($settings->get(\App\Enums\SettingKey::COD_FEE) ?? 0.0),
                minOrderAmount: (float) ($settings->get(\App\Enums\SettingKey::MIN_ORDER_AMOUNT) ?? 0.0),
                maintenanceMode: (bool) ($settings->get(\App\Enums\SettingKey::MAINTENANCE_MODE) ?? false),
                contactEmail: $settings->get(\App\Enums\SettingKey::CONTACT_EMAIL),
                contactPhone: $settings->get(\App\Enums\SettingKey::CONTACT_PHONE),
                contactAddress: $settings->get(\App\Enums\SettingKey::CONTACT_ADDRESS)
            );
        });
    }

    public function boot(): void
    {
        //
    }
}

