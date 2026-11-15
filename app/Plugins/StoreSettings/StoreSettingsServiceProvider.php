<?php

declare(strict_types=1);

namespace App\Plugins\StoreSettings;

use App\Core\Contracts\SettingsRepository;
use App\Plugins\StoreSettings\src\Services\SettingService;
use App\Plugins\StoreSettings\src\Settings\StoreAppearanceSettingsSection;
use App\Plugins\StoreSettings\src\Settings\StoreCardsSettingsSection;
use App\Plugins\StoreSettings\src\Settings\StoreIdentitySettingsSection;
use App\Plugins\StoreSettings\src\Settings\StoreSystemSettingsSection;
use App\Plugins\StoreSettings\src\Theme\ThemeCssBuilder;
use Illuminate\Support\ServiceProvider;

final class StoreSettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingService::class, fn () => new SettingService());
        $this->app->bind(SettingsRepository::class, fn ($app) => $app->make(SettingService::class));
        $this->app->singleton(ThemeCssBuilder::class);

        $this->app->singleton(src\Bootstrap\StoreSettingsBootstrapContributor::class);
        $this->app->tag([src\Bootstrap\StoreSettingsBootstrapContributor::class], 'tenant.bootstrap.contributors');

        $this->app->singleton(src\Filament\Checklist\LogoSetupChecklistItem::class);
        $this->app->tag([src\Filament\Checklist\LogoSetupChecklistItem::class], 'admin.setup-checklist');

        $this->app->singleton(StoreIdentitySettingsSection::class);
        $this->app->singleton(StoreAppearanceSettingsSection::class);
        $this->app->singleton(StoreSystemSettingsSection::class);
        $this->app->singleton(StoreCardsSettingsSection::class);
        $this->app->tag([
            StoreIdentitySettingsSection::class,
            StoreAppearanceSettingsSection::class,
            StoreSystemSettingsSection::class,
            StoreCardsSettingsSection::class,
        ], 'store-settings.sections');
    }

    public function boot(): void
    {
        //
    }
}
