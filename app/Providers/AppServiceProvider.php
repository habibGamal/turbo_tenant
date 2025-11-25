<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Route;
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
        Vite::prefetch(concurrency: 3);
        Livewire::setUpdateRoute(function ($handle) {
            return Route::post('/livewire/update', $handle)
                ->middleware(['web', InitializeTenancyByDomain::class]);
        });
        FilePreviewController::$middleware = ['web', 'universal', InitializeTenancyByDomain::class];


    }
}
