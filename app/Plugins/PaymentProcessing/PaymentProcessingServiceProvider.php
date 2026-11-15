<?php

declare(strict_types=1);

namespace App\Plugins\PaymentProcessing;

use App\Core\Contracts\SettingsRepository;
use App\Plugins\OrderManagement\src\Contracts\PaymentInitiator;
use App\Plugins\PaymentProcessing\src\Contracts\PaymentCallbackServiceInterface;
use App\Plugins\PaymentProcessing\src\Contracts\PaymentGatewayInterface;
use App\Plugins\PaymentProcessing\src\Contracts\PaymentWebhookServiceInterface;
use App\Plugins\PaymentProcessing\src\DTOs\PaymentProcessingConfig;
use App\Plugins\PaymentProcessing\src\Services\GatewayRegistry;
use App\Plugins\PaymentProcessing\src\Services\KashierService;
use App\Plugins\PaymentProcessing\src\Services\PaymentCallbackService;
use App\Plugins\PaymentProcessing\src\Services\PaymentWebhookService;
use App\Plugins\PaymentProcessing\src\Services\PaymobService;
use App\Plugins\PaymentProcessing\src\Settings\PaymentSettingKey;
use Illuminate\Support\ServiceProvider;

final class PaymentProcessingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 1. Composition Root: Bind typed Config DTO
        $this->app->singleton(PaymentProcessingConfig::class, function ($app) {
            $settings = $app->make(SettingsRepository::class);
            $integrationIdsString = (string) $settings->get(PaymentSettingKey::PAYMOB_INTEGRATION_IDS, '');
            $integrationIds = array_map(
                fn ($id) => (int) $id,
                array_filter(explode(',', $integrationIdsString))
            );

            return new PaymentProcessingConfig(
                activeGateway: (string) $settings->get(PaymentSettingKey::ACTIVE_PAYMENT_GATEWAY, 'paymob'),
                paymobBaseUrl: (string) $settings->get(PaymentSettingKey::PAYMOB_BASE_URL, 'https://accept.paymob.com'),
                paymobSecretKey: (string) $settings->get(PaymentSettingKey::PAYMOB_SECRET_KEY, ''),
                paymobPublicKey: (string) $settings->get(PaymentSettingKey::PAYMOB_PUBLIC_KEY, ''),
                paymobIntegrationIds: $integrationIds,
                paymobHmacSecret: (string) $settings->get(PaymentSettingKey::PAYMOB_HMAC_SECRET, ''),
                paymobCurrency: (string) $settings->get(PaymentSettingKey::PAYMOB_CURRENCY, 'EGP'),
                kashierMerchantId: (string) $settings->get(PaymentSettingKey::KASHIER_MERCHANT_ID, ''),
                kashierApiKey: (string) $settings->get(PaymentSettingKey::KASHIER_API_KEY, ''),
                kashierSecretKey: (string) $settings->get(PaymentSettingKey::KASHIER_SECRET_KEY, ''),
                kashierMode: (string) $settings->get(PaymentSettingKey::KASHIER_MODE, 'test'),
                kashierCurrency: (string) $settings->get(PaymentSettingKey::KASHIER_CURRENCY, 'EGP'),
                kashierAllowedMethods: (string) $settings->get(PaymentSettingKey::KASHIER_ALLOWED_METHODS, 'card')
            );
        });

        // 2. Gateway services
        $this->app->singleton(PaymobService::class);
        $this->app->singleton(KashierService::class);
        $this->app->singleton(GatewayRegistry::class, function ($app) {
            $registry = new GatewayRegistry(
                $app,
                $app->make(PaymentProcessingConfig::class)
            );

            $registry->register(PaymobService::ID, 'Paymob', PaymobService::class);
            $registry->register(KashierService::ID, 'Kashier', KashierService::class);

            return $registry;
        });

        // Bind active gateway
        $this->app->bind(PaymentInitiator::class, function ($app) {
            return $app->make(GatewayRegistry::class)->active();
        });

        $this->app->bind(PaymentGatewayInterface::class, function ($app) {
            return $app->make(GatewayRegistry::class)->active();
        });

        // 3. Callback and Webhook services
        $this->app->bind(PaymentCallbackService::class);
        $this->app->bind(PaymentWebhookService::class);

        $this->app->bind(PaymentCallbackServiceInterface::class, PaymentCallbackService::class);
        $this->app->bind(PaymentWebhookServiceInterface::class, PaymentWebhookService::class);

        $this->app->tag([src\Http\Rules\BillingDataRulesContributor::class], 'order.placement.rules');

        $this->app->singleton(src\Settings\PaymentSettingsSection::class);
        $this->app->tag([src\Settings\PaymentSettingsSection::class], 'store-settings.sections');

        $this->app->singleton(src\Bootstrap\PaymentProcessingBootstrapContributor::class);
        $this->app->tag([src\Bootstrap\PaymentProcessingBootstrapContributor::class], 'tenant.bootstrap.contributors');
    }

    public function boot(): void
    {
        if (file_exists(__DIR__.'/routes/tenant.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/tenant.php');
        }
    }
}
