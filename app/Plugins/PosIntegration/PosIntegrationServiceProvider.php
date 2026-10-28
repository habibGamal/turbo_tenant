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
            src\Services\POS\Contracts\POSApiClientInterface::class,
            src\Services\POS\POSApiClient::class
        );
        $this->app->singleton(
            src\Services\POS\Contracts\POSProductResolverInterface::class,
            src\Services\POS\POSProductResolver::class
        );
        $this->app->singleton(
            src\Services\POS\POSCustomerDataFormatter::class,
            src\Services\POS\POSCustomerDataFormatter::class
        );
        $this->app->singleton(
            src\Services\POS\Contracts\OrderPOSPayloadBuilderInterface::class,
            src\Services\POS\OrderPOSPayloadBuilder::class
        );
        $this->app->bind(
            src\Services\POS\Contracts\POSErrorResponseHandlerInterface::class,
            function ($app) {
                return new src\Services\POS\POSErrorResponseHandler(
                    $app->make(src\Services\ProductPOSImporterService::class)
                );
            }
        );
        $this->app->bind(
            src\Services\OrderPOSService::class,
            src\Services\OrderPOSService::class
        );
        $this->app->bind(
            src\Services\ProductPOSImporterService::class,
            src\Services\ProductPOSImporterService::class
        );
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/resources/views', 'pos-integration');

        $this->registerRelations();
    }

    private function registerRelations(): void
    {
        \App\Plugins\CatalogProducts\src\Models\Product::resolveRelationUsing('posMappings', function ($product) {
            return $product->hasMany(src\Models\ProductPosMapping::class);
        });

        \App\Plugins\CatalogProducts\src\Models\ProductVariant::resolveRelationUsing('posMappings', function ($variant) {
            return $variant->hasMany(src\Models\ProductPosMapping::class, 'variant_id');
        });

        \App\Plugins\CatalogProducts\src\Models\ExtraOptionItem::resolveRelationUsing('posMappings', function ($extraOptionItem) {
            return $extraOptionItem->hasMany(src\Models\ProductPosMapping::class, 'extra_option_item_id');
        });

        \App\Plugins\LocationsDelivery\src\Models\Branch::resolveRelationUsing('posMappings', function ($branch) {
            return $branch->hasMany(src\Models\ProductPosMapping::class);
        });
    }
}
