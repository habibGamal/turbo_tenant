<?php

declare(strict_types=1);

use App\Plugins\CouponsPromotions\src\Http\Controllers\CouponController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::middleware('auth')->group(function () {
        Route::post('/coupons/validate', [CouponController::class, 'validate'])->name('coupons.validate');
    });
});
