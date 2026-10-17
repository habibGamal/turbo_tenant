<?php

declare(strict_types=1);

use App\Plugins\OrderManagement\src\Http\Controllers\Api\OrderStatusController;
use App\Plugins\OrderManagement\src\Http\Controllers\OrderController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    $guestMiddleware = class_exists(\App\Plugins\GuestCheckout\src\Http\Middleware\EnsureCheckoutAccessMiddleware::class)
        ? [\App\Plugins\GuestCheckout\src\Http\Middleware\EnsureCheckoutAccessMiddleware::class]
        : [];

    Route::middleware($guestMiddleware)->group(function () {
        Route::get('/checkout', [OrderController::class, 'checkout'])->name('checkout');
        Route::post('/orders/place', [OrderController::class, 'placeOrder'])->name('orders.place');
    });

    Route::middleware('auth')->group(function () {
        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{orderId}', [OrderController::class, 'show'])->name('orders.show');
    });

    Route::post('/api/order-status', [OrderStatusController::class, 'update'])
        ->name('api.order-status.update')
        ->withoutMiddleware([VerifyCsrfToken::class]);
});
