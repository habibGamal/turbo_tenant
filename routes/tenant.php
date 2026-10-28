<?php

declare(strict_types=1);

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes (Platform Host Shell)
|--------------------------------------------------------------------------
|
| Plugin domain routes are encapsulated in each plugin's routes/tenant.php
| loaded via their respective ServiceProviders.
|
*/

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::get('/manifest.json', function () {
        return response()->file(Storage::path('manifest.json'));
    });

    Route::get('/theme.css', function () {
        return response()->file(Storage::path('theme.css'), [
            'Content-Type' => 'text/css',
        ]);
    });

    Route::view('/', 'app')->name('home');

    // Profile routes (requires authentication)
    Route::middleware('auth')->group(function () {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });

    // Core Tenant Platform APIs
    Route::prefix('api/tenant')->group(function () {
        Route::get('/bootstrap', App\Http\Controllers\Api\BootstrapController::class)->name('api.tenant.bootstrap');

        Route::prefix('auth')->group(function () {
            Route::post('/login', [App\Http\Controllers\Api\AuthController::class, 'login'])->name('api.tenant.auth.login');
            Route::post('/google/native', [App\Http\Controllers\Api\GoogleNativeAuthController::class, 'login'])
                ->middleware('throttle:10,1')
                ->name('api.tenant.auth.google.native');
            Route::post('/register', [App\Http\Controllers\Api\AuthController::class, 'register'])->name('api.tenant.auth.register');
            Route::post('/logout', [App\Http\Controllers\Api\AuthController::class, 'logout'])->name('api.tenant.auth.logout');
            Route::get('/user', [App\Http\Controllers\Api\AuthController::class, 'user'])->name('api.tenant.auth.user');
            Route::post('/forgot-password', [App\Http\Controllers\Api\AuthController::class, 'forgotPassword'])
                ->middleware('throttle:6,1')
                ->name('api.tenant.auth.forgot-password');
            Route::post('/reset-password', [App\Http\Controllers\Api\AuthController::class, 'resetPassword'])
                ->middleware('throttle:6,1')
                ->name('api.tenant.auth.reset-password');

            Route::middleware('auth')->group(function () {
                Route::patch('/profile', [App\Http\Controllers\Api\AuthController::class, 'updateProfile'])->name('api.tenant.auth.profile');
                Route::put('/password', [App\Http\Controllers\Api\AuthController::class, 'updatePassword'])
                    ->middleware('throttle:6,1')
                    ->name('api.tenant.auth.password');
            });
        });
    });

    require __DIR__.'/auth.php';

    // SPA Catch-all Route for client-side routing
    Route::get('/{any}', fn () => view('app'))
        ->where('any', '^(?!api|filament|storage|manifest\.json|theme\.css).*$')
        ->name('spa.catchall');
});
