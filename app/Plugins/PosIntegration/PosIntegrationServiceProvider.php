<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration;

use App\Plugins\PosIntegration\src\DTOs\PosIntegrationConfig;
use App\Plugins\StoreSettings\src\Services\SettingService;
use Illuminate\Support\ServiceProvider;

final class PosIntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Composition Root: Bind typed Config DTO (Pattern 1)
        $this->app->singleton(PosIntegrationConfig::class, function ($app) {
            $settings = $app->make(SettingService::class);

            return new PosIntegrationConfig(
                masterRepoUrl: (string) $settings->get(\App\Enums\SettingKey::from('products_repo_link'), ''),
                autoAcceptOrders: (bool) $settings->get('pos-integration.auto_accept_orders', false),
                timeoutSeconds: (int) $settings->get('pos-integration.timeout_seconds', 30),
                enabled: (bool) $settings->get('pos-integration.enabled', true)
            );
        });

        // Plugin domain bindings
        $this->app->singleton(
            \App\Plugins\PosIntegration\src\Services\POS\Contracts\POSApiClientInterface::class,
            \App\Plugins\PosIntegration\src\Services\POS\POSApiClient::class
        );
        $this->app->singleton(
            \App\Plugins\PosIntegration\src\Services\POS\Contracts\POSProductResolverInterface::class,
            \App\Plugins\PosIntegration\src\Services\POS\POSProductResolver::class
        );
        $this->app->singleton(
            \App\Plugins\PosIntegration\src\Services\POS\POSCustomerDataFormatter::class,
            \App\Plugins\PosIntegration\src\Services\POS\POSCustomerDataFormatter::class
        );
        $this->app->singleton(
            \App\Plugins\PosIntegration\src\Services\POS\Contracts\OrderPOSPayloadBuilderInterface::class,
            \App\Plugins\PosIntegration\src\Services\POS\OrderPOSPayloadBuilder::class
        );
        $this->app->bind(
            \App\Plugins\PosIntegration\src\Services\POS\Contracts\POSErrorResponseHandlerInterface::class,
            function ($app) {
                return new \App\Plugins\PosIntegration\src\Services\POS\POSErrorResponseHandler(
                    $app->make(\App\Plugins\PosIntegration\src\Services\ProductPOSImporterService::class)
                );
            }
        );
        $this->app->bind(
            \App\Plugins\PosIntegration\src\Services\OrderPOSService::class,
            \App\Plugins\PosIntegration\src\Services\OrderPOSService::class
        );
        $this->app->bind(
            \App\Plugins\PosIntegration\src\Services\ProductPOSImporterService::class,
            \App\Plugins\PosIntegration\src\Services\ProductPOSImporterService::class
        );
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/resources/views', 'pos-integration');

        // Dynamic relationship on Product
        \App\Plugins\CatalogProducts\src\Models\Product::resolveRelationUsing('posMappings', function ($product) {
            return $product->hasMany(\App\Plugins\PosIntegration\src\Models\ProductPosMapping::class);
        });
    }
}
