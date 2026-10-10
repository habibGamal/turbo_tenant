<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Order;
use App\Observers\OrderObserver;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Livewire\Features\SupportFileUploads\FilePreviewController;
use Livewire\Livewire;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\POS\Contracts\POSApiClientInterface::class, \App\Services\POS\POSApiClient::class);
        $this->app->singleton(\App\Services\POS\Contracts\POSProductResolverInterface::class, \App\Services\POS\POSProductResolver::class);
        $this->app->singleton(\App\Services\POS\POSCustomerDataFormatter::class, \App\Services\POS\POSCustomerDataFormatter::class);
        $this->app->singleton(\App\Services\POS\Contracts\OrderPOSPayloadBuilderInterface::class, \App\Services\POS\OrderPOSPayloadBuilder::class);
        $this->app->bind(\App\Services\POS\Contracts\POSErrorResponseHandlerInterface::class, \App\Services\POS\POSErrorResponseHandler::class);
        $this->app->bind(\App\Services\OrderPOSService::class, \App\Services\OrderPOSService::class);

        // Cart domain bindings
        $this->app->bind(\App\Services\Cart\Contracts\CartPriceCalculatorInterface::class, \App\Services\Cart\Calculators\CartPriceCalculator::class);
        $this->app->bind(\App\Services\Cart\Contracts\CartItemFormatterInterface::class, \App\Services\Cart\Formatters\CartItemFormatter::class);
        $this->app->bind(\App\Services\Cart\Contracts\CartContextResolverInterface::class, \App\Services\Cart\Resolvers\CartContextResolver::class);
        $this->app->bind(\App\Services\Cart\Contracts\ProductValidatorInterface::class, \App\Services\Cart\Validators\ProductValidator::class);
        $this->app->bind(\App\Services\Cart\Contracts\CartRepositoryInterface::class, \App\Services\Cart\Repositories\CartRepository::class);
        $this->app->bind(\App\Services\Cart\Contracts\CartServiceInterface::class, \App\Services\CartService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('app.env') !== 'local') {
            URL::forceScheme('https');
        }
        Vite::prefetch(concurrency: 3);

        // Only configure tenant-specific Livewire routes if not on central domain
        if (! $this->isCentralDomain()) {
            Livewire::setUpdateRoute(function ($handle) {
                return Route::post('/livewire/update', $handle)
                    ->middleware(['web', InitializeTenancyByDomain::class]);
            });
            FilePreviewController::$middleware = ['web', 'universal', InitializeTenancyByDomain::class];

            Order::observe(OrderObserver::class);
        }

        if (request()->isSecure() || request()->header('X-Forwarded-Proto') === 'https') {
            URL::forceScheme('https');
        }
    }

    /**
     * Check if the current request is on a central domain.
     */
    private function isCentralDomain(): bool
    {
        $centralDomains = config('tenancy.central_domains', []);
        $host = request()->getHost();

        return in_array($host, $centralDomains, true);
    }
}
