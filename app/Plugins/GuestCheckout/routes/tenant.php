<?php

declare(strict_types=1);

use App\Plugins\GuestCheckout\src\Http\Controllers\Api\GuestCheckoutApiController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    'plugin.active:guest-checkout',
])->prefix('api')->group(function () {
    Route::get('/guest-checkout', [GuestCheckoutApiController::class, 'index']);
    Route::post('/guest-checkout/track', [GuestCheckoutApiController::class, 'track'])->name('api.guest-checkout.track');
    Route::get('/tenant/guest-checkout', [GuestCheckoutApiController::class, 'index']);
    Route::post('/tenant/guest-checkout/track', [GuestCheckoutApiController::class, 'track']);
});
