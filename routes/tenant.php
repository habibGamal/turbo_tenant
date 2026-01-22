<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;

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

Route::get('/storage/{path}', function (\Illuminate\Http\Request $request, $path) {
    $file = Storage::path($path);

    if (!file_exists($file)) {
        abort(404);
    }

    // If no manipulation parameters are present, serve the original file
    if ($request->query->count() === 0) {
        $mimeType = mime_content_type($file);
        if (ob_get_length()) {
            ob_end_clean();
        }

        return response()->file($file, [
            'Content-Type' => $mimeType,
            'Content-Length' => filesize($file),
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }

    // Generate a cache key based on the file path and query parameters
    $cacheKey = md5($path . serialize($request->all()));
    $extension = pathinfo($file, PATHINFO_EXTENSION);
    $cacheFilename = $cacheKey . '.' . $extension;
    $cachePath = 'cache/' . $cacheFilename;
    $fullCachePath = Storage::path($cachePath) . '.webp';

    // Ensure cache directory exists
    if (!Storage::exists('cache')) {
        Storage::makeDirectory('cache');
    }

    // If cached file doesn't exist, create it
    if (!file_exists($fullCachePath)) {
        try {
            $image = \Spatie\Image\Image::load($file)->optimize();
            if ($request->has('w')) {
                $image->width((int) $request->input('w'));
            }

            if ($request->has('h')) {
                $image->height((int) $request->input('h'));
            }

            if ($request->has('fit')) {
                $image->fit(\Spatie\Image\Enums\Fit::tryFrom($request->input('fit')) ?? \Spatie\Image\Enums\Fit::Contain);
            }

            $image->save($fullCachePath);
        } catch (\Exception $e) {
            logger()->error('Image manipulation failed: ' . $e->getMessage());
            // Fallback to original file if manipulation fails
            return response()->file($file);
        }
    }

    $mimeType = mime_content_type($fullCachePath);
    if (ob_get_length()) {
        ob_end_clean();
    }

    return response()->file($fullCachePath, [
        'Content-Type' => $mimeType,
        'Content-Length' => filesize($fullCachePath),
        'Accept-Ranges' => 'bytes',
        'Cache-Control' => 'public, max-age=31536000',
    ]);
})->where('path', '.*')->middleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
        ]);
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
    Route::get('/', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
    Route::get('/products/{product}', [App\Http\Controllers\ProductController::class, 'show'])->name('products.show');
    Route::post('/products/{product}/reviews', [\App\Http\Controllers\ReviewController::class, 'store'])->name('reviews.store');
    // Cart routes
    Route::get('/cart', [App\Http\Controllers\CartController::class, 'index'])->name('cart.index');
    Route::post('/cart/items', [App\Http\Controllers\CartController::class, 'store'])->name('cart.store');
    Route::patch('/cart/items/{itemId}', [App\Http\Controllers\CartController::class, 'update'])->name('cart.update');
    Route::delete('/cart/items/{itemId}', [App\Http\Controllers\CartController::class, 'destroy'])->name('cart.destroy');
    Route::delete('/cart', [App\Http\Controllers\CartController::class, 'clear'])->name('cart.clear');
    Route::post('/cart/sync', [App\Http\Controllers\CartController::class, 'sync'])->name('cart.sync');
    Route::get('/menu', [App\Http\Controllers\MenuController::class, 'index'])->name('menu');
    Route::get('/sections/{section}', [App\Http\Controllers\SectionController::class, 'show'])->name('sections.show');

    // Order routes
    Route::middleware('auth')->group(function () {
        Route::get('/checkout', [App\Http\Controllers\OrderController::class, 'checkout'])->name('checkout');
        Route::post('/orders/place', [App\Http\Controllers\OrderController::class, 'placeOrder'])->name('orders.place');
        Route::get('/orders', [App\Http\Controllers\OrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{orderId}', [App\Http\Controllers\OrderController::class, 'show'])->name('orders.show');
        Route::get('/orders/{orderId}/payment/callback', [App\Http\Controllers\OrderController::class, 'paymentCallback'])->name('orders.payment.callback');
        Route::post('/coupons/validate', [App\Http\Controllers\CouponController::class, 'validate'])->name('coupons.validate');

        // Address routes
        Route::post('/addresses', [App\Http\Controllers\AddressController::class, 'store'])->name('addresses.store');
        Route::patch('/addresses/{address}', [App\Http\Controllers\AddressController::class, 'update'])->name('addresses.update');
        Route::delete('/addresses/{address}', [App\Http\Controllers\AddressController::class, 'destroy'])->name('addresses.destroy');
        Route::get('/governorates-areas', [App\Http\Controllers\AddressController::class, 'getGovernoratesAreas'])->name('governorates-areas.index');

        // Profile routes
        Route::get('/profile', [App\Http\Controllers\ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [App\Http\Controllers\ProfileController::class, 'destroy'])->name('profile.destroy');

        // Favorites route
        Route::get('/favorites', function () {
            return \Inertia\Inertia::render('Favorites');
        })->name('favorites.index');
    });
    // API routes
    Route::get('/api/products/by-ids', [App\Http\Controllers\Api\ProductController::class, 'getByIds'])->name('api.products.by-ids');
    Route::post('/api/order-status', [App\Http\Controllers\Api\OrderStatusController::class, 'update'])->name('api.order-status.update')->withoutMiddleware([VerifyCsrfToken::class]);
    Route::get('/api/search/suggestions', [App\Http\Controllers\Api\SearchController::class, 'suggestions'])->name('api.search.suggestions');
    Route::get('/api/products/{product}', [App\Http\Controllers\Api\ProductController::class, 'show'])->name('api.products.show');
    Route::get('/api/products/search', \App\Http\Controllers\Api\ProductSearchController::class)->name('api.products.search');

    // Payment gateway webhooks (no auth, no CSRF)
    Route::post('/api/webhooks/paymob', [App\Http\Controllers\PaymobWebhookController::class, 'handle'])->name('webhooks.paymob')->withoutMiddleware([VerifyCsrfToken::class]);
    Route::post('/api/webhooks/kashier', [App\Http\Controllers\KashierWebhookController::class, 'handle'])->name('webhooks.kashier')->withoutMiddleware([VerifyCsrfToken::class]);

    // Kashier payment page (requires auth)
    Route::middleware('auth')->group(function () {
        Route::get('/payments/{order}/kashier', [App\Http\Controllers\PaymentController::class, 'showKashierPayment'])->name('payment.kashier');
    });

    Route::get('/pages/{slug}', [\App\Http\Controllers\PageController::class, 'show'])->name('pages.show');

    require __DIR__ . '/auth.php';
});
