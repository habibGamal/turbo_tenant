<?php

declare(strict_types=1);

use App\Core\Plugins\Routing\PluginRoutes;
use App\Plugins\OrderManagement\src\Http\Controllers\Api\OrderController;
use App\Plugins\OrderManagement\src\Http\Controllers\Api\OrderStatusController;
use App\Plugins\OrderManagement\src\Http\Middleware\VerifyIntegrationToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

// In-flight settlement route (remains reachable regardless of plugin state)
PluginRoutes::tenantUngated()->prefix('api')->group(function () {
    Route::post('/order-status', [OrderStatusController::class, 'update'])
        ->middleware([
            VerifyIntegrationToken::class,
            'throttle:60,1',
        ])
        ->name('api.order-status.update')
        ->withoutMiddleware([VerifyCsrfToken::class]);
});

PluginRoutes::tenant('order-management')->prefix('api')->group(function () {
    Route::post('/orders/place', [OrderController::class, 'placeOrder'])->name('api.orders.place');

    Route::middleware('auth')->group(function () {
        Route::get('/orders', [OrderController::class, 'index'])->name('api.orders.index');
        Route::get('/orders/{orderId}', [OrderController::class, 'show'])->name('api.orders.show');
    });
});
