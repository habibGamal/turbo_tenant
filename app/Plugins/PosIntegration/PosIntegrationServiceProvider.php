<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration;

use App\Core\Contracts\SettingsRepository;
use App\Plugins\PosIntegration\src\DTOs\PosIntegrationConfig;
use Illuminate\Support\ServiceProvider;

final class PosIntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                src\Console\Commands\CreateTestOrder::class,
            ]);
        }

        // Composition Root: Bind typed Config DTO (Pattern 1)
        $this->app->singleton(PosIntegrationConfig::class, function ($app) {
            $settings = $app->make(SettingsRepository::class);

            return new PosIntegrationConfig(
                masterRepoUrl: (string) $settings->get(src\Settings\PosSettingKey::PRODUCTS_REPO_LINK, ''),
                masterApiKey: (string) $settings->get(src\Settings\PosSettingKey::PRODUCTS_REPO_API_KEY, ''),
                autoAcceptOrders: (bool) $settings->get('pos-integration.auto_accept_orders', false),
                timeoutSeconds: (int) $settings->get('pos-integration.timeout_seconds', 30),
                enabled: (bool) $settings->get('pos-integration.enabled', true)
            );
        });

        $this->app->singleton(src\Settings\PosSettingsSection::class);
        $this->app->tag([src\Settings\PosSettingsSection::class], 'store-settings.sections');

        // Plugin domain bindings
        $this->app->singleton(
            src\Services\POS\Contracts\POSApiClientInterface::class,
            src\Services\POS\POSApiClient::class
        );
        $this->app->bind(
            src\Services\POS\Contracts\PosMasterClient::class,
            src\Services\POS\HttpPosMasterClient::class
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
            src\Services\POS\POSErrorResponseHandler::class
        );
        $this->app->bind(
            src\Services\OrderPOSService::class,
            src\Services\OrderPOSService::class
        );
        $this->app->singleton(
            src\Services\PosCatalogDiff::class,
            src\Services\PosCatalogDiff::class
        );
        $this->app->singleton(
            src\Services\PosProductImporter::class,
            src\Services\PosProductImporter::class
        );

        // Catalog form extenders
        $this->app->singleton(src\Filament\Extenders\PosProductFormExtender::class);
        $this->app->tag([src\Filament\Extenders\PosProductFormExtender::class], 'catalog.product.form');

        $this->app->singleton(src\Filament\Extenders\PosExtraOptionFormExtender::class);
        $this->app->tag([src\Filament\Extenders\PosExtraOptionFormExtender::class], 'catalog.extra-option.form');

        $this->app->singleton(src\Filament\Contributors\PosPackageMappingFormContributor::class);
        $this->app->tag([src\Filament\Contributors\PosPackageMappingFormContributor::class], 'admin.form.combo-packages.package');

        $this->app->singleton(src\Filament\Contributors\PosBranchApiKeyFormContributor::class);
        $this->app->tag([src\Filament\Contributors\PosBranchApiKeyFormContributor::class], 'admin.form.locations-delivery.branch');

        $this->app->singleton(src\Filament\Contributors\PosPackageHealthColumnContributor::class);
        $this->app->tag([src\Filament\Contributors\PosPackageHealthColumnContributor::class], 'admin.table.combo-packages.packages');
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/lang', 'pos-integration');

        $this->loadViewsFrom(__DIR__.'/resources/views', 'pos-integration');

        $this->registerRelations();
    }

    private function registerRelations(): void
    {
        \App\Plugins\OrderManagement\src\Models\Order::resolveRelationUsing('posDelivery', function ($order) {
            return $order->hasOne(src\Models\OrderPosDelivery::class, 'order_id');
        });

        \App\Plugins\OrderManagement\src\Models\Order::resolveRelationUsing('posDeliveryAttempts', function ($order) {
            return $order->hasManyThrough(src\Models\OrderPosDeliveryAttempt::class, src\Models\OrderPosDelivery::class, 'order_id', 'delivery_id');
        });

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
