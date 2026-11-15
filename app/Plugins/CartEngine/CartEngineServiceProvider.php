<?php

declare(strict_types=1);

namespace App\Plugins\CartEngine;

use App\Http\Responses\ApiResponse;
use App\Plugins\CartEngine\src\Exceptions\CartItemNotFound;
use App\Plugins\CartEngine\src\Services\Calculators\CartPriceCalculator;
use App\Plugins\CartEngine\src\Services\CartLineTypeRegistry;
use App\Plugins\CartEngine\src\Services\CartService;
use App\Plugins\CartEngine\src\Services\Contracts\CartContextResolverInterface;
use App\Plugins\CartEngine\src\Services\Contracts\CartPriceCalculatorInterface;
use App\Plugins\CartEngine\src\Services\Contracts\CartRepositoryInterface;
use App\Plugins\CartEngine\src\Services\Contracts\CartServiceInterface;
use App\Plugins\CartEngine\src\Services\Contracts\ProductValidatorInterface;
use App\Plugins\CartEngine\src\Services\Repositories\CartRepository;
use App\Plugins\CartEngine\src\Services\Resolvers\CartContextResolver;
use App\Plugins\CartEngine\src\Services\Validators\ProductValidator;
use App\Plugins\CartEngine\src\Services\Validators\Rules\ExtrasRule;
use App\Plugins\CartEngine\src\Services\Validators\Rules\VariantRule;
use App\Plugins\CartEngine\src\Services\Validators\Rules\WeightRule;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

final class CartEngineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 1. Bind Domain Interfaces to Implementations
        $this->app->bind(CartPriceCalculatorInterface::class, CartPriceCalculator::class);
        $this->app->bind(CartRepositoryInterface::class, CartRepository::class);
        $this->app->bind(CartContextResolverInterface::class, CartContextResolver::class);
        $this->app->singleton(VariantRule::class);
        $this->app->singleton(WeightRule::class);
        $this->app->singleton(ExtrasRule::class);
        $this->app->bind(ProductValidatorInterface::class, ProductValidator::class);
        $this->app->singleton(CartLineTypeRegistry::class);
        $this->app->bind(CartServiceInterface::class, CartService::class);
        $this->app->singleton(CartService::class);

        // 3. Register Bootstrap Contributor
        $this->app->singleton(src\Bootstrap\CartEngineBootstrapContributor::class);
        $this->app->tag([src\Bootstrap\CartEngineBootstrapContributor::class], 'tenant.bootstrap.contributors');
    }

    public function boot(): void
    {
        if (file_exists(__DIR__.'/routes/tenant.php')) {
            $this->loadRoutesFrom(__DIR__.'/routes/tenant.php');
        }

        $this->callAfterResolving(ExceptionHandler::class, function (ExceptionHandler $handler): void {
            if ($handler instanceof Handler) {
                $handler->renderable(function (CartItemNotFound $e, Request $request) {
                    if ($request->is('api/*')) {
                        return ApiResponse::error('Cart item not found', 404);
                    }

                    return null;
                });
            }
        });
    }
}
