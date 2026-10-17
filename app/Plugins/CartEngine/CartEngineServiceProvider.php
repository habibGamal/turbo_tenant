<?php

declare(strict_types=1);

namespace App\Plugins\CartEngine;

use App\Plugins\CartEngine\src\DTOs\CartEngineConfig;
use App\Plugins\CartEngine\src\Services\Calculators\CartPriceCalculator;
use App\Plugins\CartEngine\src\Services\CartService;
use App\Plugins\CartEngine\src\Services\Contracts\CartContextResolverInterface;
use App\Plugins\CartEngine\src\Services\Contracts\CartItemFormatterInterface;
use App\Plugins\CartEngine\src\Services\Contracts\CartPriceCalculatorInterface;
use App\Plugins\CartEngine\src\Services\Contracts\CartRepositoryInterface;
use App\Plugins\CartEngine\src\Services\Contracts\CartServiceInterface;
use App\Plugins\CartEngine\src\Services\Contracts\ProductValidatorInterface;
use App\Plugins\CartEngine\src\Services\Formatters\CartItemFormatter;
use App\Plugins\CartEngine\src\Services\Repositories\CartRepository;
use App\Plugins\CartEngine\src\Services\Resolvers\CartContextResolver;
use App\Plugins\CartEngine\src\Services\Validators\ProductValidator;
use App\Plugins\StoreSettings\src\Services\SettingService;
use Illuminate\Support\ServiceProvider;

final class CartEngineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 1. Composition Root: Bind typed Config DTO (Pattern 1)
        $this->app->singleton(CartEngineConfig::class, function ($app) {
            $settings = $app->make(SettingService::class);

            return new CartEngineConfig(
                sessionLifetimeDays: (int) $settings->get('cart-engine.session_lifetime_days', 30),
                maxItemsPerCart: (int) $settings->get('cart-engine.max_items_per_cart', 100)
            );
        });

        // 2. Bind Domain Interfaces to Implementations
        $this->app->bind(CartPriceCalculatorInterface::class, CartPriceCalculator::class);
        $this->app->bind(CartItemFormatterInterface::class, CartItemFormatter::class);
        $this->app->bind(CartRepositoryInterface::class, CartRepository::class);
        $this->app->bind(CartContextResolverInterface::class, CartContextResolver::class);
        $this->app->bind(ProductValidatorInterface::class, ProductValidator::class);
        $this->app->bind(CartServiceInterface::class, CartService::class);
        $this->app->singleton(CartService::class);

    }

    public function boot(): void
    {
        if (file_exists(__DIR__.'/routes/tenant.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/tenant.php');
        }
    }
}
