<?php

declare(strict_types=1);

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

foreach (config('tenancy.central_domains') as $domain) {
    Route::domain($domain)->group(function () {
        Route::get('/', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
        Route::get('/products/{product}', [App\Http\Controllers\ProductController::class, 'show'])->name('products.show');
        // Cart routes
        Route::get('/cart', [App\Http\Controllers\CartController::class, 'index'])->name('cart.index');
        Route::post('/cart/items', [App\Http\Controllers\CartController::class, 'store'])->name('cart.store');
        Route::patch('/cart/items/{itemId}', [App\Http\Controllers\CartController::class, 'update'])->name('cart.update');
        Route::delete('/cart/items/{itemId}', [App\Http\Controllers\CartController::class, 'destroy'])->name('cart.destroy');
        Route::delete('/cart', [App\Http\Controllers\CartController::class, 'clear'])->name('cart.clear');
        Route::post('/cart/sync', [App\Http\Controllers\CartController::class, 'sync'])->name('cart.sync');

        // Order routes
        Route::middleware('auth')->group(function () {
            Route::get('/checkout', [App\Http\Controllers\OrderController::class, 'checkout'])->name('checkout');
            Route::post('/orders/place', [App\Http\Controllers\OrderController::class, 'placeOrder'])->name('orders.place');
            Route::get('/orders', [App\Http\Controllers\OrderController::class, 'index'])->name('orders.index');
            Route::get('/orders/{orderId}', [App\Http\Controllers\OrderController::class, 'show'])->name('orders.show');
            Route::get('/orders/{orderId}/payment/callback', [App\Http\Controllers\OrderController::class, 'paymentCallback'])->name('orders.payment.callback');

            // Address routes
            Route::post('/addresses', [App\Http\Controllers\AddressController::class, 'store'])->name('addresses.store');
            Route::get('/governorates-areas', [App\Http\Controllers\AddressController::class, 'getGovernoratesAreas'])->name('governorates-areas.index');
        });
        Route::post('/api/webhooks/paymob', [App\Http\Controllers\PaymobWebhookController::class, 'handle'])->name('webhooks.paymob')->withoutMiddleware([VerifyCsrfToken::class]);
        require __DIR__.'/auth.php';
    });
}

// Route::get('/', function () {
//     return Inertia::render('Welcome', [
//         'canLogin' => Route::has('login'),
//         'canRegister' => Route::has('register'),
//         'laravelVersion' => Application::VERSION,
//         'phpVersion' => PHP_VERSION,
//     ]);
// });

// Route::get('/dashboard', function () {
//     return Inertia::render('Dashboard');
// })->middleware(['auth', 'verified'])->name('dashboard');

// Route::middleware('auth')->group(function () {
//     Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
//     Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
//     Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
// });
