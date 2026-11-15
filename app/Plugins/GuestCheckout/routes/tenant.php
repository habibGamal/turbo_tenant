<?php

declare(strict_types=1);

use App\Core\Plugins\Routing\PluginRoutes;
use App\Plugins\GuestCheckout\src\Http\Controllers\Api\GuestCheckoutApiController;
use Illuminate\Support\Facades\Route;

PluginRoutes::tenant('guest-checkout')->prefix('api')->group(function () {
    Route::get('/guest-checkout', [GuestCheckoutApiController::class, 'index']);
    Route::post('/guest-checkout/track', [GuestCheckoutApiController::class, 'track'])->name('api.guest-checkout.track');
    Route::get('/tenant/guest-checkout', [GuestCheckoutApiController::class, 'index']);
    Route::post('/tenant/guest-checkout/track', [GuestCheckoutApiController::class, 'track']);
});
