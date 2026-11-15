<?php

declare(strict_types=1);

use App\Core\Plugins\Routing\PluginRoutes;
use App\Plugins\CouponsPromotions\src\Http\Controllers\Api\CouponApiController;
use Illuminate\Support\Facades\Route;

PluginRoutes::tenant('coupons-promotions')->prefix('api/coupons')->name('api.coupons.')->group(function () {
    Route::post('/validate', [CouponApiController::class, 'validate'])->name('validate');
});
