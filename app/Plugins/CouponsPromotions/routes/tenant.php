<?php

declare(strict_types=1);

use App\Plugins\CouponsPromotions\src\Http\Controllers\Api\CouponApiController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    'plugin.active:coupons-promotions',
])->prefix('api/coupons')->name('api.coupons.')->group(function () {
    Route::post('/validate', [CouponApiController::class, 'validate'])->name('validate');
});
