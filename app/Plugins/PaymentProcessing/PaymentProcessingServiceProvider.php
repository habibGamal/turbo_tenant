<?php

declare(strict_types=1);

namespace App\Plugins\PaymentProcessing;

use App\Plugins\OrderManagement\src\Pipelines\OrderPipelineRegistry;
use App\Plugins\PaymentProcessing\src\Contracts\PaymentCallbackServiceInterface;
use App\Plugins\PaymentProcessing\src\Contracts\PaymentGatewayInterface;
use App\Plugins\PaymentProcessing\src\Contracts\PaymentWebhookServiceInterface;
use App\Plugins\PaymentProcessing\src\DTOs\PaymentProcessingConfig;
use App\Plugins\PaymentProcessing\src\Pipelines\Pipes\ApplyCodFeePipe;
use App\Plugins\PaymentProcessing\src\Services\KashierService;
use App\Plugins\PaymentProcessing\src\Services\PaymentCallbackService;
use App\Plugins\PaymentProcessing\src\Services\PaymentGatewayFactory;
use App\Plugins\PaymentProcessing\src\Services\PaymentWebhookService;
use App\Plugins\PaymentProcessing\src\Services\PaymobService;
use App\Plugins\StoreSettings\src\Services\SettingService;
use Illuminate\Support\ServiceProvider;

final class PaymentProcessingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 1. Composition Root: Bind typed Config DTO
        $this->app->singleton(PaymentProcessingConfig::class, function ($app) {
            $settings = $app->make(SettingService::class);
            $integrationIdsString = (string) $settings->get(\App\Enums\SettingKey::PAYMOB_INTEGRATION_IDS, '');
            $integrationIds = array_map(
                fn ($id) => (int) $id,
                array_filter(explode(',', $integrationIdsString))
            );

            return new PaymentProcessingConfig(
                activeGateway: (string) $settings->get(\App\Enums\SettingKey::ACTIVE_PAYMENT_GATEWAY, 'paymob'),
                paymobBaseUrl: (string) $settings->get(\App\Enums\SettingKey::PAYMOB_BASE_URL, 'https://accept.paymob.com'),
                paymobSecretKey: (string) $settings->get(\App\Enums\SettingKey::PAYMOB_SECRET_KEY, ''),
                paymobPublicKey: (string) $settings->get(\App\Enums\SettingKey::PAYMOB_PUBLIC_KEY, ''),
                paymobIntegrationIds: $integrationIds,
                paymobHmacSecret: (string) $settings->get(\App\Enums\SettingKey::PAYMOB_HMAC_SECRET, ''),
                kashierMerchantId: (string) $settings->get(\App\Enums\SettingKey::KASHIER_MERCHANT_ID, ''),
                kashierApiKey: (string) $settings->get(\App\Enums\SettingKey::KASHIER_API_KEY, ''),
                kashierSecretKey: (string) $settings->get(\App\Enums\SettingKey::KASHIER_SECRET_KEY, ''),
                kashierMode: (string) $settings->get(\App\Enums\SettingKey::KASHIER_MODE, 'test'),
                kashierCurrency: (string) $settings->get(\App\Enums\SettingKey::KASHIER_CURRENCY, 'EGP'),
                kashierAllowedMethods: (string) $settings->get(\App\Enums\SettingKey::KASHIER_ALLOWED_METHODS, 'card')
            );
        });

        // 2. Gateway services
        $this->app->singleton(PaymobService::class);
        $this->app->singleton(KashierService::class);
        $this->app->singleton(PaymentGatewayFactory::class);

        // Bind active gateway
        $this->app->bind(PaymentGatewayInterface::class, function ($app) {
            return $app->make(PaymentGatewayFactory::class)->getActiveGateway();
        });

        // 3. Callback and Webhook services
        $this->app->bind(PaymentCallbackService::class);
        $this->app->bind(PaymentWebhookService::class);

        $this->app->bind(PaymentCallbackServiceInterface::class, PaymentCallbackService::class);
        $this->app->bind(PaymentWebhookServiceInterface::class, PaymentWebhookService::class);
    }

    public function boot(): void
    {
        if (file_exists(__DIR__.'/routes/tenant.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/tenant.php');
        }
    }
}
