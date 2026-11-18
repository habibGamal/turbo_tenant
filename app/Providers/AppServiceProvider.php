<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Database\Eloquent\Factories\Factory;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Factory::guessFactoryNamesUsing(function (string $modelName) {
            return 'Database\\Factories\\'.class_basename($modelName).'Factory';
        });

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
        }

        if (request()->isSecure() || request()->header('X-Forwarded-Proto') === 'https') {
            URL::forceScheme('https');
        }

        // Override Sanctum's CSRF cookie route after all service providers boot so tenant subdomains
        // initialize tenancy before starting the session, preventing central/tenant DB session mismatches.
        $this->app->booted(function (): void {
            Route::get('/sanctum/csrf-cookie', function () {
                return response()->noContent()->header('X-CSRF-TOKEN', csrf_token());
            })->middleware(['web', 'universal', InitializeTenancyByDomain::class])->name('sanctum.csrf-cookie');
        });
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
