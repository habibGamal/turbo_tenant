<?php

declare(strict_types=1);

use App\Plugins\OrderManagement\src\Http\Controllers\Api\OrderController;
use App\Plugins\OrderManagement\src\Http\Controllers\Api\OrderStatusController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

// In-flight settlement route (remains reachable regardless of plugin state)
Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->prefix('api')->group(function () {
    Route::post('/order-status', [OrderStatusController::class, 'update'])
        ->name('api.order-status.update')
        ->withoutMiddleware([VerifyCsrfToken::class]);
});

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    'plugin.active:order-management',
])->prefix('api')->group(function () {
    Route::get('/checkout-data', [OrderController::class, 'checkoutData'])->name('api.orders.checkout-data');
    Route::post('/orders/place', [OrderController::class, 'placeOrder'])->name('api.orders.place');

    Route::middleware('auth')->group(function () {
        Route::get('/orders', [OrderController::class, 'index'])->name('api.orders.index');
        Route::get('/orders/{orderId}', [OrderController::class, 'show'])->name('api.orders.show');
    });
});
