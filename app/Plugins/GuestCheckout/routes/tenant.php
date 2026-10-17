<?php

declare(strict_types=1);

use App\Plugins\GuestCheckout\src\Http\Controllers\GuestOrderTrackingController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::post('/orders/track', [GuestOrderTrackingController::class, 'trackGuestOrder'])->name('orders.track');
    Route::get('/track-order', [GuestOrderTrackingController::class, 'showTrackPage'])->name('orders.track.page');
});
