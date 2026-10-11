<?php

declare(strict_types=1);

namespace App\Plugins\StoreSettings;

use App\Services\SettingService;
use Illuminate\Support\ServiceProvider;

final class StoreSettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingService::class, fn () => new SettingService());
    }

    public function boot(): void
    {
        //
    }
}

