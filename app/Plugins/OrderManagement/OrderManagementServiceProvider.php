<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement;

use App\Core\Contracts\SettingsRepository;
use App\Plugins\OrderManagement\src\Contracts\CustomerOrderHistory;
use App\Plugins\OrderManagement\src\Contracts\OrderPaymentRecorder;
use App\Plugins\OrderManagement\src\Contracts\OrderPipelineRegistrar;
use App\Plugins\OrderManagement\src\DTOs\OrderManagementConfig;
use App\Plugins\OrderManagement\src\Pipelines\OrderPipelineRegistry;
use App\Plugins\OrderManagement\src\Pipelines\OrderValidationPipeline;
use App\Plugins\OrderManagement\src\Pipelines\PricingCalculationPipeline;
use App\Plugins\OrderManagement\src\Services\Contracts\OrderPaymentDispatcherInterface;
use App\Plugins\OrderManagement\src\Services\Contracts\OrderPricingCalculatorInterface;
use App\Plugins\OrderManagement\src\Services\Contracts\OrderRepositoryInterface;
use App\Plugins\OrderManagement\src\Services\Contracts\OrderValidatorInterface;
use App\Plugins\OrderManagement\src\Services\Contracts\PlaceOrderServiceInterface;
use App\Plugins\OrderManagement\src\Services\EloquentCustomerOrderHistory;
use App\Plugins\OrderManagement\src\Services\EloquentOrderPaymentRecorder;
use App\Plugins\OrderManagement\src\Services\Payments\OrderPaymentDispatcher;
use App\Plugins\OrderManagement\src\Services\PlaceOrderService;
use App\Plugins\OrderManagement\src\Services\Pricing\OrderPricingCalculator;
use App\Plugins\OrderManagement\src\Services\Repositories\OrderRepository;
use App\Plugins\OrderManagement\src\Services\Validators\OrderValidator;
use Illuminate\Support\ServiceProvider;

final class OrderManagementServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 1. Composition Root: Bind typed Config DTO
        $this->app->singleton(OrderManagementConfig::class, function ($app) {
            return new OrderManagementConfig(
                $app->make(SettingsRepository::class)
            );
        });

        // 2. Pipeline Bus
        $this->app->singleton(OrderPipelineRegistry::class);
        $this->app->bind(OrderPipelineRegistrar::class, OrderPipelineRegistry::class);
        $this->app->singleton(OrderValidationPipeline::class);
        $this->app->singleton(PricingCalculationPipeline::class);

        // 3. Domain Services
        $this->app->bind(CustomerOrderHistory::class, EloquentCustomerOrderHistory::class);
        $this->app->bind(OrderPaymentRecorder::class, EloquentOrderPaymentRecorder::class);
        $this->app->bind(OrderRepositoryInterface::class, OrderRepository::class);
        $this->app->bind(OrderPaymentDispatcherInterface::class, OrderPaymentDispatcher::class);
        $this->app->bind(OrderPricingCalculatorInterface::class, OrderPricingCalculator::class);
        $this->app->bind(OrderValidatorInterface::class, OrderValidator::class);
        $this->app->bind(PlaceOrderServiceInterface::class, PlaceOrderService::class);
        $this->app->bind(PlaceOrderService::class);

        $this->app->singleton(src\Filament\Checklist\FirstOrderSetupChecklistItem::class);
        $this->app->tag([src\Filament\Checklist\FirstOrderSetupChecklistItem::class], 'admin.setup-checklist');

        $this->app->singleton(src\Filament\Tables\OrdersCountUsersTableColumnContributor::class);
        $this->app->tag([src\Filament\Tables\OrdersCountUsersTableColumnContributor::class], 'admin.users.table.columns');

        $this->app->singleton(src\Settings\OrderSettingsSection::class);
        $this->app->tag([src\Settings\OrderSettingsSection::class], 'store-settings.sections');
    }

    public function boot(): void
    {
        $this->registerRelations();

        if (file_exists(__DIR__.'/routes/tenant.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/tenant.php');
        }
    }

    private function registerRelations(): void
    {
        \App\Models\User::resolveRelationUsing('orders', function (\App\Models\User $user) {
            return $user->hasMany(src\Models\Order::class);
        });

        \App\Plugins\LocationsDelivery\src\Models\Address::resolveRelationUsing('orders', function (\App\Plugins\LocationsDelivery\src\Models\Address $address) {
            return $address->hasMany(src\Models\Order::class);
        });

        \App\Plugins\LocationsDelivery\src\Models\Branch::resolveRelationUsing('orders', function (\App\Plugins\LocationsDelivery\src\Models\Branch $branch) {
            return $branch->hasMany(src\Models\Order::class);
        });

        \App\Plugins\CatalogProducts\src\Models\Product::resolveRelationUsing('orderItems', function (\App\Plugins\CatalogProducts\src\Models\Product $product) {
            return $product->hasMany(src\Models\OrderItem::class);
        });

        \App\Plugins\CatalogProducts\src\Models\ProductVariant::resolveRelationUsing('orderItems', function (\App\Plugins\CatalogProducts\src\Models\ProductVariant $variant) {
            return $variant->hasMany(src\Models\OrderItem::class, 'variant_id');
        });
    }
}
