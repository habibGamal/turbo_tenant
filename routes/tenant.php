<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant routes for your application.
| These routes are loaded by the TenantRouteServiceProvider.
|
| Feel free to customize them however you want. Good luck!
|
*/

Route::get('/storage/{path}', function ($path) {
    // dd($path,tenant()->id);
    $file = Storage::path($path);
    // dd($file);
    $mimeType = mime_content_type($file);
    // dd(Storage::disk('public')->path($url), mime_content_type(Storage::disk('public')->path($url)), filesize(Storage::disk('public')->path($url)));
    if (ob_get_length()) {
        ob_end_clean();
    }
    $response = response()->file(
        $file,
        [
            'Content-Type' => $mimeType,
            'Content-Length' => filesize($file),
            'Accept-Ranges' => 'bytes',
        ]
    );

    return $response;
})->where('path', '.*')->middleware([
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
]);
Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {

    Route::get('/', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
    Route::get('/products/{product}', [App\Http\Controllers\ProductController::class, 'show'])->name('products.show');
    // Cart routes
    Route::get('/cart', [App\Http\Controllers\CartController::class, 'index'])->name('cart.index');
    Route::post('/cart/items', [App\Http\Controllers\CartController::class, 'store'])->name('cart.store');
    Route::patch('/cart/items/{itemId}', [App\Http\Controllers\CartController::class, 'update'])->name('cart.update');
    Route::delete('/cart/items/{itemId}', [App\Http\Controllers\CartController::class, 'destroy'])->name('cart.destroy');
    Route::delete('/cart', [App\Http\Controllers\CartController::class, 'clear'])->name('cart.clear');
    Route::post('/cart/sync', [App\Http\Controllers\CartController::class, 'sync'])->name('cart.sync');
    Route::get('/menu', [App\Http\Controllers\MenuController::class, 'index'])->name('menu');

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
    // API routes
    Route::post('/api/order-status', [App\Http\Controllers\Api\OrderStatusController::class, 'update'])->name('api.order-status.update')->withoutMiddleware([VerifyCsrfToken::class]);
    Route::post('/api/webhooks/paymob', [App\Http\Controllers\PaymobWebhookController::class, 'handle'])->name('webhooks.paymob')->withoutMiddleware([VerifyCsrfToken::class]);

    require __DIR__.'/auth.php';

    // Paymob webhook (no auth required)
});
