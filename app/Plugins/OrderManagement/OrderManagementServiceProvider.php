<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement;

use App\Plugins\OrderManagement\src\DTOs\OrderManagementConfig;
use App\Plugins\OrderManagement\src\Pipelines\OrderPipelineRegistry;
use App\Plugins\OrderManagement\src\Pipelines\OrderValidationPipeline;
use App\Plugins\OrderManagement\src\Pipelines\PricingCalculationPipeline;
use App\Plugins\OrderManagement\src\Services\Contracts\OrderPaymentDispatcherInterface;
use App\Plugins\OrderManagement\src\Services\Contracts\OrderPricingCalculatorInterface;
use App\Plugins\OrderManagement\src\Services\Contracts\OrderRepositoryInterface;
use App\Plugins\OrderManagement\src\Services\Contracts\OrderValidatorInterface;
use App\Plugins\OrderManagement\src\Services\Contracts\PlaceOrderServiceInterface;
use App\Plugins\OrderManagement\src\Services\Payments\OrderPaymentDispatcher;
use App\Plugins\OrderManagement\src\Services\Pricing\OrderPricingCalculator;
use App\Plugins\OrderManagement\src\Services\Repositories\OrderRepository;
use App\Plugins\OrderManagement\src\Services\Validators\OrderValidator;
use App\Plugins\OrderManagement\src\Services\PlaceOrderService;
use App\Plugins\StoreSettings\src\Services\SettingService;
use Illuminate\Support\ServiceProvider;

final class OrderManagementServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 1. Composition Root: Bind typed Config DTO
        $this->app->singleton(OrderManagementConfig::class, function ($app) {
            return new OrderManagementConfig(
                $app->make(SettingService::class)
            );
        });

        // 2. Pipeline Bus
        $this->app->singleton(OrderPipelineRegistry::class);
        $this->app->singleton(OrderValidationPipeline::class);
        $this->app->singleton(PricingCalculationPipeline::class);

        // 3. Domain Services
        $this->app->bind(OrderRepositoryInterface::class, OrderRepository::class);
        $this->app->bind(OrderPaymentDispatcherInterface::class, OrderPaymentDispatcher::class);
        $this->app->bind(OrderPricingCalculatorInterface::class, OrderPricingCalculator::class);
        $this->app->bind(OrderValidatorInterface::class, OrderValidator::class);
        $this->app->bind(PlaceOrderServiceInterface::class, PlaceOrderService::class);
        $this->app->singleton(PlaceOrderService::class);

    }

    public function boot(): void
    {
        \App\Models\User::resolveRelationUsing('orders', function (\App\Models\User $user) {
            return $user->hasMany(\App\Plugins\OrderManagement\src\Models\Order::class);
        });

        if (file_exists(__DIR__.'/routes/tenant.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/tenant.php');
        }
    }
}
