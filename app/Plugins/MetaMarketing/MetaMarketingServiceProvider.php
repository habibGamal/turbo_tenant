<?php

declare(strict_types=1);

namespace App\Plugins\MetaMarketing;

use App\Core\Contracts\SettingsRepository;
use App\Enums\SettingKey;
use App\Plugins\MetaMarketing\src\Bootstrap\MetaMarketingBootstrapContributor;
use App\Plugins\MetaMarketing\src\Capi\MetaConversionsClient;
use App\Plugins\MetaMarketing\src\Capi\UserDataBuilder;
use App\Plugins\MetaMarketing\src\DTOs\MetaMarketingConfig;
use App\Plugins\MetaMarketing\src\Http\Responders\PurchaseTrackingResponseContributor;
use App\Plugins\MetaMarketing\src\Settings\MetaSettingKey;
use App\Plugins\MetaMarketing\src\Settings\MetaSettingsSection;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\ServiceProvider;

final class MetaMarketingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MetaMarketingConfig::class, function ($app) {
            $settings = $app->make(SettingsRepository::class);
            $siteName = (string) $settings->get(SettingKey::SITE_NAME, config('app.name', ''));

            return new MetaMarketingConfig(
                pixelId: $this->nullableString($settings->get(MetaSettingKey::PIXEL_ID)),
                debugMode: (bool) $settings->get(MetaSettingKey::DEBUG_MODE, false),
                capiEnabled: (bool) $settings->get(MetaSettingKey::CAPI_ENABLED, false),
                accessToken: $this->decrypt($settings->get(MetaSettingKey::CAPI_ACCESS_TOKEN)),
                testEventCode: $this->nullableString($settings->get(MetaSettingKey::CAPI_TEST_EVENT_CODE)),
                graphVersion: (string) config('services.meta.graph_version'),
                catalogEnabled: (bool) $settings->get(MetaSettingKey::CATALOG_ENABLED, false),
                catalogFeedToken: $this->nullableString($settings->get(MetaSettingKey::CATALOG_FEED_TOKEN)),
                catalogBrand: $this->nullableString($settings->get(MetaSettingKey::CATALOG_BRAND)) ?? $siteName,
                currency: (string) $settings->get(SettingKey::CURRENCY, 'EGP'),
            );
        });

        $this->app->singleton(MetaSettingsSection::class);
        $this->app->tag([MetaSettingsSection::class], 'store-settings.sections');

        $this->app->singleton(MetaMarketingBootstrapContributor::class);
        $this->app->tag([MetaMarketingBootstrapContributor::class], 'tenant.bootstrap.contributors');

        $this->app->singleton(PurchaseTrackingResponseContributor::class);
        $this->app->tag([PurchaseTrackingResponseContributor::class], 'order.placement.response');

        $this->app->singleton(MetaConversionsClient::class);
        $this->app->singleton(UserDataBuilder::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes/tenant.php');
    }

    private function nullableString(mixed $value): ?string
    {
        $value = is_scalar($value) ? mb_trim((string) $value) : '';

        return $value === '' ? null : $value;
    }

    private function decrypt(mixed $value): ?string
    {
        $value = $this->nullableString($value);
        if ($value === null) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return null;
        }
    }
}
